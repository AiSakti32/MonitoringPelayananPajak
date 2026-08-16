# Kajang Lako

Sistem internal **monitoring permohonan dan deadline pelayanan**.  
Dikembangkan sebagai proyek freelance (PHP + MySQL).

Aplikasi ini **bukan situs publik**. Data kasus, NPWP, dan akun hanya bisa diakses setelah login.

---

## Yang dikerjakan

- Input / update kasus berdasarkan **Nomor Kasus** unik (tanpa data dobel)
- Riwayat perubahan status, petugas, dan jatuh tempo
- Dashboard & monitoring deadline (H-5, H-3, hari ini, terlambat)
- Alert kasus yang perlu tindakan
- Import Excel/CSV (upsert per nomor kasus)
- Role **admin** dan **petugas** (petugas hanya melihat kasus miliknya)
- Audit log & rate limit login

---

## Stack

| Bagian | Teknologi |
|--------|-----------|
| Backend | PHP 8+ (MVC custom) |
| Database | MySQL / MariaDB |
| Frontend | HTML, CSS, Bootstrap 5, Tom Select |
| Server | Apache (`public/` sebagai document root) |

---

## Keamanan data (wajib)

Kode di GitHub **tidak boleh** berisi data produksi atau rahasia.

| Item | Status |
|------|--------|
| File `.env` (password DB) | **Tidak di-commit** (ada di `.gitignore`) |
| Data kasus / NPWP produksi | **Tidak di-commit** |
| Schema + master seed (jenis/status/petugas contoh) | Boleh (bukan data WP nyata) |
| Demo seed | Hanya dummy (`PT TEST …`), **jangan** ke production |
| Halaman aplikasi | Wajib login |
| Search engine | `noindex, nofollow` + header `X-Robots-Tag` |

### Document root

Document root web **harus** folder `public/`.

- Yang boleh diakses browser: `public/index.php`, CSS/JS/gambar
- Yang **tidak** boleh diakses URL: `app/`, `config/`, `database/`, `storage/`, `.env`, `routes/`, `scripts/`

Jika document root salah (mengarah ke folder proyek), file SQL dan config bisa bocor. Folder sensitif sudah dilindungi `.htaccess` (`Require all denied`) sebagai cadangan.

### Checklist sebelum `git push`

- [ ] `.env` tidak ikut ter-stage (`git status` tidak menampilkan `.env`)
- [ ] Tidak ada dump `.sql` produksi / Excel data klien
- [ ] Repo **private** kecuali klien mengizinkan public
- [ ] Password DB production tidak tertulis di README atau commit

---

## Setup lokal

1. Clone repo, document root ke `public/`.
2. Salin environment:

```bash
cp .env.example .env
```

3. Isi `.env` (hanya di komputer Anda):

```
DB_HOST=localhost
DB_NAME=kajang_lako
DB_USER=root
DB_PASS=
```

4. Import database **berurutan**:

```text
database/schema.sql
database/seed.sql
```

Opsional, **hanya local**:

```text
database/seed_demo.sql
```

5. Buat user admin (CLI):

```bash
php scripts/create_admin.php --username=admin --name="Administrator" --password="GantiPasswordAnda"
```

6. Buka URL aplikasi, login dengan akun yang baru dibuat.

---

## Struktur singkat

```text
public/          ← document root (satu-satunya yang di-expose web)
app/             ← controller, view, service (bukan public)
config/          ← konfigurasi
database/        ← schema & seed (bukan public)
routes/web.php
storage/logs/    ← log, tidak di-commit
```

---

## Catatan portofolio

Proyek ini adalah aplikasi **internal pelayanan**. Nama instansi, data wajib pajak, dan kredensial production tidak disertakan. Contoh di seed demo hanyalah data uji.

---

## Lisensi

Kode hasil pekerjaan freelance. Penggunaan ulang atau publikasi penuh mengikuti kesepakatan dengan klien. Default: **tidak untuk produksi pihak ketiga tanpa izin**.
