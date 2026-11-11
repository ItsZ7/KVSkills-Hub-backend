<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';

Auth::requireLogin(['technical', 'admin']);

$isScoringAllowed = is_pt_score_entry_enabled() || Auth::isAdmin();

if (is_post()) {
    Csrf::verify();

    require_once BASE_PATH . '/app/ResultEntry.php';
    try {
        ResultEntry::save($_POST, false);
        flash('success', 'Keputusan peserta berjaya disimpan.');
    } catch (DomainException $e) {
        flash('error', $e->getMessage());
    } catch (PDOException $e) {
        error_log($e->getMessage());
        flash('error', 'Gagal menyimpan markah. Sila cuba lagi.');
    }
    redirect('pegawai_teknikal/keputusan.php');

}

$skillId = (int)($_GET['skill_id'] ?? 0);
$skills  = all_skills();

$sql = "
    SELECT r.id AS registration_id, r.participant_name, r.shirt_size, r.institution,
           s.name AS skill_name, z.name AS zone_name,
           res.score, res.skill_category, res.award, res.rank_position, res.is_published, res.notes
    FROM registrations r
    JOIN skills s ON s.id = r.skill_id
    JOIN zones z ON z.id = r.zone_id
    LEFT JOIN results res ON res.registration_id = r.id
    WHERE r.status = 'approved'
";
$params = [];
if ($skillId) {
    $sql .= " AND r.skill_id = ?";
    $params[] = $skillId;
}
$sql .= " ORDER BY s.sort_order, z.sort_order";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle  = 'Pengurusan Keputusan';
$activePage = 'technical_keputusan';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
?>
<section class="dashboard-header py-4">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-3 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-ranking-star text-warning"></i>
            <span>Modul Penjurian &middot; Pegawai Berautoriti</span>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-2">Pengurusan &amp; Kemasukan Keputusan</h1>
        <p class="lead mx-auto mb-0 text-muted" style="max-width: 720px; font-size: 1.05rem;">
            Kemasukan markah teknikal peserta. Anugerah pingat dijana secara automatik mengikut piawaian rasmi kategori Heavy atau Light Skills.
        </p>
    </div>
</section>

