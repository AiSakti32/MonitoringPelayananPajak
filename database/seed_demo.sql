-- =============================================================================
-- Kajang Lako (26AGS015) — DEVELOPMENT DEMO SEED (LOCAL ONLY)
--
-- Prasyarat: schema.sql + seed.sql (master) sudah di-import.
-- JANGAN import file ini ke production.
--
-- Demo case: nomor UNIK, NPWP 16 digit, nama WP jelas sebagai DATA TEST.
-- Tanggal relatif ke CURDATE() agar H-5 / H-3 / Hari Ini / Terlambat terlihat.
-- Idempotent untuk nomor kasus demo (ON DUPLICATE KEY UPDATE).
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- Helper: skip if master belum siap
-- (manual check; script seed_local.php memvalidasi APP_ENV=local)

INSERT INTO `cases` (
  `case_number`, `npwp`, `taxpayer_name`,
  `case_type_id`, `status_id`, `source_id`,
  `created_date`, `due_date`, `officer_id`,
  `last_note`, `created_by`, `updated_by`, `created_at`, `updated_at`
)
SELECT
  v.case_number, v.npwp, v.taxpayer_name,
  ct.id, cs.id, src.id,
  v.created_date, v.due_date, o.id,
  v.note, NULL, NULL, NOW(), NOW()
FROM (
  SELECT 'P0000000001' AS case_number, '1000000000000001' AS npwp, 'PT TEST ALPHA' AS taxpayer_name,
         'Pengembalian Melalui Pelaporan Surat Pemberitahuan (SPT)' AS type_name,
         'diproses' AS status_slug, 'Portal' AS source_name, 'Cindy' AS officer_name,
         CURDATE() AS created_date, DATE_ADD(CURDATE(), INTERVAL 10 DAY) AS due_date,
         'DEMO: Normal (+10 hari)' AS note
  UNION ALL
  SELECT 'C0000000002', '1000000000000002', 'PT TEST BETA',
         'Pengembalian Melalui Surat Permohonan', 'dibuat', 'Core', 'Imam',
         CURDATE(), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'DEMO: H-5 (+5 hari)'
  UNION ALL
  SELECT 'P0000000003', '1000000000000003', 'CV TEST GAMMA',
         'Pengukuhan PKP', 'diproses', 'Portal', 'Nurul',
         CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'DEMO: H-3 (+3 hari)'
  UNION ALL
  SELECT 'C0000000004', '1000000000000004', 'PT TEST DELTA',
         'Penetapan Wajib Pajak Nonaktif (Portal)', 'diproses', 'Portal', 'Dendi',
         CURDATE(), CURDATE(), 'DEMO: Hari Ini'
  UNION ALL
  SELECT 'P0000000005', '1000000000000005', 'PT TEST EPSILON',
         'Penetapan Wajib Pajak Nonaktif (Core)', 'diproses', 'Core', 'Rena',
         DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'DEMO: Terlambat'
  UNION ALL
  SELECT 'C0000000006', '1000000000000006', 'CV TEST ZETA',
         'Perubahan Alamat Utama dengan Pemindahan KPP Terdaftar (Portal)', 'selesai', 'Portal', 'Aldy',
         DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'DEMO: Selesai'
  UNION ALL
  SELECT 'P0000000007', '1000000000000007', 'PT TEST ETA',
         'Perubahan Alamat Utama dengan Pemindahan KPP Terdaftar (Core)', 'diproses', 'Core', 'Cindy',
         CURDATE(), DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'DEMO: H-5 (+4 hari)'
  UNION ALL
  SELECT 'C0000000008', '1000000000000008', 'PT TEST THETA',
         'LA.19-05 SKB PPh atas Penghasilan dari Pengalihan Hak atas Tanah dan atau Bangunan (Portal)', 'dibuat', 'Portal', 'Imam',
         CURDATE(), DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'DEMO: Normal (non-priority group)'
  UNION ALL
  SELECT 'P0000000009', '1000000000000009', 'CV TEST IOTA',
         'Pengembalian Melalui Pelaporan Surat Pemberitahuan (SPT)', 'diproses', 'Portal', 'Nurul',
         CURDATE(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'DEMO: H-3 (+2 hari)'
  UNION ALL
  SELECT 'C0000000010', '1000000000000010', 'PT TEST KAPPA',
         'Pengembalian Melalui Surat Permohonan', 'diproses', 'Core', 'Dendi',
         CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'DEMO: H-3 (+1 hari)'
  UNION ALL
  SELECT 'P0000000011', '1000000000000011', 'PT TEST LAMBDA',
         'Pengukuhan PKP', 'selesai', 'Portal', 'Rena',
         DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 8 DAY), 'DEMO: Selesai'
  UNION ALL
  SELECT 'C0000000012', '1000000000000012', 'CV TEST MU',
         'Penetapan Wajib Pajak Nonaktif (Portal)', 'diproses', 'Portal', 'Aldy',
         DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'DEMO: Terlambat'
) AS v
INNER JOIN `case_types` ct ON ct.name = v.type_name
INNER JOIN `case_statuses` cs ON cs.slug = v.status_slug
INNER JOIN `case_sources` src ON src.name = v.source_name
INNER JOIN `officers` o ON o.name = v.officer_name
ON DUPLICATE KEY UPDATE
  `npwp` = VALUES(`npwp`),
  `taxpayer_name` = VALUES(`taxpayer_name`),
  `case_type_id` = VALUES(`case_type_id`),
  `status_id` = VALUES(`status_id`),
  `source_id` = VALUES(`source_id`),
  `created_date` = VALUES(`created_date`),
  `due_date` = VALUES(`due_date`),
  `officer_id` = VALUES(`officer_id`),
  `last_note` = VALUES(`last_note`),
  `updated_at` = NOW();

-- History CREATED untuk demo (hindari duplikat jika sudah ada event CREATED)
INSERT INTO `case_histories` (
  `case_id`, `event_type`, `old_status_id`, `new_status_id`,
  `changed_fields`, `note`, `changed_by`, `created_at`
)
SELECT
  c.id,
  'CREATED',
  NULL,
  c.status_id,
  JSON_OBJECT('case_number', JSON_OBJECT('old', NULL, 'new', c.case_number)),
  c.last_note,
  NULL,
  c.created_at
FROM `cases` c
WHERE c.case_number IN (
  'P0000000001','C0000000002','P0000000003','C0000000004',
  'P0000000005','C0000000006','P0000000007','C0000000008',
  'P0000000009','C0000000010','P0000000011','C0000000012'
)
AND NOT EXISTS (
  SELECT 1 FROM `case_histories` h
  WHERE h.case_id = c.id AND h.event_type = 'CREATED'
);
