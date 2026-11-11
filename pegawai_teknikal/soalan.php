<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once BASE_PATH . '/config/upload.php';

Auth::requireLogin(['technical', 'admin']);
$currentUser = Auth::user();
$skills = all_skills();

// Handle POST actions
if (is_post()) {
    Csrf::verify();
    $action = (string)input('action');

    // 1. UPLOAD SOALAN PERTANDINGAN
    if ($action === 'upload_soalan') {
        $title      = trim((string)input('title'));
        $skillId    = (int)input('skill_id');
        $visibility = (string)input('visibility');
        $stored     = null;
        $errors     = [];

        if ($title === '') {
            $errors[] = 'Sila masukkan tajuk kertas soalan.';
        }
        if ($skillId <= 0) {
            $errors[] = 'Sila pilih bidang kemahiran bagi soalan ini.';
        }
        if (!in_array($visibility, ['private', 'authenticated', 'public'], true)) {
            $visibility = 'private';
        }

        if (empty($_FILES['soalan_file']['name'])) {
            $errors[] = 'Sila pilih fail dokumen soalan untuk dimuat naik.';
        }

        if (!$errors) {
            try {
                $stored = store_document_upload($_FILES['soalan_file'], [
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/zip',
                    'application/x-zip-compressed'
                ]);

                $stmt = db()->prepare("
                    INSERT INTO documents(skill_id, uploaded_by, title, category, file_path, original_filename, mime_type, file_size, checksum_sha256, visibility, is_active)
                    VALUES(?, ?, ?, 'question', ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $skillId,
                    Auth::id(),
                    $title,
                    $stored['relative_path'],
                    $stored['original_filename'],
                    $stored['mime_type'],
                    $stored['file_size'],
                    $stored['checksum_sha256'],
                    $visibility
                ]);
                $docId = (int)db()->lastInsertId();
                audit(Auth::id(), 'upload_question', 'document', $docId, ['skill_id' => $skillId, 'title' => $title]);
                flash('success', "Kertas soalan '<strong>" . e($title) . "</strong>' berjaya dimuat naik.");
            } catch (Throwable $e) {
                if ($stored && isset($stored['relative_path'])) {
                    $orphan = BASE_PATH . '/' . ltrim((string)$stored['relative_path'], '/');
                    if (is_file($orphan)) @unlink($orphan);
                }
                flash('error', 'Gagal memuat naik soalan. Semak format dan saiz fail.');
            }
        } else {
            flash('error', implode(' ', $errors));
        }
        redirect('pegawai_teknikal/soalan.php');
    }

    // 2. NYAHAKTIF / PADAM SOALAN
    if ($action === 'delete_soalan') {
        $docId = (int)input('id');
        if ($docId > 0) {
            db()->prepare("UPDATE documents SET is_active=0 WHERE id=? AND category='question'")->execute([$docId]);
            audit(Auth::id(), 'disable_question', 'document', $docId);
            flash('success', 'Kertas soalan telah dinyahaktifkan.');
        }
        redirect('pegawai_teknikal/soalan.php');
    }
}

