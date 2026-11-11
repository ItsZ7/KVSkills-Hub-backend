<?php
$activePage = $activePage ?? '';
$user = Auth::user();
$role = $user['role'] ?? null;

$links = [];

if ($role === 'admin') {
    // ADMIN NAVIGATION ONLY
    $links = [
        ['admin_dashboard', 'admin/dashboard.php', 'fa-gauge-high', 'Dashboard Admin'],
        ['admin_technical', 'admin/pegawai_teknikal.php', 'fa-user-gear', 'Pegawai Teknikal'],
        ['admin_users', 'admin/pengguna.php', 'fa-users-gear', 'Pengurusan Pengguna'],
        ['admin_keputusan', 'admin/keputusan.php', 'fa-ranking-star', 'Pengurusan Keputusan'],
        ['admin_settings', 'admin/tetapan.php', 'fa-gear', 'Tetapan Pentadbir'],
    ];
} elseif ($role === 'technical') {
    // TECHNICAL OFFICER NAVIGATION ONLY
    $scoringEnabled = is_pt_score_entry_enabled();
    $keputusanLabel = $scoringEnabled ? 'Pengurusan Keputusan' : 'Keputusan (Dikunci)';
    $links = [
        ['technical_dashboard', 'pegawai_teknikal/dashboard.php', 'fa-gauge-high', 'Dashboard Teknikal'],
        ['technical_daftar_pt', 'pegawai_teknikal/daftar_pegawai_teknikal.php', 'fa-user-shield', 'Daftar Pegawai Teknikal'],
        ['technical_soalan', 'pegawai_teknikal/soalan.php', 'fa-file-circle-question', 'Masukkan Soalan'],
        ['technical_pendaftaran', 'pegawai_teknikal/pendaftaran.php', 'fa-clipboard-check', 'Semakan Pendaftaran'],
        ['technical_keputusan', 'pegawai_teknikal/keputusan.php', $scoringEnabled ? 'fa-ranking-star' : 'fa-lock', $keputusanLabel],
        ['technical_dokumen', 'pegawai_teknikal/dokumen.php', 'fa-folder-open', 'Pengurusan Dokumen'],
        ['technical_pertandingan', 'pegawai_teknikal/pertandingan.php', 'fa-calendar-plus', 'Edisi Pertandingan'],
        ['technical_settings', 'pegawai_teknikal/tetapan.php', 'fa-gear', 'Tetapan Diri'],
    ];
} elseif ($role === 'coach') {
    // COACH NAVIGATION ONLY
    $links = [
        ['coach_dashboard', 'jurulatih/dashboard.php', 'fa-gauge-high', 'Dashboard Jurulatih'],
        ['coach_pendaftaran', 'jurulatih/pendaftaran.php', 'fa-clipboard-list', 'Pendaftaran Peserta'],
        ['coach_soalan', 'jurulatih/soalan.php', 'fa-file-circle-question', 'Soalan Pertandingan'],
        ['coach_settings', 'jurulatih/tetapan.php', 'fa-gear', 'Tetapan Diri'],
    ];
} else {
    // PUBLIC / VISITOR NAVIGATION ONLY
    $links = [
        ['home', 'index.php', 'fa-house', 'Maklumat KVSkills'],
        ['jadual', 'maklumat/jadual.php', 'fa-calendar-days', 'Jadual & Dokumen'],
        ['lokasi', 'maklumat/lokasi.php', 'fa-location-dot', 'Lokasi Pertandingan'],
        ['peserta', 'maklumat/peserta.php', 'fa-users', 'Senarai Peserta'],
        ['keputusan', 'maklumat/keputusan.php', 'fa-trophy', 'Keputusan'],
        ['logo', 'maklumat/logo.php', 'fa-download', 'Muat Turun Logo'],
    ];
}
?>
<div class="sidebar-topbar">
    <a href="<?= e(url('index.php')) ?>" class="sidebar-topbar-brand">KVSkills Hub</a>
    <button class="sidebar-toggle" type="button" aria-label="Buka menu"><i class="fa-solid fa-bars"></i></button>
</div>
<div class="sidebar-overlay"></div>
<aside class="sidebar">
    <button class="sidebar-close-btn" type="button" aria-label="Tutup sidebar"><i class="fa-solid fa-xmark"></i></button>
    <a href="<?= e(url('index.php')) ?>" class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <img src="<?= e(url('assets/images/Logo KVSkills.png')) ?>" alt="Logo KVSkills">
        </div>
        <div class="sidebar-brand-info">
            <span class="sidebar-brand-name">KVSkills Hub</span>
            <span class="sidebar-brand-role">
                <?= $role ? 'Portal ' . e($role === 'admin' ? 'Pentadbir' : ($role === 'technical' ? 'Teknikal' : 'Jurulatih')) : 'Portal Rasmi Kebangsaan' ?>
            </span>
        </div>
    </a>
    <ul class="sidebar-nav">
        <?php foreach ($links as [$key, $href, $icon, $label]): ?>
            <li>
                <a href="<?= e(url($href)) ?>" class="sidebar-link <?= $activePage === $key ? 'active' : '' ?>">
                    <i class="fa-solid <?= e($icon) ?>"></i>
                    <span><?= e($label) ?></span>
                </a>
            </li>
        <?php endforeach; ?>

        <?php if ($user): ?>
            <li class="sidebar-divider">
                <form action="<?= e(url('auth/logout.php')) ?>" method="post" class="m-0">
                    <?= Csrf::field() ?>
                    <button class="sidebar-link sidebar-button sidebar-link-danger" type="submit">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Log Keluar</span>
                    </button>
                </form>
            </li>
        <?php else: ?>
            <li class="sidebar-divider">
                <a href="<?= e(url('jawatan.php')) ?>" class="sidebar-link sidebar-link-accent">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span>Log Masuk Portal</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</aside>
<button class="sidebar-reopen-btn" type="button" aria-label="Buka sidebar"><i class="fa-solid fa-bars"></i></button>
<div class="main-content">