# Dasar Keselamatan Ringkas

Laporkan kerentanan kepada pasukan teknikal organisasi melalui saluran dalaman, bukan melalui isu awam. Jangan sertakan data peserta sebenar dalam laporan ujian.

Untuk produksi:

- wajibkan HTTPS/HSTS pada reverse proxy;
- simpan `.env` di luar repositori dan gunakan secret manager;
- hadkan egress dan akses pangkalan data kepada hos aplikasi;
- jalankan PHP-FPM sebagai pengguna tanpa keistimewaan;
- pastikan `storage/private` tidak boleh dicapai secara terus;
- aktifkan MFA/SSO untuk pegawai teknikal dan admin melalui penyedia identiti organisasi;
- gunakan centralized rate limiting, alerting dan audit-log retention;
- jalankan SAST, dependency scan, pentest dan load test sebelum go-live.