// Filter by skill
$filterSkill = (int)($_GET['skill_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));

$sql = "
    SELECT d.*, s.name AS skill_name, s.code AS skill_code, u.full_name AS uploader_name
    FROM documents d
    LEFT JOIN skills s ON s.id = d.skill_id
    LEFT JOIN users u ON u.id = d.uploaded_by
    WHERE d.category = 'question'
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

// Statistics
$totalQuestions = (int)db()->query("SELECT COUNT(*) FROM documents WHERE category='question' AND is_active=1")->fetchColumn();
$skillsCovered  = (int)db()->query("SELECT COUNT(DISTINCT skill_id) FROM documents WHERE category='question' AND is_active=1 AND skill_id IS NOT NULL")->fetchColumn();
$privateCount   = (int)db()->query("SELECT COUNT(*) FROM documents WHERE category='question' AND visibility='private' AND is_active=1")->fetchColumn();

$pageTitle  = 'Masukkan Soalan';
$activePage = 'technical_soalan';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
?>
<section class="dashboard-header py-4">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-3 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-file-shield text-warning"></i>
            <span>Pengurusan Dokumen Teknikal &middot; Pegawai Berautoriti</span>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-2">Pengurusan &amp; Muat Naik Soalan Pertandingan</h1>
        <p class="lead mx-auto mb-0 text-muted" style="max-width: 720px; font-size: 1.05rem;">
            Muat naik dan selia kertas projek, modul tugasan, serta soalan teori dan amali bagi 22 bidang kemahiran KVSkills.
        </p>
    </div>
</section>

<section class="container py-4">
    <!-- STATS ROW -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="kpi-stat-card accent-navy">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-file-circle-question"></i>
                    </div>
                    <span class="kpi-tag">Aktif</span>
                </div>
                <div>
                    <div class="kpi-value"><?= (int)$totalQuestions ?></div>
                    <div class="kpi-label">Jumlah Kertas Soalan Aktif</div>
                    <p class="kpi-desc">Dokumen soalan dalam repositori pertandingan</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="kpi-stat-card accent-emerald">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                    </div>
                    <span class="kpi-tag">Liputan</span>
                </div>
                <div>
                    <div class="kpi-value"><?= (int)$skillsCovered ?> <span style="font-size: 1.15rem; font-weight: 600; color: var(--slate-500);">/ 22</span></div>
                    <div class="kpi-label">Bidang Mempunyai Soalan</div>
                    <p class="kpi-desc">Bidang kemahiran yang telah dimuat naik modul</p>
                </div>
            </div>
        </div>
        <div class="col-sm-12 col-lg-4">
            <div class="kpi-stat-card accent-amber">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-user-lock"></i>
                    </div>
                    <span class="kpi-tag">Kerahsiaan</span>
                </div>
                <div>
                    <div class="kpi-value"><?= (int)$privateCount ?></div>
                    <div class="kpi-label">Kertas Soalan Sulit</div>
                    <p class="kpi-desc">Akses terhad untuk Pegawai Teknikal sahaja</p>
                </div>
            </div>
        </div>
    </div>

    <!-- UPLOAD SOALAN TRIGGER CARD -->
    <div class="card border shadow-sm mb-4" style="border-left: 4px solid var(--brand-navy) !important;">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-cloud-arrow-up text-primary fs-5"></i>
                        <h2 class="h5 fw-bold mb-0 text-dark">Masukkan Soalan Pertandingan Baharu</h2>
                    </div>
                    <p class="text-muted mb-0 small mt-1">
                        Muat naik fail soalan rasmi bagi bidang kemahiran (Format dibenarkan: PDF, DOCX, XLSX, ZIP sehingga 10MB).
                    </p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary px-4 py-2 shadow-xs text-nowrap d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#uploadSoalanModal">
                        <i class="fa-solid fa-plus"></i>
                        <span>Masukkan Soalan</span>
                    </button>
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
                        <input type="text" name="q" value="<?= e($q) ?>" class="form-control border-start-0" placeholder="Cari tajuk atau nama fail dokumen...">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="skill_id" class="form-select">
                        <option value="0">Semua Bidang Kemahiran (22)</option>
                        <?php foreach ($skills as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= selected($filterSkill, $s['id']) ?>>
                                <?= e($s['code']) ?>: <?= e($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="fa-solid fa-filter me-1"></i> Tapis
                    </button>
                    <?php if ($filterSkill > 0 || $q !== ''): ?>
                        <a href="<?= e(url('pegawai_teknikal/soalan.php')) ?>" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-rotate-left me-1"></i> Set Semula
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- QUESTIONS TABLE -->
    <div class="card border shadow-sm">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-list-check text-primary fs-5"></i>
                <div>
                    <h2 class="h5 mb-0 fw-bold text-dark">Senarai Kertas Soalan Dimuat Naik</h2>
                    <small class="text-muted">Kertas projek dan modul soalan yang telah didaftarkan dalam sistem</small>
                </div>
            </div>
            <span class="badge text-bg-light border text-secondary px-3 py-2 font-monospace">
                <?= count($questions) ?> soalan dijumpai
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 36%;">Tajuk Soalan &amp; Fail</th>
                            <th style="width: 22%;">Bidang Kemahiran</th>
                            <th class="text-center" style="width: 14%;">Tahap Akses</th>
                            <th class="text-center" style="width: 10%;">Saiz Fail</th>
                            <th style="width: 10%;">Dimuat Naik Oleh</th>
                            <th class="text-end pe-4" style="width: 8%;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $qDoc): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="doc-icon-wrap icon-pdf">
                                            <i class="fa-solid fa-file-pdf"></i>
                                        </div>
                                        <div>
                                            <strong class="d-block text-dark fw-bold"><?= e($qDoc['title']) ?></strong>
                                            <small class="text-muted font-monospace"><i class="fa-solid fa-paperclip me-1"></i><?= e($qDoc['original_filename']) ?></small>
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
                                <td class="text-center">
                                    <?php if ($qDoc['visibility'] === 'private'): ?>
                                        <span class="access-badge access-private" title="Hanya boleh dilihat oleh Pegawai Teknikal dan Pentadbir">
                                            <i class="fa-solid fa-lock"></i> Sulit (Teknikal)
                                        </span>
                                    <?php elseif ($qDoc['visibility'] === 'authenticated'): ?>
                                        <span class="access-badge access-auth" title="Hanya pengguna log masuk">
                                            <i class="fa-solid fa-user-check"></i> Log Masuk
                                        </span>
                                    <?php else: ?>
                                        <span class="access-badge access-public" title="Boleh diakses secara terbuka">
                                            <i class="fa-solid fa-globe"></i> Awam
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center font-monospace small text-secondary">
                                    <?= number_format(((int)$qDoc['file_size']) / 1024, 1) ?> KB
                                </td>
                                <td>
                                    <small class="d-block text-dark fw-semibold"><?= e($qDoc['uploader_name'] ?? 'Sistem') ?></small>
                                    <small class="text-muted font-monospace"><?= date('d/m/Y', strtotime($qDoc['created_at'])) ?></small>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <a href="<?= e(url('download.php?id=' . (int)$qDoc['id'])) ?>" class="btn btn-sm btn-outline-primary shadow-xs" title="Muat Turun Soalan">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                        <?php if ($qDoc['is_active']): ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Adakah anda pasti ingin menyahaktifkan soalan ini?')">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="action" value="delete_soalan">
                                                <input type="hidden" name="id" value="<?= (int)$qDoc['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger shadow-xs" title="Padam Soalan">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$questions): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <div class="py-4">
                                        <i class="fa-solid fa-file-circle-question fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                        <h3 class="h6 fw-bold text-dark mb-1">Tiada Kertas Soalan Ditemui</h3>
                                        <p class="small text-muted mb-0">Tiada kertas soalan dimuat naik mengikut tapisan semasa.</p>
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

<!-- MODAL MASUKKAN SOALAN -->
<div class="modal fade" id="uploadSoalanModal" tabindex="-1" aria-labelledby="uploadSoalanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="upload_soalan">
            <div class="modal-header bg-white border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-file-circle-question text-primary fs-5"></i>
                    <h5 class="modal-title fw-bold text-dark" id="uploadSoalanModalLabel">
                        Borang Masukkan Soalan Pertandingan
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-dark mb-1">Tajuk Kertas Soalan / Modul <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="Cth: Kertas Projek Modul 1: Web Application Development" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-dark mb-1">Bidang Kemahiran <span class="text-danger">*</span></label>
                        <select name="skill_id" class="form-select" required>
                            <option value="">Pilih Bidang Kemahiran (22)</option>
                            <?php foreach ($skills as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= selected((int)($currentUser['skill_id'] ?? 0), $s['id']) ?>>
                                    <?= e($s['name']) ?> (<?= e($s['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-dark mb-1">Tahap Akses / Kerahsiaan <span class="text-danger">*</span></label>
                        <select name="visibility" class="form-select" required>
                            <option value="private" selected>Sulit (Pegawai Teknikal Sahaja)</option>
                            <option value="authenticated">Pengguna Log Masuk (Jurulatih &amp; Peserta)</option>
                            <option value="public">Awam (Boleh Dicapai Semua)</option>
                        </select>
                        <div class="form-text"><i class="fa-solid fa-circle-info me-1"></i>Pilih "Sulit" sekiranya soalan belum dibuka untuk akses kontinjen.</div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-dark mb-1">Fail Dokumen Soalan <span class="text-danger">*</span></label>
                        <input type="file" name="soalan_file" class="form-control" accept=".pdf,.docx,.xlsx,.zip" required>
                        <div class="form-text">Format yang diterima: PDF, DOCX, XLSX, ZIP (Maksimum 10MB).</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top py-3 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4 shadow-xs d-inline-flex align-items-center gap-2">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Muat Naik Soalan</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require BASE_PATH . '/partials/footer.php'; ?>
