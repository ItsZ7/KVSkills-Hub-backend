<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';

Auth::requireLogin('coach');
$user   = Auth::user();
$skills = all_skills();

// Find skills that the coach's participants are competing in
$coachSkillIds = db()->prepare("
    SELECT DISTINCT skill_id FROM registrations WHERE coach_user_id = ?
");
$coachSkillIds->execute([Auth::id()]);
$mySkillIds = $coachSkillIds->fetchAll(PDO::FETCH_COLUMN);

// Filter by skill & search
$filterSkill = (int)($_GET['skill_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));

$sql = "
    SELECT d.*, s.name AS skill_name, s.code AS skill_code, u.full_name AS uploader_name
    FROM documents d
    LEFT JOIN skills s ON s.id = d.skill_id
    LEFT JOIN users u ON u.id = d.uploaded_by
    WHERE d.category = 'question'
      AND d.is_active = 1
      AND d.visibility IN ('authenticated', 'public')
";
$params = [];

if ($filterSkill > 0) {
    $sql .= " AND d.skill_id = ?";
    $params[] = $filterSkill;
}
if ($q !== '') {
    $sql .= " AND (d.title LIKE ? OR d.original_filename LIKE ? OR s.name LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY d.created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll();

$totalAvailable = count($questions);

$pageTitle  = 'Soalan Pertandingan';
$activePage = 'coach_soalan';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
?>
<section class="dashboard-header py-4">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-3 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-file-circle-question text-warning"></i>
            <span>Repositori Dokumen Teknikal &middot; KVSkills Hub</span>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-2">Kertas Soalan Pertandingan</h1>
        <p class="lead mx-auto mb-0 text-muted" style="max-width: 720px; font-size: 1.05rem;">
            Akses dan muat turun kertas projek, soalan teori serta amali yang dibekalkan oleh Pegawai Teknikal KVSkills.
        </p>
    </div>
</section>

<section class="container py-4">
    <!-- STATS / INFO CARD -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="kpi-stat-card accent-navy">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-file-circle-question"></i>
                    </div>
                    <span class="kpi-tag">Repositori Rasmi</span>
                </div>
                <div>
                    <div class="kpi-value"><?= (int)$totalAvailable ?></div>
                    <div class="kpi-label">Kertas Soalan &amp; Projek Tersedia</div>
                    <p class="kpi-desc">Dokumen disahkan oleh Pegawai Teknikal mengikut bidang kemahiran</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="kpi-stat-card accent-emerald">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                    <span class="kpi-tag">Kontinjen Anda</span>
                </div>
                <div>
                    <div class="kpi-value"><?= count($mySkillIds) ?> <span style="font-size: 1.25rem; font-weight: 600; color: var(--slate-600);">Bidang</span></div>
                    <div class="kpi-label">Bidang Kemahiran Disertai</div>
                    <p class="kpi-desc">Calon kontinjen anda berdaftar dalam bidang ini</p>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="text" name="q" value="<?= e($q) ?>" class="form-control border-start-0" placeholder="Cari tajuk kertas projek / nama fail...">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="skill_id" class="form-select">
                        <option value="0">Semua Bidang Kemahiran (22)</option>
                        <?php foreach ($skills as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= selected($filterSkill, $s['id']) ?>>
                                <?= e($s['name']) ?> (<?= e($s['code']) ?>)
                                <?= in_array($s['id'], $mySkillIds, false) ? '★ [Bidang Kontinjen]' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="fa-solid fa-filter me-1"></i> Tapis
                    </button>
                    <?php if ($filterSkill > 0 || $q !== ''): ?>
                        <a href="<?= e(url('jurulatih/soalan.php')) ?>" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-rotate-left me-1"></i> Set Semula
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- QUESTIONS LIST TABLE -->
    <div class="card border shadow-sm">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-file-shield text-primary fs-5"></i>
                <div>
                    <h2 class="h5 mb-0 fw-bold text-dark">Senarai Dokumen Rasmi Pegawai Teknikal</h2>
                    <small class="text-muted">Kertas projek, modul tugasan, dan skema amali rasmi pertandingan</small>
                </div>
            </div>
            <span class="badge text-bg-light border text-secondary px-3 py-2 font-monospace">
                <?= count($questions) ?> fail dijumpai
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 42%;">Kertas Soalan / Projek</th>
                            <th style="width: 26%;">Bidang Kemahiran</th>
                            <th class="text-center" style="width: 10%;">Saiz Fail</th>
                            <th style="width: 12%;">Tarikh Dikeluarkan</th>
                            <th class="text-end pe-4" style="width: 10%;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $qDoc): ?>
                            <?php $isMyField = in_array($qDoc['skill_id'], $mySkillIds, false); ?>
                            <tr class="<?= $isMyField ? 'table-light' : '' ?>">
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="doc-icon-wrap icon-pdf">
                                            <i class="fa-solid fa-file-pdf"></i>
                                        </div>
                                        <div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <strong class="text-dark fw-bold"><?= e($qDoc['title']) ?></strong>
                                                <?php if ($isMyField): ?>
                                                    <span class="doc-highlight-badge">
                                                        <i class="fa-solid fa-star"></i> Bidang Anda
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                <i class="fa-solid fa-paperclip me-1"></i>
                                                <span class="font-monospace text-secondary"><?= e($qDoc['original_filename']) ?></span>
                                                <?php if (!empty($qDoc['uploader_name'])): ?>
                                                    &middot; <span class="text-muted"><i class="fa-solid fa-user-shield me-1"></i><?= e($qDoc['uploader_name']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($qDoc['skill_name']): ?>
                                        <div class="fw-bold text-dark small"><?= e($qDoc['skill_name']) ?></div>
                                        <span class="badge text-bg-light border text-secondary font-monospace mt-1"><?= e($qDoc['skill_code']) ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Umum / Semua Bidang</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center font-monospace small text-secondary">
                                    <?= number_format(((int)$qDoc['file_size']) / 1024, 1) ?> KB
                                </td>
                                <td>
                                    <div class="small text-dark fw-semibold font-monospace"><?= date('d/m/Y', strtotime($qDoc['created_at'])) ?></div>
                                    <div class="small text-muted font-monospace"><?= date('h:i A', strtotime($qDoc['created_at'])) ?></div>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="<?= e(url('download.php?id=' . (int)$qDoc['id'])) ?>" class="btn btn-sm btn-primary px-3 shadow-xs d-inline-flex align-items-center gap-1" title="Muat Turun Soalan">
                                        <i class="fa-solid fa-download"></i>
                                        <span>Muat Turun</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$questions): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <div class="py-4">
                                        <i class="fa-solid fa-file-circle-question fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                        <h3 class="h6 fw-bold text-dark mb-1">Tiada Kertas Soalan Ditemui</h3>
                                        <p class="small text-muted mb-0">Tiada kertas soalan dikeluarkan bagi carian semasa atau soalan belum dibuka untuk akses jurulatih.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php require BASE_PATH . '/partials/footer.php'; ?>
