<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
Auth::requireLogin('coach');
$user = Auth::user();

$stmt = db()->prepare("
    SELECT COUNT(*) AS total,
           SUM(status='approved') AS approved,
           SUM(status='submitted') AS submitted,
           SUM(status='rejected') AS rejected
    FROM registrations
    WHERE coach_user_id=?
");
$stmt->execute([Auth::id()]);
$counts = $stmt->fetch();

$stmt = db()->prepare("
    SELECT r.*, s.name AS skill_name, z.name AS zone_name
    FROM registrations r
    JOIN skills s ON s.id=r.skill_id
    JOIN zones z ON z.id=r.zone_id
    WHERE r.coach_user_id=?
    ORDER BY r.created_at DESC
    LIMIT 8
");
$stmt->execute([Auth::id()]);
$recent = $stmt->fetchAll();

$pageTitle = 'Dashboard Jurulatih';
$activePage = 'coach_dashboard';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
?>
<!-- Header Section -->
<section class="dashboard-header py-4">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-3 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-user-group text-warning"></i>
            <span>Portal Pengurusan Kontinjen &middot; Jurulatih Rasmi</span>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-2">Dashboard Jurulatih</h1>
        <p class="lead mx-auto mb-0 text-muted" style="max-width: 680px; font-size: 1.05rem;">
            Selamat datang, <?= e($user['full_name']) ?> &middot; <?= e($user['institution'] ?? 'Kolej Vokasional') ?> (<?= e($user['zone_name'] ?? 'Zon belum ditetapkan') ?>)
        </p>
    </div>
</section>

<section class="container py-4">
    <!-- STATS CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-stat-card accent-navy">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </div>
                    <span class="kpi-tag">Jumlah</span>
                </div>
                <div>
                    <div class="kpi-value"><?= (int)($counts['total'] ?? 0) ?></div>
                    <div class="kpi-label">Jumlah Peserta</div>
                    <p class="kpi-desc">Calon berdaftar bawah bimbingan</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-stat-card accent-amber">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <span class="kpi-tag">Semakan</span>
                </div>
                <div>
                    <div class="kpi-value"><?= (int)($counts['submitted'] ?? 0) ?></div>
                    <div class="kpi-label">Menunggu Kelulusan</div>
                    <p class="kpi-desc">Dalam semakan Pegawai Teknikal</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-stat-card accent-emerald">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <span class="kpi-tag">Sah Bertanding</span>
                </div>
                <div>
                    <div class="kpi-value"><?= (int)($counts['approved'] ?? 0) ?></div>
                    <div class="kpi-label">Telah Diluluskan</div>
                    <p class="kpi-desc">Disahkan layak ke pertandingan</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-stat-card accent-danger">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <span class="kpi-tag">Tindakan</span>
                </div>
                <div>
                    <div class="kpi-value"><?= (int)($counts['rejected'] ?? 0) ?></div>
                    <div class="kpi-label">Perlu Tindakan</div>
                    <p class="kpi-desc">Pendaftaran ditolak / perlu dikemas kini</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- PROFILE CARD -->
        <div class="col-lg-4">
            <div class="card border shadow-sm h-100">
                <div class="card-body p-4 text-center">
                    <div class="position-relative d-inline-block mb-3">
                        <img src="<?= e(user_avatar_url($user['image_path'] ?? null)) ?>" class="rounded-circle shadow-sm" style="width: 96px; height: 96px; object-fit: cover; border: 3px solid var(--border-subtle);" alt="Avatar">
                    </div>
                    <h2 class="h5 mb-1 fw-bold text-dark"><?= e($user['full_name']) ?></h2>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 mb-3" style="font-size: 11.5px; font-weight: 700;">
                        <i class="fa-solid fa-id-badge me-1"></i> Jurulatih Kontinjen
                    </span>

                    <ul class="profile-meta-list text-start mt-3 pt-3 border-top">
                        <li class="profile-meta-item">
                            <span class="profile-meta-label">Institusi / Kolej</span>
                            <span class="profile-meta-val text-truncate" style="max-width: 180px;"><?= e($user['institution'] ?? '-') ?></span>
                        </li>
                        <li class="profile-meta-item">
                            <span class="profile-meta-label">Zon Kontinjen</span>
                            <span class="profile-meta-val"><span class="badge bg-secondary-subtle text-secondary border"><?= e($user['zone_name'] ?? '-') ?></span></span>
                        </li>
                        <li class="profile-meta-item">
                            <span class="profile-meta-label">E-mel Rasmi</span>
                            <span class="profile-meta-val text-truncate" style="max-width: 180px;"><?= e($user['email']) ?></span>
                        </li>
                        <li class="profile-meta-item">
                            <span class="profile-meta-label">No. Telefon</span>
                            <span class="profile-meta-val"><?= e($user['phone'] ?? '-') ?></span>
                        </li>
                    </ul>

                    <div class="d-grid gap-2 mt-4 pt-2 border-top">
                        <a href="<?= e(url('jurulatih/pendaftaran.php')) ?>" class="btn btn-primary fw-semibold py-2">
                            <i class="fa-solid fa-user-plus me-1"></i> Daftar &amp; Urus Peserta
                        </a>
                        <a href="<?= e(url('jurulatih/soalan.php')) ?>" class="btn btn-outline-secondary fw-semibold py-2">
                            <i class="fa-solid fa-file-circle-question me-1"></i> Kertas Soalan Pertandingan
                        </a>
                        <a href="<?= e(url('jurulatih/tetapan.php')) ?>" class="btn btn-outline-secondary fw-semibold py-2">
                            <i class="fa-solid fa-gear me-1"></i> Tetapan Profil &amp; Kata Laluan
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- RECENT REGISTRATIONS -->
        <div class="col-lg-8">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-users text-primary fs-5"></i>
                        <h2 class="h5 mb-0 fw-bold text-dark">Pendaftaran Terkini Kontinjen</h2>
                    </div>
                    <a href="<?= e(url('jurulatih/pendaftaran.php')) ?>" class="btn btn-sm btn-outline-primary fw-semibold px-3">
                        Lihat Semua <i class="fa-solid fa-arrow-right ms-1 fs-xs"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Peserta</th>
                                    <th>Bidang Kemahiran</th>
                                    <th class="text-center">Status Semakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent as $r): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <img src="<?= e(participant_photo_url($r['image_path'])) ?>" class="rounded-circle border shadow-xs" style="width: 40px; height: 40px; object-fit: cover;" alt="Peserta">
                                                <div>
                                                    <strong class="text-dark d-block"><?= e($r['participant_name']) ?></strong>
                                                    <small class="text-muted"><?= e($r['institution']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong class="text-primary d-block small"><?= e($r['skill_name']) ?></strong>
                                            <small class="text-muted"><?= e($r['zone_name']) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($r['status'] === 'approved'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1">
                                                    <i class="fa-solid fa-circle-check me-1"></i> Diluluskan
                                                </span>
                                            <?php elseif ($r['status'] === 'submitted'): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1">
                                                    <i class="fa-solid fa-clock me-1"></i> Menunggu
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1">
                                                    <i class="fa-solid fa-circle-xmark me-1"></i> Ditolak
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($r['rejection_reason']): ?>
                                                <div class="small text-danger mt-1 text-start" style="font-size: 11.5px;">
                                                    <i class="fa-solid fa-circle-exclamation me-1"></i><?= e($r['rejection_reason']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$recent): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-5">
                                            <i class="fa-solid fa-folder-open text-secondary mb-2 d-block" style="font-size: 32px; opacity: 0.35;"></i>
                                            Belum ada pendaftaran peserta di bawah bimbingan anda.
                                            <div class="mt-2">
                                                <a href="<?= e(url('jurulatih/pendaftaran.php')) ?>" class="btn btn-sm btn-primary">
                                                    <i class="fa-solid fa-user-plus me-1"></i> Daftar Peserta Pertama
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require BASE_PATH . '/partials/footer.php'; ?>