<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
Auth::requireLogin(['technical', 'admin']);
$user = Auth::user();
$comp = active_competition();
$compId = (int)($comp['id'] ?? 0);

// Key Stats
$counts = [
    'zones_registered' => (int)db()->prepare("SELECT COUNT(DISTINCT zone_id) FROM registrations WHERE competition_id=? AND status='approved'")->execute([$compId]) ? (int)db()->query("SELECT COUNT(DISTINCT zone_id) FROM registrations WHERE competition_id=$compId AND status='approved'")->fetchColumn() : 0,
    'total_participants' => (int)db()->prepare("SELECT COUNT(*) FROM registrations WHERE competition_id=? AND status='approved'")->execute([$compId]) ? (int)db()->query("SELECT COUNT(*) FROM registrations WHERE competition_id=$compId AND status='approved'")->fetchColumn() : 0,
    'pending_regs' => (int)db()->prepare("SELECT COUNT(*) FROM registrations WHERE competition_id=? AND status='submitted'")->execute([$compId]) ? (int)db()->query("SELECT COUNT(*) FROM registrations WHERE competition_id=$compId AND status='submitted'")->fetchColumn() : 0,
    'rejected_regs' => (int)db()->prepare("SELECT COUNT(*) FROM registrations WHERE competition_id=? AND status='rejected'")->execute([$compId]) ? (int)db()->query("SELECT COUNT(*) FROM registrations WHERE competition_id=$compId AND status='rejected'")->fetchColumn() : 0,
    'scores_entered' => (int)db()->prepare("SELECT COUNT(*) FROM results res JOIN registrations r ON r.id=res.registration_id WHERE r.competition_id=?")->execute([$compId]) ? (int)db()->query("SELECT COUNT(*) FROM results res JOIN registrations r ON r.id=res.registration_id WHERE r.competition_id=$compId")->fetchColumn() : 0,
    'results_published' => (int)db()->prepare("SELECT COUNT(*) FROM results res JOIN registrations r ON r.id=res.registration_id WHERE r.competition_id=? AND res.is_published=1")->execute([$compId]) ? (int)db()->query("SELECT COUNT(*) FROM results res JOIN registrations r ON r.id=res.registration_id WHERE r.competition_id=$compId AND res.is_published=1")->fetchColumn() : 0,
];

