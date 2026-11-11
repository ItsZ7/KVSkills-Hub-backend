# Laporan Pelaksanaan Backend

## Ringkasan

Projek asal merupakan antaramuka PHP statik tanpa skema pangkalan data, autentikasi berfungsi, penyimpanan peserta, semakan, keputusan atau pengurusan dokumen. Backend baharu dibina tanpa framework luaran supaya mudah dipasang pada persekitaran PHP/MySQL organisasi.

## Data daripada fail sumber

- Jadual Excel/PDF penataran dibaca dan dinormalkan menjadi 44 rekod.
- Tarikh bersiri Excel ditukar kepada tarikh ISO sebenar.
- Ejaan bidang diseragamkan, contohnya `BOOKKEEPING` dan `LANDSCAPE GARDENING`, sementara pemetaan fail sumber yang menggunakan variasi nama dikekalkan.
- Surat makluman digunakan untuk tarikh pertandingan, zon hos, 9 lokasi, pemetaan 22 bidang, syarat asas dan ambang pemenang.
- Semua dokumen sumber disalin ke storan terlindung dan diberikan checksum SHA-256.

## Backend yang ditambah

- MySQL schema, installer idempotent dan seed data.
- Akaun jurulatih, pegawai teknikal dan admin dengan status kelulusan.
- CSRF, password hashing, prepared statements, role checks, session hardening, output escaping dan audit log.
- Pendaftaran peserta per zon/bidang serta validasi pembantu/model di server.
- Aliran semakan dan kelulusan pegawai teknikal.
- Pengiraan anugerah Heavy/Light Skills dan penerbitan keputusan.
- Pengurusan edisi pertandingan dan pembukaan pendaftaran.
- Pengurusan dokumen dengan MIME validation, had saiz, nama rawak dan checksum.
- Portal awam dinamik dan API v1.
- Dockerfile dan Docker Compose untuk pembangunan.

## Pengesahan yang dibuat

- Semua fail PHP lulus `php -l`.
- Ujian data lulus untuk 10 zon, 22 bidang, 9 lokasi, 44 penataran, 19 jadual bidang yang dibekalkan, peraturan pembantu, ambang anugerah dan checksum dokumen.
- Pautan fail dalaman literal disemak dan tiada sasaran yang hilang.

## Had pengesahan persekitaran

Persekitaran pembinaan ini tidak menyediakan pelayan MySQL atau sambungan PHP `pdo_mysql`, jadi pemasangan pangkalan data dan ujian pelayar hujung-ke-hujung tidak dapat dijalankan di sini. Dockerfile memasang sambungan yang diperlukan dan `docker-compose.yml` menyediakan MySQL untuk ujian integrasi selepas projek dimuat turun.