<section class="container py-4">
    <!-- LOCK WARNING ALERT IF SCORING ENTRY IS DISABLED BY ADMIN -->
    <?php if (!$isScoringAllowed): ?>
        <div class="alert alert-warning border shadow-sm d-flex align-items-center gap-3 mb-4 p-4 rounded-3" style="background: #fffbeb; border-color: #fde68a !important;">
            <div class="fs-1 text-warning flex-shrink-0">
                <i class="fa-solid fa-lock"></i>
            </div>
            <div>
                <h4 class="h6 fw-bold mb-1 text-dark">Kemasukan Markah Telah Dikunci oleh Pentadbir</h4>
                <p class="mb-0 small text-secondary">
                    Pentadbir sistem telah mengunci akses kemasukan dan pengemaskinian markah buat masa ini. Borang penyuntingan dinyahaktifkan dan anda hanya dibenarkan melihat rekod keputusan yang sedia ada.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- FILTER FORM -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-10">
                    <label class="form-label small fw-bold text-dark mb-1">Tapis mengikut Bidang Kemahiran</label>
                    <select name="skill_id" class="form-select">
                        <option value="0">Semua Bidang Kemahiran (22)</option>
                        <?php foreach ($skills as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= selected($skillId, $s['id']) ?>>
                                <?= e($s['code']) ?>: <?= e($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="fa-solid fa-filter me-1"></i> Tapis
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- PARTICIPANTS SCORING CARDS -->
    <div class="row g-4">
        <?php foreach ($rows as $r): ?>
            <div class="col-xl-6">
                <form method="post" class="card border shadow-sm h-100 <?= !$isScoringAllowed ? 'scoring-card-locked' : '' ?>">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <?= Csrf::field() ?>
                            <input type="hidden" name="registration_id" value="<?= (int)$r['registration_id'] ?>">

                            <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
                                <div>
                                    <h2 class="h5 fw-bold text-dark mb-1"><?= e($r['participant_name']) ?></h2>
                                    <div class="small text-muted">
                                        <span class="fw-semibold text-primary"><?= e($r['skill_name']) ?></span> &middot;
                                        <span class="badge text-bg-light border text-secondary font-monospace"><?= e($r['zone_name']) ?></span> &middot;
                                        <span><?= e($r['institution']) ?></span>
                                        <?php if ($r['shirt_size']): ?>
                                            &middot; <span class="badge text-bg-light border font-monospace"><?= e($r['shirt_size']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($r['award'] && $r['award'] !== 'Tiada'): ?>
                                    <span class="badge <?= e(CompetitionService::medalBadge($r['award'])) ?> align-self-start fs-6 px-3 py-2 shadow-xs">
                                        <?= e($r['award']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="p-3 mb-3 rounded-3" style="background: var(--slate-50); border: 1px solid var(--border-subtle);">
                                <div class="row g-3">
                                    <div class="col-sm-4">
                                        <label class="form-label small fw-bold text-dark mb-1">Markah (0-100)</label>
                                        <input type="number" min="0" max="100" step="0.01" name="score" class="form-control scoring-score-input" value="<?= e($r['score'] ?? '') ?>" <?= !$isScoringAllowed ? 'disabled' : 'required' ?> placeholder="0.00">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label small fw-bold text-dark mb-1">Kategori Bidang</label>
                                        <select name="skill_category" class="form-select" <?= !$isScoringAllowed ? 'disabled' : 'required' ?>>
                                            <option value="">Pilih</option>
                                            <option value="heavy" <?= selected($r['skill_category'] ?? '', 'heavy') ?>>Heavy (Emas &gt;= 90)</option>
                                            <option value="light" <?= selected($r['skill_category'] ?? '', 'light') ?>>Light (Emas &gt;= 85)</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label small fw-bold text-dark mb-1">Kedudukan</label>
                                        <input type="number" min="1" name="rank_position" class="form-control font-monospace text-center" value="<?= e($r['rank_position'] ?? '') ?>" <?= !$isScoringAllowed ? 'disabled' : '' ?> placeholder="Cth: 1">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-dark mb-1">Catatan Penjurian</label>
                                        <input name="notes" class="form-control" value="<?= e($r['notes'] ?? '') ?>" <?= !$isScoringAllowed ? 'disabled' : '' ?> placeholder="Catatan teknikal jika ada">
                                    </div>
                                </div>
                            </div>

                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" id="pub<?= (int)$r['registration_id'] ?>" name="is_published" value="1" <?= checked((bool)($r['is_published'] ?? false)) ?> <?= !$isScoringAllowed ? 'disabled' : '' ?>>
                                <label class="form-check-label small text-secondary" for="pub<?= (int)$r['registration_id'] ?>">
                                    <strong>Terbitkan keputusan kepada umum</strong> (Paparan di laman semakan keputusan)
                                </label>
                            </div>
                        </div>

                        <div class="pt-3 border-top mt-3">
                            <?php if ($isScoringAllowed): ?>
                                <button type="submit" class="btn btn-primary px-4 py-2 shadow-xs d-inline-flex align-items-center gap-2">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    <span>Simpan Keputusan</span>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary px-4 py-2 d-inline-flex align-items-center gap-2" disabled>
                                    <i class="fa-solid fa-lock"></i>
                                    <span>Kemasukan Markah Dikunci</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>

        <?php if (!$rows): ?>
            <div class="col-12">
                <div class="card border shadow-sm p-5 text-center text-muted">
                    <i class="fa-solid fa-trophy fs-1 d-block mb-3 text-secondary opacity-50"></i>
                    <h3 class="h6 fw-bold text-dark mb-1">Tiada Peserta Diluluskan</h3>
                    <p class="small text-muted mb-0">Tiada peserta yang berstatus 'Diluluskan' ditemui bagi bidang kemahiran yang dipilih.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require BASE_PATH . '/partials/footer.php'; ?>
