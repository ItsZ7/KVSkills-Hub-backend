# KVSkills Hub Malaysia

Backend PHP 8.2+ dan MySQL 8 untuk pengurusan KVSkills peringkat kebangsaan. Projek ini telah dinaik taraf daripada laman statik kepada sistem berasaskan pangkalan data dengan autentikasi, kawalan peranan, pendaftaran peserta, validasi pembantu/model, semakan pegawai teknikal, keputusan, dokumen dan API baca-sahaja.

## Data rasmi yang dimasukkan

- 10 zon seluruh Malaysia.
- 22 bidang pertandingan.
- 9 lokasi pertandingan dan pemetaan semua 22 bidang berdasarkan surat makluman.
- Tarikh pertandingan kebangsaan: 6 hingga 11 September 2025, Zon Melaka/Negeri Sembilan.
- 44 rekod penataran: 22 peringkat zon dan 22 peringkat kebangsaan, termasuk tarikh, masa dan pautan Google Meet.
- Ambang anugerah Heavy Skills dan Light Skills daripada surat makluman.
- 25 dokumen sumber rasmi dalam storan terlindung.
- Peraturan pembantu peserta:
  - Beauty Therapy: tepat 1 model dan 1 pembantu.
  - Cooking: tepat 1 pembantu.
  - Landscape Gardening: tepat 1 pembantu.
  - Bidang lain: tiada pembantu atau model.

Fail jadual pertandingan khusus tidak terdapat dalam bahan sumber untuk **Automobile Technology, Bookkeeping, dan Patisserie & Confectionery**. Sistem tetap menyediakan ketiga-tiga bidang dan lokasi, tetapi memaparkan status "Fail belum dibekalkan" supaya tiada maklumat direka.

## Keperluan

- PHP 8.2 atau lebih baharu dengan `pdo_mysql`, `mbstring`, `fileinfo`, `json`.
- MySQL 8.0+ atau MariaDB yang serasi.
- Apache 2.4 atau Nginx + PHP-FPM.
- HTTPS untuk penggunaan produksi.

## Pemasangan

1. Salin konfigurasi:

```bash
cp .env.example .env
```

2. Cipta pangkalan data dan pengguna MySQL dengan prinsip least privilege. Kemudian kemas kini `.env`.

3. Tetapkan `APP_URL`, `APP_KEY`, kata laluan admin awal sekurang-kurangnya 12 aksara, dan kod pendaftaran pegawai teknikal.

4. Jalankan pemasangan:

```bash
php database/install.php
```

5. Pastikan proses PHP boleh menulis ke `storage/private/uploads` dan `storage/logs`, tetapi pelayan web menolak akses terus ke `storage/private`.

6. Log masuk sebagai admin menggunakan nilai `INITIAL_ADMIN_EMAIL` dan `INITIAL_ADMIN_PASSWORD`, kemudian tukar kata laluan melalui aliran pengurusan identiti organisasi sebelum produksi.

## Menjalankan dengan Docker untuk pembangunan

```bash
cp .env.docker.example .env
docker compose up -d --build
docker compose exec app php database/install.php
```

Portal tersedia di `http://localhost:8080`. Konfigurasi Docker contoh bukan konfigurasi produksi nasional.

## Modul utama

- Portal awam: jadual, penataran, lokasi, peserta diluluskan, keputusan diterbitkan dan dokumen rasmi.
- Jurulatih: pendaftaran akaun, dashboard dan pendaftaran peserta mengikut zon/bidang.
- Pegawai teknikal/admin: kelulusan pengguna, pengesahan penyertaan, keputusan dan pengurusan dokumen.
- API v1: `catalog.php`, `briefings.php`, `participants.php`, `results.php`, `documents.php`, `registrations.php`, dan `health.php`.
- Audit trail untuk log masuk dan perubahan utama.

## Keselamatan yang telah dilaksanakan

- Kata laluan menggunakan `password_hash()` dan `password_verify()`.
- PDO prepared statements dan `utf8mb4`.
- CSRF token bagi semua tindakan berasaskan sesi.
- Session regeneration selepas log masuk dan cookie `HttpOnly`, `SameSite=Lax`, `Secure` apabila HTTPS.
- Had cubaan log masuk berasaskan sesi/IP.
- Role-based access control untuk jurulatih, pegawai teknikal dan admin.
- Validasi MIME dan saiz untuk muat naik dokumen, nama fail rawak, serta storan terlindung.
- Output HTML di-escape secara lalai.
- Data peribadi sensitif seperti MyKad tidak dikumpulkan atau dipaparkan.

## Ujian

```bash
php tests/run.php
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Semakan sebelum penggunaan seluruh Malaysia

Kod ini menyediakan asas aplikasi yang kukuh, tetapi pelancaran nasional masih memerlukan semakan keselamatan bebas, ujian beban, backup dan disaster recovery, pemantauan, WAF/rate limiting peringkat infrastruktur, log berpusat, pengurusan rahsia, dasar retensi data, penilaian PDPA, UAT rasmi, dan pelan sokongan operasi. Jangan gunakan akaun MySQL `root` atau rahsia contoh dalam produksi.

### Naik taraf keselamatan akaun

Untuk pangkalan data sedia ada, jalankan `php database/install.php --auth-limits-only` sebelum menggunakan versi ini. Arahan ini hanya menyediakan jadual had percubaan. Sesi yang dibuat sebelum naik taraf memerlukan log masuk semula.

Pemulihan jurulatih menggunakan pautan sekali guna selama 30 minit. Tetapkan `APP_URL` kepada alamat HTTPS rasmi dan konfigurasikan penghantaran e-mel PHP pada hos. Sahkan penerimaan melalui peti masuk ujian selepas pemasangan. Tukar kata laluan akaun contoh lama yang pernah digunakan. Pemasang baharu hanya menyediakan admin awal.
