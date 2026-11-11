<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$level = (string)($_GET['level'] ?? 'zone');
if (!in_array($level, ['zone', 'national'], true)) {
    $level = 'zone';
}
$skillId = (int)($_GET['skill_id'] ?? 0);
$skills  = all_skills();

$sql = 'SELECT b.*, s.name AS skill_name, s.sort_order 
        FROM briefings b 
        JOIN skills s ON s.id = b.skill_id 
        WHERE b.is_published = 1 AND b.level = ?';
$params = [$level];

if ($skillId) {
    $sql .= ' AND b.skill_id = ?';
    $params[] = $skillId;
}
$sql .= ' ORDER BY b.briefing_date, b.start_time, s.sort_order';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$briefings = $stmt->fetchAll();

$docRows = db()->query("
    SELECT s.id AS skill_id, s.name, s.sort_order, d.id AS document_id, d.original_filename 
    FROM skills s 
    LEFT JOIN documents d ON d.skill_id = s.id 
        AND d.category = 'national_schedule' 
        AND d.visibility = 'public' 
        AND d.is_active = 1 
    WHERE s.is_active = 1 
    ORDER BY s.sort_order
")->fetchAll();

$generalDocs = public_documents();

$pageTitle  = 'Jadual & Dokumen';
$activePage = 'jadual';

require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/public_navbar.php';
?>

<section class="dashboard-header">
    <div class="container text-center">
        <h1>Jadual &amp; Dokumen KVSkills</h1>
        <p>Jadual penataran rasmi zon dan kebangsaan, pautan Google Meet serta dokumen jadual setiap bidang kemahiran.</p>
    </div>
</section>

<section class="container py-4">
    <!-- FILTER BAR -->
    <form class="card border-0 shadow-sm mb-4" method="get">
        <div class="card-body p-3 p-md-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Peringkat Penataran</label>
                    <select name="level" class="form-select">
                        <option value="zone" <?= selected($level, 'zone') ?>>Peringkat Zon</option>
                        <option value="national" <?= selected($level, 'national') ?>>Peringkat Kebangsaan</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold">Bidang Kemahiran</label>
                    <select name="skill_id" class="form-select">
                        <option value="0">Semua Bidang (22)</option>
                        <?php foreach ($skills as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= selected($skillId, $s['id']) ?>>
                                <?= e($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="fa-solid fa-filter me-1"></i> Tapis Jadual
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- BRIEFINGS TABLE -->
    <div class="card border-0 shadow-sm mb-5">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-calendar-check text-primary fs-5"></i>
                <h2 class="h5 mb-0 fw-bold text-dark">
                    Sesi Penataran Peringkat <?= $level === 'zone' ? 'Zon' : 'Kebangsaan' ?>
                </h2>
            </div>
            <span class="badge bg-light text-dark border">
                <?= count($briefings) ?> Rekod Dijumpai
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Bidang Kemahiran</th>
                            <th style="width: 20%;">Tarikh</th>
                            <th style="width: 15%;">Masa</th>
                            <th style="width: 30%;" class="text-end">Pautan &amp; Kod QR</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($briefings as $b): ?>
                            <tr>
                                <td>
                                    <strong class="text-dark d-block"><?= e($b['skill_name']) ?></strong>
                                    <small class="text-muted">KVSkills 2025</small>
                                </td>
                                <td class="tabular-nums">
                                    <i class="fa-regular fa-calendar text-muted me-1"></i>
                                    <?= e(format_date($b['briefing_date'])) ?>
                                </td>
                                <td class="tabular-nums">
                                    <i class="fa-regular fa-clock text-muted me-1"></i>
                                    <?= e(format_time($b['start_time'])) ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-2 flex-wrap">
                                        <a class="btn btn-sm btn-primary text-nowrap" href="<?= e($b['meeting_url']) ?>" target="_blank" rel="noopener noreferrer">
                                            <i class="fa-solid fa-video me-1"></i> Google Meet
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" data-bs-toggle="modal" data-bs-target="#qrModal-<?= (int)$b['id'] ?>">
                                            <i class="fa-solid fa-qrcode me-1"></i> Kod QR
                                        </button>
                                    </div>

                                    <!-- QR MODAL -->
                                    <div class="modal fade" id="qrModal-<?= (int)$b['id'] ?>" tabindex="-1" aria-labelledby="qrModalLabel-<?= (int)$b['id'] ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-sm">
                                            <div class="modal-content text-center shadow-lg border-0 rounded-4">
                                                <div class="modal-header border-0 pb-0">
                                                    <h5 class="modal-title fs-6 fw-bold w-100 text-dark" id="qrModalLabel-<?= (int)$b['id'] ?>">
                                                        <i class="fa-solid fa-qrcode text-primary me-2"></i>Kod QR Google Meet
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                </div>
                                                <div class="modal-body pt-2 pb-4">
                                                    <div class="badge text-bg-light border text-dark mb-2 px-3 py-2 text-wrap">
                                                        <?= e($b['skill_name']) ?>
                                                    </div>
                                                    <div class="small text-muted mb-3 tabular-nums">
                                                        <i class="fa-regular fa-calendar-days me-1"></i> <?= e(format_date($b['briefing_date'])) ?> &middot; <?= e(format_time($b['start_time'])) ?>
                                                    </div>
                                                    <div class="p-3 bg-white rounded-3 d-inline-block border shadow-sm my-1">
                                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&amp;data=<?= urlencode($b['meeting_url']) ?>" alt="Kod QR Google Meet" width="190" height="190" class="img-fluid rounded" loading="lazy">
                                                    </div>
                                                    <div class="mt-3">
                                                        <small class="text-muted d-block mb-3">Imbas kod QR di atas menggunakan telefon untuk terus menyertai sesi Google Meet.</small>
                                                        <a href="<?= e($b['meeting_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-primary w-100">
                                                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Buka Pautan Meet
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$briefings): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="fa-solid fa-calendar-xmark text-secondary mb-2 d-block" style="font-size: 32px; opacity: 0.4;"></i>
                                    Tiada rekod penataran ditemui bagi tapisan ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SCHEDULE PER SKILL -->
    <div class="mb-5">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-file-lines text-primary fs-5"></i>
            <h2 class="h5 mb-0 fw-bold text-dark">Jadual Pertandingan Kebangsaan Mengikut Bidang</h2>
        </div>
        <div class="row g-3">
            <?php foreach ($docRows as $d): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="document-card">
                        <div>
                            <strong><?= e($d['name']) ?></strong>
                            <small class="tabular-nums"><i class="fa-regular fa-calendar me-1"></i> 6 - 11 September 2025</small>
                        </div>
                        <?php if ($d['document_id']): ?>
                            <a class="btn btn-sm btn-outline-primary text-nowrap" href="<?= e(url('download.php?id=' . (int)$d['document_id'])) ?>">
                                <i class="fa-solid fa-download me-1"></i> Muat Turun
                            </a>
                        <?php else: ?>
                            <span class="badge text-bg-light border text-muted">Belum dibekalkan</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- OTHER OFFICIAL DOCUMENTS -->
    <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-folder-open text-primary fs-5"></i>
            <h2 class="h5 mb-0 fw-bold text-dark">Dokumen &amp; Pek Maklumat Rasmi Lain</h2>
        </div>
        <div class="row g-3">
            <?php foreach ($generalDocs as $d): 
                if (in_array($d['category'], ['national_schedule', 'national_schedule_source'], true)) continue; 
            ?>
                <div class="col-md-6">
                    <div class="document-card">
                        <div>
                            <strong><?= e($d['title']) ?></strong>
                            <small class="tabular-nums"><i class="fa-solid fa-file-pdf text-danger me-1"></i> <?= number_format((int)$d['file_size'] / 1024, 0) ?> KB</small>
                        </div>
                        <a class="btn btn-sm btn-outline-primary text-nowrap" href="<?= e(url('download.php?id=' . (int)$d['id'])) ?>">
                            <i class="fa-solid fa-download me-1"></i> Muat Turun
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require BASE_PATH . '/partials/footer.php'; ?>
