<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Centralized deadline engine (Asia/Jakarta, date-only).
 * ALL dashboard / monitoring / detail / alert pages MUST use this class.
 */
final class DeadlineClassifier
{
    public const KEY_SELESAI = 'selesai';
    public const KEY_OVERDUE = 'overdue';
    public const KEY_TODAY = 'today';
    public const KEY_H3 = 'h3';
    public const KEY_H5 = 'h5';
    public const KEY_NORMAL = 'normal';
    public const KEY_ACTIVE = 'active';
    public const KEY_ALL = 'all';

    /** Alert-relevant keys in priority order. */
    public const ALERT_KEYS = [
        self::KEY_OVERDUE,
        self::KEY_TODAY,
        self::KEY_H3,
        self::KEY_H5,
    ];

    /**
     * Normalize deadline query aliases to canonical keys.
     */
    public static function normalizeKey(?string $raw): string
    {
        $key = strtolower(trim((string) $raw));
        return match ($key) {
            '', 'all' => self::KEY_ALL,
            'alert', 'perlu_tindakan', 'attention' => 'alert',
            'terlambat', 'overdue', 'late' => self::KEY_OVERDUE,
            'hari_ini', 'hari-ini', 'today' => self::KEY_TODAY,
            'h-3', 'h3', 'kritis' => self::KEY_H3,
            'h-5', 'h5', 'waspada' => self::KEY_H5,
            'normal' => self::KEY_NORMAL,
            'selesai', 'completed', 'done' => self::KEY_SELESAI,
            'active', 'aktif' => self::KEY_ACTIVE,
            default => $key,
        };
    }

    /**
     * @return array{key:string,label:string,tone:string,priority:int}
     */
    public static function classify(?string $dueDate, bool $isCompleted, ?string $today = null): array
    {
        if ($isCompleted) {
            return [
                'key' => self::KEY_SELESAI,
                'label' => 'Selesai',
                'tone' => 'done',
                'priority' => 99,
            ];
        }

        if ($dueDate === null || $dueDate === '') {
            return ['key' => 'unknown', 'label' => '—', 'tone' => 'normal', 'priority' => 50];
        }

        $today ??= today_jakarta();
        $days = self::daysRemaining($dueDate, false, $today);
        if ($days === null) {
            return ['key' => 'unknown', 'label' => '—', 'tone' => 'normal', 'priority' => 50];
        }

        if ($days < 0) {
            return [
                'key' => self::KEY_OVERDUE,
                'label' => 'Terlambat ' . abs($days) . ' hari',
                'tone' => 'overdue',
                'priority' => 0,
            ];
        }
        if ($days === 0) {
            return [
                'key' => self::KEY_TODAY,
                'label' => 'Jatuh tempo hari ini',
                'tone' => 'critical',
                'priority' => 1,
            ];
        }
        if ($days >= 1 && $days <= 3) {
            return [
                'key' => self::KEY_H3,
                'label' => 'Sisa ' . $days . ' hari',
                'tone' => 'critical',
                'priority' => 2,
            ];
        }
        if ($days >= 4 && $days <= 5) {
            return [
                'key' => self::KEY_H5,
                'label' => 'Sisa ' . $days . ' hari',
                'tone' => 'warn',
                'priority' => 3,
            ];
        }

        return [
            'key' => self::KEY_NORMAL,
            'label' => 'Sisa ' . $days . ' hari',
            'tone' => 'normal',
            'priority' => 4,
        ];
    }

    /**
     * Remaining days until due (negative = overdue). Null if completed/unknown.
     */
    public static function daysRemaining(?string $dueDate, bool $isCompleted, ?string $today = null): ?int
    {
        if ($isCompleted || $dueDate === null || $dueDate === '') {
            return null;
        }

        $today ??= today_jakarta();
        $due = substr($dueDate, 0, 10);
        $dueTs = strtotime($due . ' 00:00:00');
        $todayTs = strtotime($today . ' 00:00:00');
        return (int) (($dueTs - $todayTs) / 86400);
    }

