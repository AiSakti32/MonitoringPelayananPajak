<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\CaseHistoryRepository;
use App\Repositories\CaseRepository;
use App\Repositories\CaseTypeRepository;
use App\Repositories\OfficerRepository;
use App\Repositories\SourceRepository;
use App\Repositories\StatusRepository;
use App\Services\CaseHistoryPresenter;
use App\Services\CaseNeedsConfirmationException;
use App\Services\CaseUpsertService;
use App\Services\DeadlineClassifier;
use App\Services\FilterNormalizer;
use Throwable;

final class CaseController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly CaseRepository $cases = new CaseRepository(),
        private readonly CaseHistoryRepository $histories = new CaseHistoryRepository(),
        private readonly CaseTypeRepository $types = new CaseTypeRepository(),
        private readonly StatusRepository $statuses = new StatusRepository(),
        private readonly SourceRepository $sources = new SourceRepository(),
        private readonly OfficerRepository $officers = new OfficerRepository(),
        private readonly CaseUpsertService $upsertService = new CaseUpsertService(),
        private readonly CaseHistoryPresenter $historyPresenter = new CaseHistoryPresenter(),
    ) {
    }

    public function index(): void
    {
        $user = Session::user();
        $filters = FilterNormalizer::normalizeCaseFilters();
        $filters = FilterNormalizer::applyRoleScope($filters, $user);

        $page = (int) Request::input('page', 1);

        try {
            $paginator = $this->cases->paginate($filters, $page, self::PER_PAGE, 'urgency');
            $loadError = null;
        } catch (Throwable $e) {
            $paginator = null;
            $loadError = (bool) config('app.debug', false)
                ? $e->getMessage()
                : 'Gagal memuat daftar kasus.';
        }

        $items = [];
        if ($paginator !== null) {
            foreach ($paginator->items as $row) {
                $row['deadline'] = DeadlineClassifier::classify(
                    (string) $row['due_date'],
                    (bool) (int) $row['status_is_completed']
                );
                $items[] = $row;
            }
            $paginator = new \App\Core\Paginator($items, $paginator->total, $paginator->page, $paginator->perPage);
        }

        $this->render('cases/index', [
            'pageTitle' => 'Daftar Kasus',
            'paginator' => $paginator,
            'filters' => $filters,
            'loadError' => $loadError,
            'basePath' => '/cases',
            'isAdmin' => ($user['role'] ?? '') === 'admin',
            'officers' => $this->officers->activeOptions(),
            'statuses' => $this->statuses->activeOptions(),
            'types' => $this->types->activeOptions(),
            'sources' => $this->sources->activeOptions(),
        ]);
    }

    public function create(): void
    {
        $user = Session::user();
        $this->render('cases/form', [
            'pageTitle' => 'Simpan/Update Kasus',
            'mode' => 'upsert',
            'item' => null,
            'errors' => get_flash('errors', []),
            'old' => get_flash('old', []),
            'needsConfirm' => (bool) get_flash('needs_confirm', false),
            'existingCase' => get_flash('existing_case'),
            'confirmMessage' => get_flash('confirm_message'),
            'options' => $this->formOptions($user),
            'lockedOfficerId' => ($user['role'] ?? '') === 'petugas' ? ($user['officer_id'] ?? null) : null,
        ]);
    }

    public function upsert(): void
    {
        $user = Session::user();
        $userId = (int) $user['id'];
        $input = Request::all();

        // Petugas: force own officer
        if (($user['role'] ?? '') === 'petugas') {
            if (empty($user['officer_id'])) {
                Session::flash('error', 'Akun petugas belum dikaitkan ke master petugas.');
                redirect('/cases/create');
            }
            $input['officer_id'] = (int) $user['officer_id'];
        }

        $validated = $this->upsertService->validate($input);
        if (!$validated['ok']) {
            $this->redirectWithErrors('/cases/create', $validated['errors'], $this->oldFromInput($input));
        }

        // Authorization on update: petugas may only update own cases
        $existing = $this->cases->findByNumber($validated['data']['case_number']);
        if ($existing !== null && ($user['role'] ?? '') === 'petugas') {
            if ((int) $existing['officer_id'] !== (int) $user['officer_id']) {
                Session::flash('error', 'Anda tidak berhak memperbarui kasus milik petugas lain.');
                Session::flash('old', $this->oldFromInput($input));
                redirect('/cases/create');
            }
        }

        $confirm = Request::input('confirm_existing') === '1';

        try {
            $result = $this->upsertService->upsert($validated['data'], $userId, $confirm);
            $this->redirectWithSuccess('/cases/' . $result['case_id'], $result['message']);
        } catch (CaseNeedsConfirmationException $e) {
            Session::flash('needs_confirm', true);
            Session::flash('confirm_message', $e->getMessage());
            Session::flash('existing_case', $e->existingCase());
            Session::flash('old', $this->oldFromInput($input));
            Session::flash('error', $e->getMessage());
            redirect('/cases/create');
        } catch (Throwable $e) {
            Session::flash('error', (bool) config('app.debug', false) ? $e->getMessage() : 'Gagal menyimpan kasus.');
            Session::flash('old', $this->oldFromInput($input));
            redirect('/cases/create');
        }
    }

    public function show(string $id): void
    {
        $user = Session::user();
        $case = $this->cases->findById((int) $id);
        if ($case === null) {
            abort(404, 'Kasus tidak ditemukan.');
        }

        if (($user['role'] ?? '') === 'petugas' && (int) $case['officer_id'] !== (int) ($user['officer_id'] ?? 0)) {
            abort(403, 'Anda tidak berhak melihat kasus ini.');
        }

        $order = (string) Request::input('timeline', 'desc');
        $histories = $this->histories->listByCaseId((int) $id, $order);
        $histories = $this->historyPresenter->presentMany($histories);
        $deadline = DeadlineClassifier::classify(
            (string) $case['due_date'],
            (bool) (int) $case['status_is_completed']
        );
        $daysRemaining = DeadlineClassifier::daysRemaining(
            (string) $case['due_date'],
            (bool) (int) $case['status_is_completed']
        );

        $this->render('cases/show', [
            'pageTitle' => 'Detail Kasus ' . $case['case_number'],
            'case' => $case,
            'histories' => $histories,
            'deadline' => $deadline,
            'daysRemaining' => $daysRemaining,
            'timelineOrder' => $order === 'asc' ? 'asc' : 'desc',
        ]);
    }

    public function edit(string $id): void
    {
        $case = $this->cases->findById((int) $id);
        if ($case === null) {
            abort(404, 'Kasus tidak ditemukan.');
        }

        $user = Session::user();
        if (($user['role'] ?? '') === 'petugas' && (int) $case['officer_id'] !== (int) ($user['officer_id'] ?? 0)) {
            abort(403, 'Anda tidak berhak mengedit kasus ini.');
        }

        // Reuse upsert form with prefill; confirm already implied for edit of known id
        $this->render('cases/form', [
            'pageTitle' => 'Simpan/Update Kasus',
            'mode' => 'edit',
            'item' => $case,
            'errors' => get_flash('errors', []),
            'old' => get_flash('old', []),
            'needsConfirm' => false,
            'existingCase' => $case,
            'confirmMessage' => null,
            'options' => $this->formOptions($user),
            'lockedOfficerId' => ($user['role'] ?? '') === 'petugas' ? ($user['officer_id'] ?? null) : null,
            'forceConfirmExisting' => true,
        ]);
    }

    public function update(string $id): void
    {
        $case = $this->cases->findById((int) $id);
        if ($case === null) {
            abort(404, 'Kasus tidak ditemukan.');
        }

        $user = Session::user();
        if (($user['role'] ?? '') === 'petugas' && (int) $case['officer_id'] !== (int) ($user['officer_id'] ?? 0)) {
            abort(403, 'Anda tidak berhak memperbarui kasus ini.');
        }

        $input = Request::all();
        // Keep case number immutable on edit path
        $input['case_number'] = $case['case_number'];
        if (($user['role'] ?? '') === 'petugas') {
            $input['officer_id'] = (int) $user['officer_id'];
        }

        $validated = $this->upsertService->validate($input);
        if (!$validated['ok']) {
            $this->redirectWithErrors('/cases/' . $id . '/edit', $validated['errors'], $this->oldFromInput($input));
        }

        try {
            $result = $this->upsertService->upsert($validated['data'], (int) $user['id'], true);
            $this->redirectWithSuccess('/cases/' . $result['case_id'], $result['message']);
        } catch (Throwable $e) {
            Session::flash('error', (bool) config('app.debug', false) ? $e->getMessage() : 'Gagal memperbarui kasus.');
            redirect('/cases/' . $id . '/edit');
        }
    }

    /** AJAX lookup by case number */
    public function lookupByNumber(): void
    {
        $raw = strtoupper(trim((string) Request::input('case_number', '')));
        if (!preg_match('/^[A-Z][0-9]{10}$/', $raw)) {
            json_response([
                'found' => false,
                'valid' => false,
                'message' => 'Format nomor kasus tidak valid.',
            ], 422);
        }

        $case = $this->cases->findByNumber($raw);
        if ($case === null) {
            json_response([
                'found' => false,
                'valid' => true,
                'message' => 'Nomor kasus belum terdaftar — akan disimpan sebagai kasus baru.',
            ]);
        }

        $user = Session::user();
        if (($user['role'] ?? '') === 'petugas' && (int) $case['officer_id'] !== (int) ($user['officer_id'] ?? 0)) {
            json_response([
                'found' => true,
                'valid' => true,
                'forbidden' => true,
                'message' => 'Nomor kasus sudah terdaftar milik petugas lain.',
            ], 403);
        }

        json_response([
            'found' => true,
            'valid' => true,
            'forbidden' => false,
            'message' => 'Nomor kasus sudah terdaftar — form diisi data terkini. Ubah yang perlu, lalu perbarui.',
            'case' => [
                'id' => (int) $case['id'],
                'case_number' => $case['case_number'],
                'npwp' => $case['npwp'],
                'taxpayer_name' => $case['taxpayer_name'],
                'case_type_id' => (int) $case['case_type_id'],
                'case_type_name' => $case['case_type_name'],
                'status_id' => (int) $case['status_id'],
                'status_name' => $case['status_name'],
                'source_id' => (int) $case['source_id'],
                'source_name' => $case['source_name'],
                'created_date' => substr((string) $case['created_date'], 0, 10),
                'due_date' => substr((string) $case['due_date'], 0, 10),
                'created_date_id' => format_date_id((string) $case['created_date']),
                'due_date_id' => format_date_id((string) $case['due_date']),
                'officer_id' => (int) $case['officer_id'],
                'officer_name' => $case['officer_name'],
                'last_note' => $case['last_note'],
            ],
        ]);
    }

    public function searchCases(): void
    {
        $user = Session::user();
        $q = trim((string) Request::input('q', ''));
        $officerId = null;
        if (($user['role'] ?? '') === 'petugas') {
            $officerId = (int) ($user['officer_id'] ?? 0);
            if ($officerId < 1) {
                json_response(['items' => [], 'message' => 'Akun petugas belum dikaitkan.'], 403);
            }
        }

        $rows = $this->cases->searchPicker($q, $officerId, 20);
        $items = [];
        foreach ($rows as $row) {
            $number = (string) $row['case_number'];
            $name = (string) $row['taxpayer_name'];
            $status = (string) $row['status_name'];
            $items[] = [
                'id' => $number,
                'case_number' => $number,
                'text' => $number . ' — ' . $name . ' (' . $status . ')',
                'npwp' => (string) $row['npwp'],
                'taxpayer_name' => $name,
                'status_name' => $status,
                'officer_name' => (string) $row['officer_name'],
                'due_date_id' => format_date_id($row['due_date'] ?? null),
                'is_completed' => (int) ($row['status_is_completed'] ?? 0) === 1,
            ];
        }

        json_response(['items' => $items]);
    }

    /** @param array<string,mixed>|null $user */
    private function formOptions(?array $user): array
    {
        return [
            'types' => $this->types->activeOptions(),
            'statuses' => $this->statuses->activeOptions(),
            'sources' => $this->sources->activeOptions(),
            'officers' => $this->officers->activeOptions(),
        ];
    }

    /** @param array<string,mixed> $input */
    private function oldFromInput(array $input): array
    {
        return [
            'case_number' => strtoupper(trim((string) ($input['case_number'] ?? ''))),
            'npwp' => (string) ($input['npwp'] ?? ''),
            'taxpayer_name' => (string) ($input['taxpayer_name'] ?? ''),
            'case_type_id' => (string) ($input['case_type_id'] ?? ''),
            'status_id' => (string) ($input['status_id'] ?? ''),
            'source_id' => (string) ($input['source_id'] ?? ''),
            'created_date' => (string) ($input['created_date'] ?? ''),
            'due_date' => (string) ($input['due_date'] ?? ''),
            'officer_id' => (string) ($input['officer_id'] ?? ''),
            'note' => (string) ($input['note'] ?? ''),
            'confirm_existing' => (string) ($input['confirm_existing'] ?? ''),
        ];
    }
}