// Zone Breakdown
$zoneStats = db()->query("
    SELECT z.id, z.name, z.code,
           COUNT(r.id) AS total_regs,
           SUM(CASE WHEN r.status='approved' THEN 1 ELSE 0 END) AS approved_count,
           SUM(CASE WHEN r.status='submitted' THEN 1 ELSE 0 END) AS pending_count
    FROM zones z
    LEFT JOIN registrations r ON r.zone_id=z.id AND r.competition_id=$compId
    WHERE z.is_active=1
    GROUP BY z.id
    ORDER BY z.sort_order, z.name
")->fetchAll();

// Skills Breakdown
$skillStats = db()->query("
    SELECT s.id, s.name, s.code,
           COUNT(r.id) AS reg_count,
           SUM(CASE WHEN res.id IS NOT NULL THEN 1 ELSE 0 END) AS score_count,
           SUM(CASE WHEN res.is_published=1 THEN 1 ELSE 0 END) AS published_count
    FROM skills s
    LEFT JOIN registrations r ON r.skill_id=s.id AND r.competition_id=$compId AND r.status='approved'
    LEFT JOIN results res ON res.registration_id=r.id
    WHERE s.is_active=1
    GROUP BY s.id
    ORDER BY s.sort_order, s.name
")->fetchAll();

// Skills List with Student Counts for Filtering
$skillsWithCount = db()->query("
    SELECT s.id, s.name, s.code,
           COUNT(r.id) AS student_count,
           SUM(CASE WHEN r.status='approved' THEN 1 ELSE 0 END) AS approved_count
    FROM skills s
    LEFT JOIN registrations r ON r.skill_id=s.id AND r.competition_id=$compId
    WHERE s.is_active=1
    GROUP BY s.id
    ORDER BY s.sort_order, s.name
")->fetchAll();

// Technical Officer Assigned Skill
$ptSkillId = (int)($user['skill_id'] ?? 0);
$selectedSkillId = (int)($_GET['bidang_id'] ?? ($ptSkillId > 0 ? $ptSkillId : 0));

// Query Students Registered by Skill
$sqlStudents = "
    SELECT r.id, r.participant_name, r.institution, r.image_path, r.shirt_size, r.status,
           s.id AS skill_id, s.name AS skill_name, s.code AS skill_code,
           z.name AS zone_name,
           u.full_name AS coach_name, u.phone AS coach_phone,
           res.score, res.award,
           MAX(CASE WHEN h.helper_type='assistant' THEN h.full_name ELSE NULL END) AS assistant_name,
           MAX(CASE WHEN h.helper_type='model' THEN h.full_name ELSE NULL END) AS model_name
    FROM registrations r
    JOIN skills s ON s.id = r.skill_id
    JOIN zones z ON z.id = r.zone_id
    JOIN users u ON u.id = r.coach_user_id
    LEFT JOIN registration_helpers h ON h.registration_id = r.id
    LEFT JOIN results res ON res.registration_id = r.id
    WHERE r.competition_id = ?
";
$paramsStudents = [$compId];
if ($selectedSkillId > 0) {
    $sqlStudents .= " AND r.skill_id = ?";
    $paramsStudents[] = $selectedSkillId;
}
$sqlStudents .= " GROUP BY r.id ORDER BY s.sort_order, (r.status='approved') DESC, z.sort_order, r.participant_name";
$stmtStudents = db()->prepare($sqlStudents);
$stmtStudents->execute($paramsStudents);
$studentsBySkill = $stmtStudents->fetchAll();

// Recent Pending Registrations
$recentPending = db()->query("
    SELECT r.id, r.participant_name, r.institution, r.image_path, s.name AS skill_name, z.name AS zone_name, u.full_name AS coach_name, r.created_at
    FROM registrations r
    JOIN skills s ON s.id=r.skill_id
    JOIN zones z ON z.id=r.zone_id
    JOIN users u ON u.id=r.coach_user_id
    WHERE r.status='submitted' AND r.competition_id=$compId
    ORDER BY r.created_at DESC
    LIMIT 6
")->fetchAll();

$pageTitle  = 'Dashboard Teknikal';
$activePage = 'technical_dashboard';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
?>
<section class="dashboard-header py-4">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-3 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-user-gear text-warning"></i>
            <span>Portal Operasi Teknikal &middot; Pegawai Berautoriti</span>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-2">Dashboard Pegawai Teknikal</h1>
        <p class="lead mx-auto mb-3 text-muted" style="max-width: 720px; font-size: 1.05rem;">
            Selamat bertugas, <?= e($user['full_name']) ?> &middot; Pengurusan operasi teknikal, pendaftaran kontinjen, dan penjurian markah kebangsaan.
        </p>
        <?php if ($comp): ?>
            <div class="event-pill">
                <i class="fa-solid fa-trophy text-warning"></i>
                <span>Edisi: <strong><?= e($comp['name']) ?></strong> (Status: <?= ucfirst(e($comp['status'])) ?>)</span>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="container py-4">
    <!-- STATS OVERVIEW -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-stat-card accent-navy">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-earth-asia"></i>
                    </div>
                    <span class="kpi-tag">10 Zon TVET</span>
                </div>
                <div>
                    <div class="kpi-value"><?= $counts['zones_registered'] ?> <span style="font-size: 1.15rem; font-weight: 600; color: var(--slate-500);">/ 10</span></div>
                    <div class="kpi-label">Kontinjen Zon Berdaftar</div>
                    <p class="kpi-desc">Zon kontinjen dengan peserta diluluskan</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-stat-card accent-emerald">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <span class="kpi-tag">Peserta Rasmi</span>
                </div>
                <div>
                    <div class="kpi-value"><?= (int)$counts['total_participants'] ?></div>
                    <div class="kpi-label">Peserta Diluluskan</div>
                    <p class="kpi-desc">Calon layak bertanding peringkat akhir</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-stat-card accent-amber">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-clipboard-question"></i>
                    </div>
                    <span class="kpi-tag">Tindakan</span>
                </div>
                <div>
                    <div class="kpi-value"><?= (int)$counts['pending_regs'] ?></div>
                    <div class="kpi-label">Menunggu Semakan</div>
                    <p class="kpi-desc">Pendaftaran baharu perlu pengesahan</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-stat-card accent-blue">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-square-poll-vertical"></i>
                    </div>
                    <span class="kpi-tag">Penjurian</span>
                </div>
                <div>
                    <div class="kpi-value"><?= (int)$counts['scores_entered'] ?> <span style="font-size: 1.05rem; font-weight: 600; color: var(--slate-500);">(<?= (int)$counts['results_published'] ?> Umum)</span></div>
                    <div class="kpi-label">Markah Dinilai / Terbit</div>
                    <p class="kpi-desc">Rekod keputusan dan kedudukan rasmi</p>
                </div>
            </div>
        </div>
    </div>

    <!-- QUICK ACTION MODULES -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
            <a class="op-quick-card" href="<?= e(url('pegawai_teknikal/daftar_pegawai_teknikal.php')) ?>">
                <div class="op-quick-icon icon-navy">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <h4 class="op-quick-title">Daftar Pegawai Teknikal</h4>
                <span class="op-quick-desc">Lantikan &amp; Akaun</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a class="op-quick-card" href="<?= e(url('pegawai_teknikal/soalan.php')) ?>">
                <div class="op-quick-icon icon-danger">
                    <i class="fa-solid fa-file-circle-question"></i>
                </div>
                <h4 class="op-quick-title">Masukkan Soalan</h4>
                <span class="op-quick-desc">Kertas Projek / Modul</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a class="op-quick-card" href="<?= e(url('pegawai_teknikal/pendaftaran.php')) ?>">
                <div class="op-quick-icon icon-emerald">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <h4 class="op-quick-title">Semakan Pendaftaran</h4>
                <span class="op-quick-desc">Kelulusan Calon</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <?php $ptScoringOk = is_pt_score_entry_enabled(); ?>
            <a class="op-quick-card position-relative" href="<?= e(url('pegawai_teknikal/keputusan.php')) ?>">
                <?php if (!$ptScoringOk): ?>
                    <span class="position-absolute top-0 end-0 m-2 badge bg-danger" style="font-size: 9px; font-weight: 700;">
                        <i class="fa-solid fa-lock me-1"></i>Dikunci
                    </span>
                <?php endif; ?>
                <div class="op-quick-icon <?= $ptScoringOk ? 'icon-amber' : 'icon-slate' ?>">
                    <i class="fa-solid <?= $ptScoringOk ? 'fa-ranking-star' : 'fa-lock' ?>"></i>
                </div>
                <h4 class="op-quick-title">Kemasukan Markah</h4>
                <span class="op-quick-desc <?= $ptScoringOk ? 'text-success fw-semibold' : 'text-danger' ?>">
                    <?= $ptScoringOk ? 'Sesi Dibuka' : 'Ditutup Pentadbir' ?>
                </span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a class="op-quick-card" href="<?= e(url('pegawai_teknikal/dokumen.php')) ?>">
                <div class="op-quick-icon icon-info">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <h4 class="op-quick-title">Pengurusan Dokumen</h4>
                <span class="op-quick-desc">Muat Naik Fail</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a class="op-quick-card" href="<?= e(url('pegawai_teknikal/tetapan.php')) ?>">
                <div class="op-quick-icon icon-slate">
                    <i class="fa-solid fa-gear"></i>
                </div>
                <h4 class="op-quick-title">Tetapan Profil</h4>
                <span class="op-quick-desc">Akaun &amp; Keselamatan</span>
            </a>
        </div>
    </div>

    <!-- 3. SENARAI PELAJAR MENGIKUT BIDANG KEMAHIRAN -->
    <div class="card border shadow-sm mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-graduation-cap text-primary fs-5"></i>
                        <h2 class="h5 mb-0 fw-bold text-dark">Senarai Pelajar Mengikut Bidang Kemahiran</h2>
                    </div>
                    <p class="text-muted small mb-0 mt-1">
                        Paparan wajib calon pelajar mengikut bidang kemahiran untuk semakan dan pemantauan Pegawai Teknikal.
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <form method="get" class="d-flex gap-2">
                        <select name="bidang_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 260px;">
                            <option value="0">Semua Bidang Kemahiran (22)</option>
                            <?php foreach ($skillsWithCount as $sk): ?>
                                <option value="<?= (int)$sk['id'] ?>" <?= selected($selectedSkillId, $sk['id']) ?>>
                                    <?= e($sk['name']) ?> (<?= (int)$sk['student_count'] ?> Pelajar)
                                    <?= ((int)($user['skill_id'] ?? 0) === (int)$sk['id']) ? '★ [Bidang Anda]' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($selectedSkillId > 0): ?>
                            <a href="<?= e(url('pegawai_teknikal/dashboard.php')) ?>" class="btn btn-sm btn-outline-secondary" title="Lihat Semua">
                                <i class="fa-solid fa-rotate-left"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <!-- PILL TABS OF POPULAR SKILLS -->
            <div class="p-3 bg-light border-bottom overflow-auto text-nowrap d-flex gap-2 align-items-center">
                <a href="<?= e(url('pegawai_teknikal/dashboard.php?bidang_id=0')) ?>" class="btn btn-sm <?= $selectedSkillId === 0 ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3">
                    Semua Bidang
                </a>
                <?php foreach ($skillsWithCount as $sk): ?>
                    <?php if ((int)$sk['student_count'] > 0 || (int)($user['skill_id'] ?? 0) === (int)$sk['id']): ?>
                        <a href="<?= e(url('pegawai_teknikal/dashboard.php?bidang_id=' . (int)$sk['id'])) ?>" class="btn btn-sm <?= $selectedSkillId === (int)$sk['id'] ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3">
                            <span class="font-monospace"><?= e($sk['code']) ?></span>: <?= e($sk['name']) ?>
                            <span class="badge bg-white text-dark ms-1 font-monospace"><?= (int)$sk['student_count'] ?></span>
                            <?php if ((int)($user['skill_id'] ?? 0) === (int)$sk['id']): ?>
                                <i class="fa-solid fa-star text-warning ms-1" title="Bidang Diselia Anda"></i>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- STUDENTS TABLE -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Pelajar / Calon</th>
                            <th>Bidang Kemahiran</th>
                            <th>Kolej Vokasional &amp; Zon</th>
                            <th class="text-center">Saiz Baju</th>
                            <th>Jurulatih Pengiring</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($studentsBySkill as $st): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= e(participant_photo_url($st['image_path'])) ?>" class="rounded-circle border shadow-xs" style="width: 44px; height: 44px; object-fit: cover;" alt="Foto Calon">
                                        <div>
                                            <strong class="d-block text-dark fw-bold"><?= e($st['participant_name']) ?></strong>
                                            <?php if ($st['assistant_name']): ?>
                                                <small class="text-muted d-block"><i class="fa-solid fa-user-gear me-1"></i>Pembantu: <?= e($st['assistant_name']) ?></small>
                                            <?php endif; ?>
                                            <?php if ($st['model_name']): ?>
                                                <small class="text-muted d-block"><i class="fa-solid fa-person me-1"></i>Model: <?= e($st['model_name']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong class="d-block text-dark small fw-bold"><?= e($st['skill_name']) ?></strong>
                                    <span class="badge text-bg-light border text-secondary font-monospace mt-1"><?= e($st['skill_code']) ?></span>
                                </td>
                                <td>
                                    <span class="d-block text-dark fw-semibold small"><?= e($st['institution']) ?></span>
                                    <span class="badge text-bg-light border text-secondary small font-monospace mt-1"><?= e($st['zone_name']) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($st['shirt_size']): ?>
                                        <span class="badge text-bg-light border text-dark font-monospace px-2 py-1"><?= e($st['shirt_size']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small font-monospace">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="d-block text-dark small fw-semibold"><?= e($st['coach_name']) ?></span>
                                    <?php if ($st['coach_phone']): ?>
                                        <small class="text-muted font-monospace"><i class="fa-solid fa-phone me-1"></i><?= e($st['coach_phone']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($st['status'] === 'approved'): ?>
                                        <span class="badge bg-success px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i>Diluluskan</span>
                                    <?php elseif ($st['status'] === 'submitted'): ?>
                                        <span class="badge bg-warning text-dark px-2 py-1"><i class="fa-solid fa-clock me-1"></i>Menunggu</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger px-2 py-1"><i class="fa-solid fa-ban me-1"></i>Ditolak</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="<?= e(url('pegawai_teknikal/pendaftaran.php?skill_id=' . (int)$st['skill_id'])) ?>" class="btn btn-sm btn-outline-primary shadow-xs" title="Semak Pendaftaran">
                                        <i class="fa-solid fa-magnifying-glass me-1"></i> Semak
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$studentsBySkill): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <div class="py-4">
                                        <i class="fa-solid fa-user-graduate fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                        <h3 class="h6 fw-bold text-dark mb-1">Tiada Pelajar Dijumpai</h3>
                                        <p class="small text-muted mb-0">Tiada pelajar didaftarkan bagi bidang kemahiran yang dipilih.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PENDING REGS TABLE -->
    <div class="card border shadow-sm mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-warning fs-5"></i>
                <h2 class="h5 mb-0 fw-bold text-dark">Pendaftaran Menunggu Semakan Teknikal</h2>
            </div>
            <a href="<?= e(url('pegawai_teknikal/pendaftaran.php?status=submitted')) ?>" class="btn btn-sm btn-outline-primary">
                Lihat Semua Menunggu <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Peserta</th>
                            <th>Bidang Kemahiran</th>
                            <th>Institusi</th>
                            <th>Jurulatih</th>
                            <th>Tarikh Hantar</th>
                            <th class="text-end pe-4">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPending as $rp): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <strong class="d-block text-dark fw-bold"><?= e($rp['participant_name']) ?></strong>
                                    <span class="badge text-bg-light border text-secondary font-monospace"><?= e($rp['zone_name']) ?></span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark"><?= e($rp['skill_name']) ?></span>
                                </td>
                                <td>
                                    <small class="text-muted"><?= e($rp['institution']) ?></small>
                                </td>
                                <td>
                                    <small class="fw-semibold text-dark"><?= e($rp['coach_name']) ?></small>
                                </td>
                                <td>
                                    <div class="small text-dark font-monospace"><?= date('d/m/Y', strtotime($rp['created_at'])) ?></div>
                                    <div class="small text-muted font-monospace"><?= date('h:i A', strtotime($rp['created_at'])) ?></div>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="<?= e(url('pegawai_teknikal/pendaftaran.php?status=submitted')) ?>" class="btn btn-sm btn-primary shadow-xs">
                                        <i class="fa-solid fa-clipboard-check me-1"></i> Semak Sekarang
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentPending): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <div class="py-3">
                                        <i class="fa-solid fa-circle-check fs-2 text-success opacity-50 mb-2 d-block"></i>
                                        <span class="small">Semua pendaftaran telah disemak. Tiada pendaftaran menunggu semakan teknikal.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 2 COLUMN STATS: ZONE & SKILL BREAKDOWN -->
    <div class="row g-4">
        <!-- 10 ZONES SUMMARY -->
        <div class="col-lg-6">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-map-location-dot text-primary fs-5"></i>
                        <h2 class="h5 mb-0 fw-bold text-dark">Statistik Penyertaan 10 Zon Kontinjen</h2>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Zon Kontinjen</th>
                                    <th class="text-center">Diluluskan</th>
                                    <th class="text-center">Menunggu</th>
                                    <th class="text-center pe-4">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($zoneStats as $z): ?>
                                    <tr>
                                        <td class="ps-4 py-2">
                                            <strong class="text-dark"><?= e($z['name']) ?></strong>
                                            <span class="badge text-bg-light border text-secondary font-monospace ms-1"><?= e($z['code']) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success font-monospace"><?= (int)$z['approved_count'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-warning text-dark font-monospace"><?= (int)$z['pending_count'] ?></span>
                                        </td>
                                        <td class="text-center pe-4 fw-bold font-monospace text-dark">
                                            <?= (int)$z['total_regs'] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 22 SKILLS SUMMARY -->
        <div class="col-lg-6">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-screwdriver-wrench text-warning fs-5"></i>
                        <h2 class="h5 mb-0 fw-bold text-dark">Status 22 Bidang Kemahiran</h2>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-4">Bidang Kemahiran</th>
                                    <th class="text-center">Peserta</th>
                                    <th class="text-center">Markah</th>
                                    <th class="text-center pe-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($skillStats as $s): ?>
                                    <tr>
                                        <td class="ps-4 py-2">
                                            <span class="badge text-bg-light border text-secondary font-monospace me-1"><?= e($s['code']) ?></span>
                                            <span class="text-dark small fw-semibold"><?= e($s['name']) ?></span>
                                        </td>
                                        <td class="text-center font-monospace small">
                                            <?= (int)$s['reg_count'] ?>
                                        </td>
                                        <td class="text-center font-monospace small">
                                            <?= (int)$s['score_count'] ?>
                                        </td>
                                        <td class="text-center pe-4">
                                            <?php if ((int)$s['published_count'] > 0): ?>
                                                <span class="badge bg-success">Diterbitkan</span>
                                            <?php elseif ((int)$s['score_count'] > 0): ?>
                                                <span class="badge bg-info text-dark">Dinilai</span>
                                            <?php elseif ((int)$s['reg_count'] > 0): ?>
                                                <span class="badge bg-warning text-dark">Didaftar</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary opacity-75">Tiada Peserta</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require BASE_PATH . '/partials/footer.php'; ?>
