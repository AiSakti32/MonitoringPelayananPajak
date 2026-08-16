<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CaseHistoryRepository
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::connection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO case_histories (
                case_id, event_type, old_status_id, new_status_id, changed_fields, note, changed_by, created_at
             ) VALUES (
                :case_id, :event_type, :old_status_id, :new_status_id, :changed_fields, :note, :changed_by, :created_at
             )'
        );
        $stmt->execute([
            'case_id' => $data['case_id'],
            'event_type' => $data['event_type'],
            'old_status_id' => $data['old_status_id'] ?? null,
            'new_status_id' => $data['new_status_id'] ?? null,
            'changed_fields' => isset($data['changed_fields'])
                ? json_encode($data['changed_fields'], JSON_UNESCAPED_UNICODE)
                : null,
            'note' => $data['note'] ?? null,
            'changed_by' => $data['changed_by'] ?? null,
            'created_at' => now_jakarta(),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @return array<int, array> */
    public function listByCaseId(int $caseId, string $order = 'asc'): array
    {
        $dir = strtolower($order) === 'desc' ? 'DESC' : 'ASC';
        $stmt = $this->db->prepare(
            "SELECT h.*,
                    u.full_name AS changed_by_name,
                    os.name AS old_status_name,
                    ns.name AS new_status_name
             FROM case_histories h
             LEFT JOIN users u ON u.id = h.changed_by
             LEFT JOIN case_statuses os ON os.id = h.old_status_id
             LEFT JOIN case_statuses ns ON ns.id = h.new_status_id
             WHERE h.case_id = :case_id
             ORDER BY h.created_at {$dir}, h.id {$dir}"
        );
        $stmt->execute(['case_id' => $caseId]);
        return $stmt->fetchAll();
    }
}
