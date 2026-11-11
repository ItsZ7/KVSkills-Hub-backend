<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';

Auth::requireLogin('admin');
$adminUser = Auth::user();

// HANDLE POST ACTIONS
if (is_post()) {
    Csrf::verify();
    $action = (string)input('action');

    // 1. SUIS KAWALAN KEMASUKAN MARKAH PEGAWAI TEKNIKAL
    if ($action === 'toggle_pt_scoring') {
        $current = is_pt_score_entry_enabled();
        $newVal  = $current ? '0' : '1';
        set_setting('pt_score_entry_enabled', $newVal, 'Kebenaran kemasukan markah oleh Pegawai Teknikal');
        audit(Auth::id(), 'toggle_pt_score_entry', 'system_settings', 0, ['enabled' => $newVal]);

        if ($newVal === '1') {
            flash('success', 'Kemasukan markah oleh Pegawai Teknikal telah <strong>DIBUKA</strong>.');
        } else {
            flash('warning', 'Kemasukan markah oleh Pegawai Teknikal telah <strong>DITUTUP / DIKUNCI</strong>.');
        }
        redirect('admin/keputusan.php');
    }

    // 2. SIMPAN / KEMAS KINI MARKAH PESERTA
    if ($action === 'save_score') {
        require_once BASE_PATH . '/app/ResultEntry.php';
        try {
            ResultEntry::save($_POST, true);
            flash('success', 'Keputusan peserta berjaya disimpan.');
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        } catch (PDOException $e) {
            error_log($e->getMessage());
            flash('error', 'Gagal menyimpan markah. Sila cuba lagi.');
        }
        redirect('admin/keputusan.php');
    }

    // 3. TERBITKAN SEMUA KEPUTUSAN YANG TELAH DINILAI
    if ($action === 'publish_all') {
        $stmt = db()->prepare("UPDATE results SET is_published = 1, published_at = COALESCE(published_at, NOW()) WHERE is_published = 0");
        $stmt->execute();
        $count = $stmt->rowCount();
        audit(Auth::id(), 'bulk_publish_results', 'results', 0, ['count' => $count]);
        flash('success', "$count keputusan peserta berjaya diterbitkan kepada umum.");
        redirect('admin/keputusan.php');
    }

    // 4. TARIK BALIK SEMUA TERBITAN
    if ($action === 'unpublish_all') {
        $stmt = db()->prepare("UPDATE results SET is_published = 0");
        $stmt->execute();
        $count = $stmt->rowCount();
        audit(Auth::id(), 'bulk_unpublish_results', 'results', 0, ['count' => $count]);
        flash('warning', "Penerbitan semua keputusan telah ditarik balik daripada paparan umum.");
        redirect('admin/keputusan.php');
    }

    // 5. PADAM REKOD MARKAH
    if ($action === 'delete_result') {
        $regId = (int)input('registration_id');
        if ($regId > 0) {
            $stmt = db()->prepare("DELETE FROM results WHERE registration_id = ?");
            $stmt->execute([$regId]);
            audit(Auth::id(), 'admin_delete_result', 'result', $regId);
            flash('success', 'Rekod keputusan peserta telah dipadam.');
        }
        redirect('admin/keputusan.php');
    }
}

// FILTERS
$skillId     = (int)($_GET['skill_id'] ?? 0);
$zoneId      = (int)($_GET['zone_id'] ?? 0);
$scoreStatus = (string)($_GET['score_status'] ?? '');
$pubStatus   = (string)($_GET['pub_status'] ?? '');
$q           = trim((string)($_GET['q'] ?? ''));

$skills = all_skills();
$zones  = all_zones();

$sql = "
    SELECT r.id AS registration_id, r.participant_name, r.shirt_size, r.institution,
           s.id AS skill_id, s.name AS skill_name, s.code AS skill_code,
           z.id AS zone_id, z.name AS zone_name,
           u.full_name AS coach_name,
           res.id AS result_id, res.score, res.skill_category, res.award, res.rank_position, res.is_published, res.notes, res.published_at
    FROM registrations r
    JOIN skills s ON s.id = r.skill_id
    JOIN zones z ON z.id = r.zone_id
    JOIN users u ON u.id = r.coach_user_id
    LEFT JOIN results res ON res.registration_id = r.id
    WHERE r.status = 'approved'
";
$params = [];

