<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Services\CaseImportService;
use RuntimeException;
use Throwable;

final class CaseImportController extends Controller
{
    public function __construct(
        private readonly CaseImportService $importService = new CaseImportService(),
    ) {
    }

    public function form(): void
    {
        $this->render('cases/import', [
            'pageTitle' => 'Import Excel Kasus',
            'result' => Session::getFlash('import_result'),
            'errors' => get_flash('errors', []),
        ]);
    }

    public function store(): void
    {
        $user = Session::user();
        if ($user === null) {
            abort(401, 'Silakan login.');
        }

        $file = $_FILES['excel_file'] ?? null;
        if (!is_array($file)) {
            Session::flash('error', 'Pilih file Excel (.xlsx) atau CSV terlebih dahulu.');
            redirect('/cases/import');
        }

        try {
            $result = $this->importService->importUploadedFile($file, $user);
            Session::flash('import_result', $result);
            if ($result['ok']) {
                Session::flash('success', $result['message']);
            } elseif ($result['summary']['total'] > 0 && $result['summary']['failed'] < $result['summary']['total']) {
                Session::flash('error', $result['message'] . ' Periksa detail baris gagal di bawah.');
            } else {
                Session::flash('error', $result['message']);
            }
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable $e) {
            Session::flash(
                'error',
                (bool) config('app.debug', false) ? $e->getMessage() : 'Gagal memproses file import.'
            );
        }

        redirect('/cases/import');
    }

    public function template(): void
    {
        $headers = CaseImportService::templateHeaders();
        $sample = [
            'P0000000001',
            '1234567890123450',
            'PT CONTOH',
            'Pengembalian Melalui Surat Permohonan',
            'Diproses',
            'Portal',
            '2026-08-10',
            '2026-08-20',
            'Cindy',
        ];

        $filename = 'template_import_kasus.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            abort(500, 'Gagal membuat template.');
        }
        // UTF-8 BOM for Excel
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        fputcsv($out, $sample);
        fclose($out);
        exit;
    }
}
