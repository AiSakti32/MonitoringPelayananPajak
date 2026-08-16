<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Paginator;
use PDO;

final class UserRepository
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::connection();
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, o.name AS officer_name
             FROM users u
             LEFT JOIN officers o ON o.id = u.officer_id
             WHERE u.username = :username
             LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, o.name AS officer_name
             FROM users u
             LEFT JOIN officers o ON o.id = u.officer_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findByEmail(string $email, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM users WHERE email = :email';
        $params = ['email' => $email];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = :username';
        $params = ['username' => $username];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function paginate(string $search = '', string $status = 'all', string $role = 'all', int $page = 1, int $perPage = 15): Paginator
    {
        $where = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(u.username LIKE :q OR u.full_name LIKE :q2 OR u.email LIKE :q3 OR o.name LIKE :q4)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }
        if ($status === 'active') {
            $where[] = 'u.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'u.is_active = 0';
        }
        if ($role === 'admin' || $role === 'petugas') {
            $where[] = 'u.role = :role';
            $params['role'] = $role;
        }

        $sqlWhere = implode(' AND ', $where);
        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM users u LEFT JOIN officers o ON o.id = u.officer_id WHERE {$sqlWhere}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = Paginator::normalizePage($page);
        $offset = Paginator::offset($page, $perPage);

        $stmt = $this->db->prepare(
            "SELECT u.*, o.name AS officer_name
             FROM users u
             LEFT JOIN officers o ON o.id = u.officer_id
             WHERE {$sqlWhere}
             ORDER BY u.full_name ASC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return new Paginator($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id'
        );
        $now = now_jakarta();
        $stmt->execute([
            'last_login_at' => $now,
            'updated_at' => $now,
            'id' => $id,
        ]);
    }

    public function countAdmins(bool $activeOnly = false): int
    {
        $sql = "SELECT COUNT(*) FROM users WHERE role = 'admin'";
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $stmt = $this->db->query($sql);
        return (int) $stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, password_hash, full_name, role, officer_id, is_active, created_at, updated_at)
             VALUES (:username, :email, :password_hash, :full_name, :role, :officer_id, :is_active, :created_at, :updated_at)'
        );
        $now = now_jakarta();
        $stmt->execute([
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'password_hash' => $data['password_hash'],
            'full_name' => $data['full_name'],
            'role' => $data['role'],
            'officer_id' => $data['officer_id'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [
            'username = :username',
            'email = :email',
            'full_name = :full_name',
            'role = :role',
            'officer_id = :officer_id',
            'is_active = :is_active',
            'updated_at = :updated_at',
        ];
        $params = [
            'id' => $id,
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'full_name' => $data['full_name'],
            'role' => $data['role'],
            'officer_id' => $data['officer_id'] ?? null,
            'is_active' => (int) ($data['is_active'] ?? 1),
            'updated_at' => now_jakarta(),
        ];

        if (!empty($data['password_hash'])) {
            $fields[] = 'password_hash = :password_hash';
            $params['password_hash'] = $data['password_hash'];
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Self-service profile update (nama, email, optional password).
     * Does not touch role, username, officer_id, or is_active.
     */
    public function updateProfile(int $id, array $data): void
    {
        $fields = [
            'full_name = :full_name',
            'email = :email',
            'updated_at = :updated_at',
        ];
        $params = [
            'id' => $id,
            'full_name' => $data['full_name'],
            'email' => $data['email'] ?? null,
            'updated_at' => now_jakarta(),
        ];

        if (!empty($data['password_hash'])) {
            $fields[] = 'password_hash = :password_hash';
            $params['password_hash'] = $data['password_hash'];
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = $this->db->prepare('UPDATE users SET is_active = :active, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'updated_at' => now_jakarta(), 'id' => $id]);
    }
}
