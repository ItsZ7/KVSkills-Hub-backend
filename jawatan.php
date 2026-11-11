<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
$pageTitle = 'Pilih Peranan';
require BASE_PATH . '/partials/head.php';
?>
<section class="hero">
    <div class="container text-center" style="max-width: 960px;">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-3 rounded-pill fade-in-item" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-award text-warning"></i>
            <span>Bahagian Pendidikan &amp; Latihan Teknikal Vokasional</span>
        </div>
        <div>
            <img src="<?= e(url('assets/images/Logo KVSkills.png')) ?>" class="logo mb-3 fade-in-item" alt="Logo KVSkills">
        </div>
        <h1 class="title fade-in-item">KVSkills Hub Malaysia</h1>
        <p class="subtitle fade-in-item">Sila pilih peranan rasmi anda untuk meneruskan akses ke dalam sistem pengurusan pertandingan.</p>
        <div class="row justify-content-center mt-5 g-4">
            <div class="col-lg-4 col-md-6 fade-in-item">
                <a href="<?= e(url('jurulatih/login.php')) ?>" class="menu-btn p-4">
                    <i class="fa-solid fa-user-group"></i>
                    <span>Jurulatih</span>
                    <small class="text-muted mt-2" style="font-size: 12.5px; font-weight: 400; line-height: 1.3;">Pendaftaran peserta &amp; semakan maklumat kontinjen</small>
                </a>
            </div>
            <div class="col-lg-4 col-md-6 fade-in-item">
                <a href="<?= e(url('pegawai_teknikal/login.php')) ?>" class="menu-btn p-4">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>Pegawai Teknikal</span>
                    <small class="text-muted mt-2" style="font-size: 12.5px; font-weight: 400; line-height: 1.3;">Pengesahan pendaftaran, soalan &amp; penjurian</small>
                </a>
            </div>
            <div class="col-lg-4 col-md-6 fade-in-item">
                <a href="<?= e(url('admin/login.php')) ?>" class="menu-btn p-4">
                    <i class="fa-solid fa-shield-halved text-warning"></i>
                    <span>Pentadbir (Admin)</span>
                    <small class="text-muted mt-2" style="font-size: 12.5px; font-weight: 400; line-height: 1.3;">Kawalan keselamatan, pengguna &amp; penerbitan keputusan</small>
                </a>
            </div>
        </div>
        <div class="mt-5 fade-in-item">
            <a href="<?= e(url('index.php')) ?>" class="btn btn-outline-secondary px-4 py-2 bg-white shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Portal Awam
            </a>
        </div>
    </div>
</section>
</body>
</html>