    public static function priorityLabel(string $key): string
    {
        return match (self::normalizeKey($key)) {
            self::KEY_OVERDUE => 'Terlambat',
            self::KEY_TODAY => 'Hari Ini',
            self::KEY_H3 => 'Kritis H-3',
            self::KEY_H5 => 'Waspada H-5',
            self::KEY_NORMAL => 'Normal',
            self::KEY_SELESAI => 'Selesai',
            self::KEY_ACTIVE => 'Aktif',
            default => 'Semua',
        };
    }

    /** @return list<array{key:string,label:string,tone:string}> */
    public static function quickFilters(): array
    {
        return [
            ['key' => self::KEY_ALL, 'label' => 'Semua', 'tone' => 'neutral'],
            ['key' => self::KEY_OVERDUE, 'label' => 'Terlambat', 'tone' => 'overdue'],
            ['key' => self::KEY_TODAY, 'label' => 'Hari Ini', 'tone' => 'critical'],
            ['key' => self::KEY_H3, 'label' => 'H-3', 'tone' => 'critical'],
            ['key' => self::KEY_H5, 'label' => 'H-5', 'tone' => 'warn'],
            ['key' => self::KEY_NORMAL, 'label' => 'Normal', 'tone' => 'normal'],
            ['key' => self::KEY_SELESAI, 'label' => 'Selesai', 'tone' => 'done'],
        ];
    }

    /**
     * Shared SQL CASE fragments using bound placeholder names for :dl_today.
     * Caller must bind dl_today (and duplicates if needed) to today_jakarta().
     *
     * @return array{overdue:string,today:string,h3:string,h5:string,normal:string,selesai:string,active:string}
     */
    public static function sqlPredicates(string $dueCol = 'c.due_date', string $completedCol = 'cs.is_completed'): array
    {
        return [
            'selesai' => "{$completedCol} = 1",
            'active' => "{$completedCol} = 0",
            'overdue' => "{$completedCol} = 0 AND {$dueCol} < :dl_today",
            'today' => "{$completedCol} = 0 AND {$dueCol} = :dl_today",
            'h3' => "{$completedCol} = 0 AND {$dueCol} BETWEEN DATE_ADD(:dl_today, INTERVAL 1 DAY) AND DATE_ADD(:dl_today_h3, INTERVAL 3 DAY)",
            'h5' => "{$completedCol} = 0 AND {$dueCol} BETWEEN DATE_ADD(:dl_today_h5a, INTERVAL 4 DAY) AND DATE_ADD(:dl_today_h5b, INTERVAL 5 DAY)",
            'normal' => "{$completedCol} = 0 AND {$dueCol} > DATE_ADD(:dl_today, INTERVAL 5 DAY)",
        ];
    }

    /**
     * Urgency ORDER BY fragment (completed last, then overdue→today→h3→h5→normal).
     * Escapes today as literal date (safe: from today_jakarta()).
     */
    public static function sqlOrderByUrgency(string $dueCol = 'c.due_date', string $completedCol = 'cs.is_completed'): string
    {
        $today = today_jakarta();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
            $today = date('Y-m-d');
        }

        return "
            {$completedCol} ASC,
            CASE
                WHEN {$completedCol} = 1 THEN 99
                WHEN {$dueCol} < '{$today}' THEN 0
                WHEN {$dueCol} = '{$today}' THEN 1
                WHEN {$dueCol} BETWEEN DATE_ADD('{$today}', INTERVAL 1 DAY) AND DATE_ADD('{$today}', INTERVAL 3 DAY) THEN 2
                WHEN {$dueCol} BETWEEN DATE_ADD('{$today}', INTERVAL 4 DAY) AND DATE_ADD('{$today}', INTERVAL 5 DAY) THEN 3
                ELSE 4
            END ASC,
            {$dueCol} ASC
        ";
    }
}