if ($skillId > 0) {
    $sql .= " AND r.skill_id = ?";
    $params[] = $skillId;
}
if ($zoneId > 0) {
    $sql .= " AND r.zone_id = ?";
    $params[] = $zoneId;
}
if ($scoreStatus === 'scored') {
    $sql .= " AND res.score IS NOT NULL";
} elseif ($scoreStatus === 'unscored') {
    $sql .= " AND res.score IS NULL";
}
if ($pubStatus === 'published') {
    $sql .= " AND res.is_published = 1";
} elseif ($pubStatus === 'unpublished') {
    $sql .= " AND (res.is_published = 0 OR res.is_published IS NULL)";
}
if ($q !== '') {
    $sql .= " AND (r.participant_name LIKE ? OR r.institution LIKE ? OR s.name LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY s.sort_order, COALESCE(res.rank_position, 999), res.score DESC, z.sort_order";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// STATISTICS
$totalApproved   = (int)db()->query("SELECT COUNT(*) FROM registrations WHERE status='approved'")->fetchColumn();
$totalScored     = (int)db()->query("SELECT COUNT(*) FROM results WHERE score IS NOT NULL")->fetchColumn();
$totalPublished  = (int)db()->query("SELECT COUNT(*) FROM results WHERE is_published=1")->fetchColumn();
$totalUnscored   = max(0, $totalApproved - $totalScored);

$goldCount   = (int)db()->query("SELECT COUNT(*) FROM results WHERE award='Emas'")->fetchColumn();
$silverCount = (int)db()->query("SELECT COUNT(*) FROM results WHERE award='Perak'")->fetchColumn();
$bronzeCount = (int)db()->query("SELECT COUNT(*) FROM results WHERE award='Gangsa'")->fetchColumn();
$medalCount  = (int)db()->query("SELECT COUNT(*) FROM results WHERE award='Medallion'")->fetchColumn();

$isPtScoringEnabled = is_pt_score_entry_enabled();

$pageTitle  = 'Pengurusan Markah & Keputusan';
$activePage = 'admin_keputusan';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
?>
<section class="dashboard-header py-4">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-2 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-trophy text-warning"></i>
            <span>Pusat Kawalan Pentadbir &middot; Keputusan &amp; Markah</span>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-1">Pengurusan Markah &amp; Keputusan KVSkills</h1>
        <p class="text-muted mb-0">Panel kawalan induk pentadbir bagi semakan markah, penganugerahan pingat, dan kawalan penerbitan keputusan kebangsaan.</p>
    </div>
</section>

<section class="container py-4">
    <!-- MASTER TOGGLE SWITCH CARD FOR PT SCORING ENTRY -->
    <div class="card shadow-sm border-0 mb-4 <?= $isPtScoringEnabled ? 'border-start border-4 border-success' : 'border-start border-4 border-danger' ?>">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-circle <?= $isPtScoringEnabled ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' ?>" style="font-size: 28px;">
                        <i class="fa-solid <?= $isPtScoringEnabled ? 'fa-lock-open' : 'fa-lock' ?>"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h2 class="h5 mb-0 fw-bold">Suis Kemasukan Markah Pegawai Teknikal</h2>
                            <span class="badge <?= $isPtScoringEnabled ? 'bg-success' : 'bg-danger' ?> fs-6 px-3 py-1">
                                <?= $isPtScoringEnabled ? 'DIBUKA (AKTIF)' : 'DITUTUP (DIKUNCI)' ?>
                            </span>
                        </div>
                        <p class="text-muted mb-0 small">
                            <?= $isPtScoringEnabled
                                ? 'Pegawai Teknikal dibenarkan mengakses borang dan memasukkan markah peserta bagi zon masing-masing.'
                                : 'Akses kemasukan markah bagi Pegawai Teknikal dikunci sepenuhnya. Pegawai Teknikal hanya boleh melihat rekod sedia ada tanpa kebenaran menyunting.'
                            ?>
                        </p>
                    </div>
                </div>
                <div>
                    <form method="post" class="m-0" onsubmit="return confirm('Adakah anda pasti ingin mengubah status kebenaran kemasukan markah bagi Pegawai Teknikal?')">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="toggle_pt_scoring">
                        <button type="submit" class="btn <?= $isPtScoringEnabled ? 'btn-danger' : 'btn-success' ?> btn-lg px-4 shadow-sm text-nowrap">
                            <i class="fa-solid <?= $isPtScoringEnabled ? 'fa-lock me-2' : 'fa-lock-open me-2' ?>"></i>
                            <?= $isPtScoringEnabled ? 'Tutup Kemasukan Markah' : 'Buka Kemasukan Markah' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- STATS CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <i class="fa-solid fa-users-viewfinder text-primary"></i>
                <strong><?= $totalApproved ?></strong>
                <span>Peserta Diluluskan</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <i class="fa-solid fa-clipboard-check text-success"></i>
                <strong><?= $totalScored ?></strong>
                <span>Telah Dinilai</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <i class="fa-solid fa-hourglass-half text-warning"></i>
                <strong><?= $totalUnscored ?></strong>
                <span>Belum Dinilai</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <i class="fa-solid fa-bullhorn text-info"></i>
                <strong><?= $totalPublished ?></strong>
                <span>Diterbitkan ke Umum</span>
            </div>
        </div>
    </div>

    <!-- MEDALS SUMMARY -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <span class="fw-bold text-dark"><i class="fa-solid fa-award me-1 text-warning"></i> Ringkasan Anugerah:</span>
                    <span class="badge bg-warning text-dark px-3 py-2 fs-6"><i class="fa-solid fa-medal me-1"></i> Emas: <?= $goldCount ?></span>
                    <span class="badge bg-secondary px-3 py-2 fs-6"><i class="fa-solid fa-medal me-1"></i> Perak: <?= $silverCount ?></span>
                    <span class="badge text-bg-danger px-3 py-2 fs-6"><i class="fa-solid fa-medal me-1"></i> Gangsa: <?= $bronzeCount ?></span>
                    <span class="badge bg-info text-dark px-3 py-2 fs-6"><i class="fa-solid fa-certificate me-1"></i> Medallion: <?= $medalCount ?></span>
                </div>
                <div class="d-flex gap-2">
                    <form method="post" class="d-inline" onsubmit="return confirm('Terbitkan SEMUA keputusan yang telah dinilai kepada paparan umum?')">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="publish_all">
                        <button type="submit" class="btn btn-sm btn-outline-success">
                            <i class="fa-solid fa-globe me-1"></i> Terbitkan Semua
                        </button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('Tarik balik semua penerbitan keputusan daripada paparan umum?')">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="unpublish_all">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="fa-solid fa-eye-slash me-1"></i> Tarik Balik Semua
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTERS FORM -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted">Carian</label>
                    <input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Nama peserta atau kolej...">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Bidang Kemahiran</label>
                    <select name="skill_id" class="form-select">
                        <option value="0">Semua Bidang (22)</option>
                        <?php foreach ($skills as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= selected($skillId, $s['id']) ?>><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Zon Kontinjen</label>
                    <select name="zone_id" class="form-select">
                        <option value="0">Semua Zon (10)</option>
                        <?php foreach ($zones as $z): ?>
                            <option value="<?= (int)$z['id'] ?>" <?= selected($zoneId, $z['id']) ?>><?= e($z['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Status Penilaian</label>
                    <select name="score_status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="scored" <?= selected($scoreStatus, 'scored') ?>>Telah Dinilai</option>
                        <option value="unscored" <?= selected($scoreStatus, 'unscored') ?>>Belum Dinilai</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i> Tapis</button>
                    <?php if ($q !== '' || $skillId > 0 || $zoneId > 0 || $scoreStatus !== '' || $pubStatus !== ''): ?>
                        <a href="<?= e(url('admin/keputusan.php')) ?>" class="btn btn-outline-secondary" title="Set Semula"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- RESULTS TABLE -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 70px;">Ked.</th>
                            <th>Peserta &amp; Institusi</th>
                            <th>Bidang Kemahiran</th>
                            <th class="text-center">Kategori</th>
                            <th class="text-center">Markah</th>
                            <th class="text-center">Anugerah</th>
                            <th class="text-center">Terbitan</th>
                            <th class="text-end pe-4">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td class="text-center fw-bold fs-5 text-primary">
                                    <?= $r['rank_position'] ? e($r['rank_position']) : '-' ?>
                                </td>
                                <td>
                                    <strong><?= e($r['participant_name']) ?></strong>
                                    <?php if ($r['shirt_size']): ?>
                                        <span class="badge text-bg-light border text-muted ms-1"><?= e($r['shirt_size']) ?></span>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted"><?= e($r['institution']) ?> · <strong><?= e($r['zone_name']) ?></strong></small>
                                </td>
                                <td>
                                    <strong><?= e($r['skill_name']) ?></strong><br>
                                    <small class="text-muted"><?= e($r['skill_code']) ?></small>
                                </td>
                                <td class="text-center">
                                    <?php if ($r['skill_category']): ?>
                                        <span class="badge <?= $r['skill_category'] === 'heavy' ? 'bg-primary' : 'bg-info text-dark' ?>">
                                            <?= $r['skill_category'] === 'heavy' ? 'Heavy Skill' : 'Light Skill' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($r['score'] !== null): ?>
                                        <strong class="fs-6 text-dark"><?= number_format((float)$r['score'], 2) ?></strong>
                                    <?php else: ?>
                                        <span class="badge text-bg-light border text-muted">Belum Dinilai</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($r['award'] && $r['award'] !== 'Tiada'): ?>
                                        <span class="badge <?= e(CompetitionService::medalBadge($r['award'])) ?> px-3 py-1">
                                            <?= e($r['award']) ?>
                                        </span>
                                    <?php elseif ($r['award'] === 'Tiada'): ?>
                                        <span class="badge text-bg-light border text-muted">Tiada</span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($r['is_published']): ?>
                                        <span class="badge bg-success" title="Diterbitkan kepada umum"><i class="fa-solid fa-eye me-1"></i> Terbit</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary" title="Belum diterbitkan"><i class="fa-solid fa-eye-slash me-1"></i> Draf</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <!-- Score Modal Button -->
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#scoreModal-<?= (int)$r['registration_id'] ?>" title="Kemasukan / Edit Markah">
                                            <i class="fa-solid fa-pen-to-square me-1"></i> <?= $r['score'] !== null ? 'Kemaskini' : 'Nilai' ?>
                                        </button>
                                        <?php if ($r['score'] !== null): ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Adakah anda pasti ingin memadam rekod markah bagi peserta ini?')">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="action" value="delete_result">
                                                <input type="hidden" name="registration_id" value="<?= (int)$r['registration_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Padam Markah">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <!-- SCORING MODAL FOR PARTICIPANT -->
                                    <div class="modal fade text-start" id="scoreModal-<?= (int)$r['registration_id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <form method="post" class="modal-content">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="action" value="save_score">
                                                <input type="hidden" name="registration_id" value="<?= (int)$r['registration_id'] ?>">
                                                <div class="modal-header bg-light">
                                                    <h5 class="modal-title fs-6 fw-bold">
                                                        <i class="fa-solid fa-ranking-star me-2 text-primary"></i>Kemasukan Markah Peserta
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3 p-3 bg-light rounded border">
                                                        <strong class="d-block text-dark fs-6"><?= e($r['participant_name']) ?></strong>
                                                        <small class="text-muted d-block"><?= e($r['institution']) ?> · <?= e($r['zone_name']) ?></small>
                                                        <span class="badge text-bg-primary mt-1"><?= e($r['skill_name']) ?></span>
                                                    </div>

                                                    <div class="row g-3 mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Markah (0.00 - 100.00) <span class="text-danger">*</span></label>
                                                            <input type="number" min="0" max="100" step="0.01" name="score" class="form-control" value="<?= e($r['score'] ?? '') ?>" placeholder="Cth: 88.50" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Kategori Kemahiran <span class="text-danger">*</span></label>
                                                            <select name="skill_category" class="form-select" required>
                                                                <option value="">Pilih Kategori</option>
                                                                <option value="heavy" <?= selected($r['skill_category'] ?? '', 'heavy') ?>>Heavy Skill</option>
                                                                <option value="light" <?= selected($r['skill_category'] ?? '', 'light') ?>>Light Skill</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Kedudukan / Rank (Opsyenal)</label>
                                                            <input type="number" min="1" name="rank_position" class="form-control" value="<?= e($r['rank_position'] ?? '') ?>" placeholder="Cth: 1">
                                                            <div class="form-text">Kedudukan 1, 2, 3, dst.</div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Status Terbitan</label>
                                                            <div class="form-check mt-2">
                                                                <input type="checkbox" class="form-check-input" id="pub-<?= (int)$r['registration_id'] ?>" name="is_published" value="1" <?= checked((bool)($r['is_published'] ?? false)) ?>>
                                                                <label class="form-check-label" for="pub-<?= (int)$r['registration_id'] ?>">
                                                                    Terbitkan kepada umum
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Catatan Pegawai (Opsyenal)</label>
                                                            <input type="text" name="notes" class="form-control" value="<?= e($r['notes'] ?? '') ?>" placeholder="Cth: Pengiraan modul 1 hingga 3 selesai">
                                                        </div>
                                                    </div>

                                                    <div class="alert alert-info py-2 px-3 small mb-0">
                                                        <i class="fa-solid fa-circle-info me-1"></i>
                                                        <strong>Ambang Anugerah Automatik:</strong><br>
                                                        • <strong>Heavy:</strong> Emas (90-100), Perak (80-89), Gangsa (70-79), Medallion (65-69).<br>
                                                        • <strong>Light:</strong> Emas (95-100), Perak (90-94), Gangsa (85-89), Medallion (75-84).
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary px-4">
                                                        <i class="fa-solid fa-check me-1"></i> Simpan Keputusan
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="fa-solid fa-trophy fs-1 d-block mb-3 opacity-25"></i>
                                    Tiada peserta yang diluluskan ditemui mengikut tapisan semasa.
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
