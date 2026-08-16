<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Paginator;
use PDO;

final class SourceRepository
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::connection();
    }

    public function paginate(string $search = '', string $status = 'all', int $page = 1, int $perPage = 15): Paginator
    {
        $where = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = 'name LIKE :q';
            $params['q'] = '%' . $search . '%';
        }
        if ($status === 'active') {
            $where[] = 'is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'is_active = 0';
        }

        $sqlWhere = implode(' AND ', $where);
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM case_sources WHERE {$sqlWhere}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = Paginator::normalizePage($page);
        $offset = Paginator::offset($page, $perPage);

        $stmt = $this->db->prepare(
            "SELECT * FROM case_sources WHERE {$sqlWhere} ORDER BY name ASC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return new Paginator($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM case_sources WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM case_sources WHERE name = :name';
        $params = ['name' => $name];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO case_sources (name, is_active, created_at, updated_at)
             VALUES (:name, :is_active, :created_at, :updated_at)'
        );
        $now = now_jakarta();
        $stmt->execute([
            'name' => $data['name'],
            'is_active' => (int) ($data['is_active'] ?? 1),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE case_sources SET name = :name, is_active = :is_active, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'is_active' => (int) ($data['is_active'] ?? 1),
            'updated_at' => now_jakarta(),
        ]);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = $this->db->prepare('UPDATE case_sources SET is_active = :active, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'updated_at' => now_jakarta(), 'id' => $id]);
    }

    public function usageCount(int $id): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM cases WHERE source_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, array> */
    public function activeOptions(): array
    {
        $stmt = $this->db->query('SELECT id, name FROM case_sources WHERE is_active = 1 ORDER BY name ASC');
        return $stmt->fetchAll();
    }
}
