<?php
/**
 * Public Glassmorphism Top Navbar
 * Digunakan HANYA untuk halaman awam (public landing page & public info pages).
 * Dashboard dalaman (admin/jurulatih/pegawai_teknikal) TIDAK menggunakan partial ini.
 */
$activePage = $activePage ?? '';

$publicNav = [
    ['home',      'index.php',              'fa-house',        'Maklumat KVSkills'],
    ['jadual',    'maklumat/jadual.php',    'fa-calendar-days','Jadual & Dokumen'],
    ['lokasi',    'maklumat/lokasi.php',    'fa-location-dot', 'Lokasi Pertandingan'],
    ['peserta',   'maklumat/peserta.php',   'fa-users',        'Senarai Peserta'],
    ['keputusan', 'maklumat/keputusan.php', 'fa-trophy',       'Keputusan'],
    ['logo',      'maklumat/logo.php',      'fa-download',     'Muat Turun Logo'],
];
?>
<nav class="public-navbar" id="publicNavbar" aria-label="Navigasi Utama Awam">
    <div class="public-navbar-inner">

        <!-- =================== KIRI: Logo & Brand =================== -->
        <a href="<?= e(url('index.php')) ?>" class="public-navbar-brand" aria-label="KVSkills Hub - Laman Utama">
            <div class="public-navbar-logo-wrap">
                <img src="<?= e(url('assets/images/Logo KVSkills.png')) ?>" alt="Logo KVSkills Hub" class="public-navbar-logo-img">
            </div>
            <div class="public-navbar-brand-text">
                <span class="public-navbar-name">KVSkills Hub</span>
                <span class="public-navbar-sub">Portal Rasmi Kebangsaan &middot; BPLTV</span>
            </div>
        </a>

        <!-- =================== TENGAH: Nav Pautan =================== -->
        <ul class="public-navbar-links" id="publicNavLinks" role="list">
            <?php foreach ($publicNav as [$key, $href, $icon, $label]): ?>
                <li role="listitem">
                    <a href="<?= e(url($href)) ?>"
                       class="public-navbar-link<?= $activePage === $key ? ' active' : '' ?>"
                       <?= $activePage === $key ? 'aria-current="page"' : '' ?>>
                        <i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i>
                        <span><?= e($label) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- =================== KANAN: CTA + Hamburger =================== -->
        <div class="public-navbar-actions">
            <a href="<?= e(url('jawatan.php')) ?>" class="public-navbar-cta" aria-label="Log Masuk Portal KVSkills Hub">
                <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                <span>Log Masuk Portal</span>
            </a>
            <!-- Hamburger (mobile only) -->
            <button class="public-navbar-hamburger" id="publicNavHamburger"
                    type="button"
                    aria-controls="publicNavLinks"
                    aria-expanded="false"
                    aria-label="Buka menu navigasi">
                <span class="hamburger-bar"></span>
                <span class="hamburger-bar"></span>
                <span class="hamburger-bar"></span>
            </button>
        </div>
    </div>

    <!-- Mobile drawer overlay -->
    <div class="public-nav-overlay" id="publicNavOverlay" aria-hidden="true"></div>
</nav>

<!-- Spacer supaya kandungan tidak tersembunyi di bawah navbar fixed -->
<div class="public-navbar-spacer" aria-hidden="true"></div>

<!-- Wrapper utama full-width untuk halaman awam -->
<div class="public-main-content">
