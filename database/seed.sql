-- =============================================================================
-- Kajang Lako (26AGS015) — CORE / PRODUCTION MASTER SEED
-- Import AFTER schema.sql
--
-- Berisi HANYA master data sesuai Excel customer.
-- TIDAK berisi case dummy.
--
-- Idempotent: aman dijalankan berulang (ON DUPLICATE KEY UPDATE).
-- Admin user: buat via scripts/create_admin.php (jangan taruh password di sini).
--
-- Demo case development: database/seed_demo.sql (hanya APP_ENV=local)
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- -----------------------------------------------------------------------------
-- MASTER STATUS KASUS (Excel: Dibuat, Diproses, Selesai)
-- -----------------------------------------------------------------------------
INSERT INTO `case_statuses` (`id`, `name`, `slug`, `is_completed`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Dibuat', 'dibuat', 0, 1, 1, NOW(), NOW()),
(2, 'Diproses', 'diproses', 0, 2, 1, NOW(), NOW()),
(3, 'Selesai', 'selesai', 1, 3, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `slug` = VALUES(`slug`),
  `is_completed` = VALUES(`is_completed`),
  `sort_order` = VALUES(`sort_order`),
  `is_active` = 1,
  `updated_at` = NOW();

-- -----------------------------------------------------------------------------
-- MASTER SUMBER KASUS (Excel: Portal, Core)
-- -----------------------------------------------------------------------------
INSERT INTO `case_sources` (`id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Portal', 1, NOW(), NOW()),
(2, 'Core', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `is_active` = 1,
  `updated_at` = NOW();

-- -----------------------------------------------------------------------------
-- MASTER PETUGAS (Excel contoh nyata — BUKAN "dst")
-- -----------------------------------------------------------------------------
INSERT INTO `officers` (`id`, `name`, `employee_code`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Cindy', NULL, 1, NOW(), NOW()),
(2, 'Imam', NULL, 1, NOW(), NOW()),
(3, 'Nurul', NULL, 1, NOW(), NOW()),
(4, 'Dendi', NULL, 1, NOW(), NOW()),
(5, 'Rena', NULL, 1, NOW(), NOW()),
(6, 'Aldy', NULL, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `employee_code` = VALUES(`employee_code`),
  `is_active` = 1,
  `updated_at` = NOW();

-- -----------------------------------------------------------------------------
-- MASTER JENIS KASUS (Excel — urutan contoh; boleh ditambah Admin nanti)
-- dashboard_group: Portal/Core digabung untuk 5 kelompok prioritas dashboard
-- -----------------------------------------------------------------------------
INSERT INTO `case_types` (`name`, `dashboard_group`, `is_dashboard_priority`, `is_active`, `created_at`, `updated_at`) VALUES
('Penetapan Wajib Pajak Nonaktif (Portal)', 'Penetapan Wajib Pajak Nonaktif', 1, 1, NOW(), NOW()),
('Penetapan Wajib Pajak Nonaktif (Core)', 'Penetapan Wajib Pajak Nonaktif', 1, 1, NOW(), NOW()),
('Perubahan Alamat Utama dengan Pemindahan KPP Terdaftar (Portal)', 'Perubahan Alamat Utama dengan Pemindahan KPP Terdaftar', 1, 1, NOW(), NOW()),
('Pengukuhan PKP', 'Pengukuhan PKP', 1, 1, NOW(), NOW()),
('Pengembalian Melalui Surat Permohonan', 'Pengembalian Melalui Surat Permohonan', 1, 1, NOW(), NOW()),
('Perubahan Alamat Utama dengan Pemindahan KPP Terdaftar (Core)', 'Perubahan Alamat Utama dengan Pemindahan KPP Terdaftar', 1, 1, NOW(), NOW()),
('LA.19-05 SKB PPh atas Penghasilan dari Pengalihan Hak atas Tanah dan atau Bangunan (Portal)', NULL, 0, 1, NOW(), NOW()),
('Pengembalian Melalui Pelaporan Surat Pemberitahuan (SPT)', 'Pengembalian Melalui Pelaporan Surat Pemberitahuan (SPT)', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `dashboard_group` = VALUES(`dashboard_group`),
  `is_dashboard_priority` = VALUES(`is_dashboard_priority`),
  `is_active` = 1,
  `updated_at` = NOW();
