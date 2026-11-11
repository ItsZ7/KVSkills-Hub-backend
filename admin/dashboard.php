<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
Auth::requireLogin('admin');
$user = Auth::user();

if (is_post() && input('action') === 'approve_user') {
    Csrf::verify();
    $uid = (int)input('user_id');
    if ($uid > 0 && $uid !== Auth::id()) {
        $stmt = db()->prepare("UPDATE users SET status='active', approved_at=NOW(), approved_by=? WHERE id=? AND status='pending'");
        $stmt->execute([Auth::id(), $uid]);
        if ($stmt->rowCount() > 0) {
            audit(Auth::id(), 'approve_user', 'user', $uid);
            flash('success', 'Akaun pengguna telah diluluskan.');
        }
    }
    redirect('admin/dashboard.php');
}

// Quick action: toggle PT score entry switch directly from dashboard
if (is_post() && input('action') === 'toggle_pt_scoring') {
    Csrf::verify();
    $current = is_pt_score_entry_enabled();
    $newVal  = $current ? '0' : '1';
    set_setting('pt_score_entry_enabled', $newVal, 'Kebenaran kemasukan markah oleh Pegawai Teknikal');
    audit(Auth::id(), 'toggle_pt_score_entry', 'system_settings', 0, ['enabled' => $newVal]);

    if ($newVal === '1') {
        flash('success', 'Kemasukan markah oleh Pegawai Teknikal telah <strong>DIBUKA</strong>.');
    } else {
        flash('warning', 'Kemasukan markah oleh Pegawai Teknikal telah <strong>DITUTUP / DIKUNCI</strong>.');
    }
    redirect('admin/dashboard.php');
}

$counts = [
    'pending_users'   => (int)db()->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn(),
    'active_admins'   => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'")->fetchColumn(),
    'active_tech'     => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='technical' AND status='active'")->fetchColumn(),
    'active_coaches'  => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='coach' AND status='active'")->fetchColumn(),
    'submitted_regs'  => (int)db()->query("SELECT COUNT(*) FROM registrations WHERE status='submitted'")->fetchColumn(),
    'approved_regs'   => (int)db()->query("SELECT COUNT(*) FROM registrations WHERE status='approved'")->fetchColumn(),
    'published_res'   => (int)db()->query("SELECT COUNT(*) FROM results WHERE is_published=1")->fetchColumn(),
    'total_docs'      => (int)db()->query("SELECT COUNT(*) FROM documents WHERE is_active=1")->fetchColumn(),
];

$comp = active_competition();
$pendingUsers = db()->query("SELECT u.*, z.name AS zone_name FROM users u LEFT JOIN zones z ON z.id=u.zone_id WHERE u.status='pending' ORDER BY u.created_at DESC LIMIT 6")->fetchAll();
$recentRegs = db()->query("SELECT r.id, r.participant_name, r.institution, s.name AS skill_name, z.name AS zone_name, r.created_at FROM registrations r JOIN skills s ON s.id=r.skill_id JOIN zones z ON z.id=r.zone_id WHERE r.status='submitted' ORDER BY r.created_at DESC LIMIT 6")->fetchAll();

$pageTitle = 'Dashboard Pentadbir';
$activePage = 'admin_dashboard';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
?>

<style>
/* ==========================================================================
   ADMIN DASHBOARD — Scoped Design Tokens & Component Overrides
   Prefix: .adm- | Does NOT bleed into other pages
   ========================================================================== */

/* Page Shell */
.adm-page {
    padding: 32px 0 48px;
}

/* --- PAGE HEADER --- */
.adm-page-header {
    padding: 28px 0 24px;
    border-bottom: 1px solid var(--border-subtle);
    background: #ffffff;
    margin-bottom: 0;
}

.adm-page-header-inner {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.adm-page-title {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--slate-900);
    letter-spacing: -0.025em;
    margin: 0 0 3px;
    line-height: 1.2;
}

.adm-page-subtitle {
    font-size: 13.5px;
    color: var(--slate-500);
    margin: 0;
    font-weight: 400;
}

.adm-page-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 2px;
}

.adm-comp-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: var(--brand-blue-subtle);
    border: 1px solid #bfdbfe;
    border-radius: var(--radius-sm);
    font-size: 12px;
    font-weight: 600;
    color: var(--brand-blue-dark);
    white-space: nowrap;
}

.adm-comp-tag i {
    color: var(--brand-amber);
    font-size: 11px;
}

/* --- SECTION LABELS --- */
.adm-section-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--slate-400);
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border-subtle);
}

/* --- MASTER TOGGLE CONTROL --- */
.adm-toggle-strip {
    background: #ffffff;
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    box-shadow: var(--shadow-xs);
}

.adm-toggle-strip.is-open {
    border-left: 3px solid var(--semantic-success);
}

.adm-toggle-strip.is-locked {
    border-left: 3px solid var(--semantic-danger);
}

.adm-toggle-left {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
}

.adm-toggle-icon {
    width: 38px;
    height: 38px;
    border-radius: var(--radius-sm);
    display: grid;
    place-items: center;
    font-size: 16px;
    flex-shrink: 0;
}

.adm-toggle-icon.is-open {
    background: var(--semantic-success-bg);
    color: var(--semantic-success);
    border: 1px solid var(--semantic-success-border);
}

.adm-toggle-icon.is-locked {
    background: var(--semantic-danger-bg);
    color: var(--semantic-danger);
    border: 1px solid var(--semantic-danger-border);
}

.adm-toggle-info {
    min-width: 0;
}

.adm-toggle-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--slate-900);
    margin: 0 0 2px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.adm-toggle-desc {
    font-size: 12.5px;
    color: var(--slate-500);
    margin: 0;
    line-height: 1.4;
}

.adm-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: var(--radius-sm);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.adm-status-pill.is-open {
    background: var(--semantic-success-bg);
    color: var(--semantic-success-text);
    border: 1px solid var(--semantic-success-border);
}

.adm-status-pill.is-locked {
    background: var(--semantic-danger-bg);
    color: var(--semantic-danger-text);
    border: 1px solid var(--semantic-danger-border);
}

/* --- KPI STAT CARDS (compact, left-aligned, information-dense) --- */
.adm-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

@media (max-width: 991px) {
    .adm-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .adm-kpi-grid {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
}

.adm-kpi {
    background: #ffffff;
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    padding: 16px 18px;
    box-shadow: var(--shadow-xs);
    display: flex;
    align-items: flex-start;
    gap: 14px;
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}

.adm-kpi:hover {
    border-color: var(--border-strong);
    box-shadow: var(--shadow-sm);
}

.adm-kpi-icon {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-sm);
    display: grid;
    place-items: center;
    font-size: 15px;
    flex-shrink: 0;
    margin-top: 2px;
}

.adm-kpi-body {
    min-width: 0;
    flex: 1;
}

.adm-kpi-value {
    font-family: var(--font-mono);
    font-size: 1.7rem;
    font-weight: 800;
    color: var(--slate-900);
    line-height: 1;
    font-variant-numeric: tabular-nums;
    margin-bottom: 3px;
    display: block;
}

.adm-kpi-label {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--slate-500);
    line-height: 1.3;
    display: block;
}

/* KPI Color Variants */
.adm-kpi-icon.amber    { background: #fffbeb; color: #b45309; }
.adm-kpi-icon.blue     { background: #eff6ff; color: #1d4ed8; }
.adm-kpi-icon.emerald  { background: #ecfdf5; color: #047857; }
.adm-kpi-icon.rose     { background: #fff1f2; color: #be123c; }

/* --- QUICK NAV LINKS --- */
.adm-quicknav {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}

@media (max-width: 767px) {
    .adm-quicknav {
        grid-template-columns: repeat(2, 1fr);
    }
}

.adm-quicknav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 15px;
    background: #ffffff;
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    text-decoration: none;
    color: var(--slate-700);
    font-size: 13.5px;
    font-weight: 600;
    box-shadow: var(--shadow-xs);
    transition: all var(--transition-fast);
}

.adm-quicknav-item:hover {
    background: var(--brand-blue-subtle);
    border-color: #bfdbfe;
    color: var(--brand-blue-dark);
    box-shadow: var(--shadow-sm);
}

.adm-quicknav-item i {
    font-size: 16px;
    color: var(--slate-400);
    width: 20px;
    text-align: center;
    flex-shrink: 0;
    transition: color var(--transition-fast);
}

.adm-quicknav-item:hover i {
    color: var(--brand-blue);
}

/* --- TABLE PANELS --- */
.adm-panel {
    background: #ffffff;
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-xs);
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.adm-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    border-bottom: 1px solid var(--border-subtle);
    background: var(--slate-50);
    flex-shrink: 0;
}

.adm-panel-title {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--slate-800);
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 0;
}

.adm-panel-title i {
    font-size: 13px;
    color: var(--slate-400);
}

.adm-panel-body {
    flex: 1;
    overflow: auto;
}

/* Clean table overrides for admin panels */
.adm-panel .table {
    margin: 0;
    font-size: 13px;
}

.adm-panel .table > thead > tr > th {
    font-size: 11px;
    padding: 10px 16px;
    background: transparent;
    color: var(--slate-500);
    border-bottom: 1px solid var(--border-subtle);
}

.adm-panel .table > tbody > tr > td {
    padding: 11px 16px;
    border-bottom: 1px solid var(--slate-100);
    font-size: 13px;
    vertical-align: middle;
}

.adm-panel .table > tbody > tr:last-child > td {
    border-bottom: none;
}

.adm-panel .table-responsive {
    border: none;
    border-radius: 0;
}

/* Empty state */
.adm-empty {
    padding: 32px 20px;
    text-align: center;
}

.adm-empty-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    background: var(--slate-100);
    display: grid;
    place-items: center;
    margin: 0 auto 10px;
    font-size: 16px;
    color: var(--slate-400);
}

.adm-empty p {
    font-size: 13px;
    color: var(--slate-400);
    margin: 0;
}

/* Approve button */
.adm-btn-approve {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 600;
    border-radius: var(--radius-sm);
    border: 1px solid var(--semantic-success-border);
    background: var(--semantic-success-bg);
    color: var(--semantic-success-text);
    cursor: pointer;
    transition: all var(--transition-fast);
    white-space: nowrap;
    font-family: var(--font-sans);
}

.adm-btn-approve:hover {
    background: var(--semantic-success);
    color: #ffffff;
    border-color: var(--semantic-success);
}

/* Role badge */
.adm-role-tag {
    display: inline-block;
    padding: 2px 8px;
    border-radius: var(--radius-sm);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    background: var(--slate-100);
    color: var(--slate-600);
    border: 1px solid var(--border-subtle);
}

/* Zone tag */
.adm-zone-tag {
    display: inline-block;
    padding: 2px 7px;
    border-radius: var(--radius-sm);
    font-size: 11px;
    font-weight: 600;
    background: var(--brand-blue-subtle);
    color: var(--brand-blue-dark);
    border: 1px solid #bfdbfe;
}

/* Primary name */
.adm-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--slate-800);
    display: block;
    line-height: 1.3;
}

.adm-sub {
    font-size: 11.5px;
    color: var(--slate-400);
    display: block;
    margin-top: 1px;
}

/* View all link */
.adm-view-all {
    font-size: 12px;
    font-weight: 600;
    color: var(--brand-blue);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 0;
    border-bottom: 1px solid transparent;
    transition: border-color var(--transition-fast), color var(--transition-fast);
}

.adm-view-all:hover {
    color: var(--brand-navy);
    border-bottom-color: var(--brand-navy);
}

/* Responsive page header */
@media (max-width: 576px) {
    .adm-page-header-inner {
        flex-direction: column;
        gap: 10px;
    }
    .adm-toggle-strip {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<!-- ============================= PAGE HEADER ============================= -->
<div class="adm-page-header">
    <div class="container">
        <div class="adm-page-header-inner">
            <div>
                <h1 class="adm-page-title">Dashboard Pentadbir</h1>
                <p class="adm-page-subtitle"><?= e($user['full_name']) ?> &middot; Kawalan Penuh Sistem KVSkills Hub</p>
                <?php if ($comp): ?>
                    <div class="adm-page-meta mt-2">
                        <span class="adm-comp-tag">
                            <i class="fa-solid fa-trophy"></i>
                            <?= e($comp['name']) ?>
                            &middot;
                            <span class="status status-<?= e($comp['status']) ?>"><?= ucfirst(e($comp['status'])) ?></span>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============================= MAIN CONTENT ============================= -->
<div class="adm-page">
    <div class="container">

        <!-- ── MASTER TOGGLE ── -->
        <?php $isPtScoring = is_pt_score_entry_enabled(); ?>
        <div class="adm-section-label">Kawalan Sistem</div>

        <div class="adm-toggle-strip <?= $isPtScoring ? 'is-open' : 'is-locked' ?> mb-4">
            <div class="adm-toggle-left">
                <div class="adm-toggle-icon <?= $isPtScoring ? 'is-open' : 'is-locked' ?>">
                    <i class="fa-solid <?= $isPtScoring ? 'fa-lock-open' : 'fa-lock' ?>"></i>
                </div>
                <div class="adm-toggle-info">
                    <div class="adm-toggle-title">
                        Kemasukan Markah Pegawai Teknikal
                        <span class="adm-status-pill <?= $isPtScoring ? 'is-open' : 'is-locked' ?>">
                            <?= $isPtScoring ? 'Dibuka' : 'Dikunci' ?>
                        </span>
                    </div>
                    <p class="adm-toggle-desc">
                        <?= $isPtScoring
                            ? 'Pegawai Teknikal dibenarkan memasukkan dan mengemas kini markah peserta bagi zon masing-masing.'
                            : 'Kemasukan markah oleh Pegawai Teknikal telah dikunci. Borang markah pada portal PT kini dinyahaktifkan.'
                        ?>
                    </p>
                </div>
            </div>
            <form method="post" class="m-0 flex-shrink-0" onsubmit="return confirm('Adakah anda pasti ingin mengubah status kebenaran kemasukan markah bagi Pegawai Teknikal?')">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="toggle_pt_scoring">
                <button type="submit" class="btn <?= $isPtScoring ? 'btn-danger' : 'btn-success' ?> btn-sm">
                    <i class="fa-solid <?= $isPtScoring ? 'fa-lock' : 'fa-lock-open' ?>"></i>
                    <?= $isPtScoring ? 'Kunci Markah' : 'Buka Markah' ?>
                </button>
            </form>
        </div>

        <!-- ── KPI STATS ── -->
        <div class="adm-section-label">Statistik Semasa</div>

        <div class="adm-kpi-grid mb-4">
            <div class="adm-kpi">
                <div class="adm-kpi-icon amber">
                    <i class="fa-solid fa-user-clock"></i>
                </div>
                <div class="adm-kpi-body">
                    <span class="adm-kpi-value"><?= $counts['pending_users'] ?></span>
                    <span class="adm-kpi-label">Akaun Menunggu</span>
                </div>
            </div>
            <div class="adm-kpi">
                <div class="adm-kpi-icon blue">
                    <i class="fa-solid fa-user-gear"></i>
                </div>
                <div class="adm-kpi-body">
                    <span class="adm-kpi-value"><?= $counts['active_tech'] ?></span>
                    <span class="adm-kpi-label">Pegawai Teknikal</span>
                </div>
            </div>
            <div class="adm-kpi">
                <div class="adm-kpi-icon emerald">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
                <div class="adm-kpi-body">
                    <span class="adm-kpi-value"><?= $counts['active_coaches'] ?></span>
                    <span class="adm-kpi-label">Jurulatih Aktif</span>
                </div>
            </div>
            <div class="adm-kpi">
                <div class="adm-kpi-icon rose">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <div class="adm-kpi-body">
                    <span class="adm-kpi-value"><?= $counts['submitted_regs'] ?></span>
                    <span class="adm-kpi-label">Pendaftaran Menunggu</span>
                </div>
            </div>
        </div>

        <!-- ── QUICK NAV ── -->
        <div class="adm-section-label">Navigasi Pantas</div>

        <div class="adm-quicknav mb-4">
            <a class="adm-quicknav-item" href="<?= e(url('admin/pengguna.php')) ?>">
                <i class="fa-solid fa-users-gear"></i>
                Pengurusan Pengguna
            </a>
            <a class="adm-quicknav-item" href="<?= e(url('admin/pegawai_teknikal.php')) ?>">
                <i class="fa-solid fa-user-gear"></i>
                Pegawai Teknikal
            </a>
            <a class="adm-quicknav-item" href="<?= e(url('admin/keputusan.php')) ?>">
                <i class="fa-solid fa-ranking-star"></i>
                Keputusan
            </a>
            <a class="adm-quicknav-item" href="<?= e(url('admin/tetapan.php')) ?>">
                <i class="fa-solid fa-gear"></i>
                Tetapan Pentadbir
            </a>
        </div>

        <!-- ── TABLES ROW ── -->
        <div class="adm-section-label">Tindakan Diperlukan</div>

        <div class="row g-3">
            <!-- Pending Users -->
            <div class="col-lg-6">
                <div class="adm-panel">
                    <div class="adm-panel-header">
                        <h2 class="adm-panel-title">
                            <i class="fa-solid fa-user-clock"></i>
                            Akaun Menunggu Kelulusan
                        </h2>
                        <a href="<?= e(url('admin/pengguna.php?status=pending')) ?>" class="adm-view-all">
                            Lihat semua <i class="fa-solid fa-arrow-right" style="font-size: 10px;"></i>
                        </a>
                    </div>
                    <div class="adm-panel-body">
                        <?php if ($pendingUsers): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nama / Emel</th>
                                            <th>Peranan</th>
                                            <th>Tindakan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingUsers as $pu): ?>
                                            <tr>
                                                <td>
                                                    <span class="adm-name"><?= e($pu['full_name']) ?></span>
                                                    <span class="adm-sub"><?= e($pu['email']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="adm-role-tag"><?= e(ucfirst($pu['role'])) ?></span>
                                                </td>
                                                <td>
                                                    <form method="post" class="d-inline">
                                                        <?= Csrf::field() ?>
                                                        <input type="hidden" name="action" value="approve_user">
                                                        <input type="hidden" name="user_id" value="<?= (int)$pu['id'] ?>">
                                                        <button class="adm-btn-approve" type="submit" title="Luluskan Akaun">
                                                            <i class="fa-solid fa-check"></i> Lulus
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="adm-empty">
                                <div class="adm-empty-icon">
                                    <i class="fa-solid fa-check-double"></i>
                                </div>
                                <p>Tiada akaun menunggu kelulusan.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Registrations -->
            <div class="col-lg-6">
                <div class="adm-panel">
                    <div class="adm-panel-header">
                        <h2 class="adm-panel-title">
                            <i class="fa-solid fa-clipboard-check"></i>
                            Pendaftaran Peserta Terkini
                        </h2>
                        <span class="adm-role-tag"><?= count($recentRegs) ?> rekod</span>
                    </div>
                    <div class="adm-panel-body">
                        <?php if ($recentRegs): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Peserta / Institusi</th>
                                            <th>Bidang</th>
                                            <th>Zon</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentRegs as $r): ?>
                                            <tr>
                                                <td>
                                                    <span class="adm-name"><?= e($r['participant_name']) ?></span>
                                                    <span class="adm-sub"><?= e($r['institution']) ?></span>
                                                </td>
                                                <td style="max-width: 140px;">
                                                    <span style="font-size: 12.5px; color: var(--slate-700); font-weight: 500;"><?= e($r['skill_name']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="adm-zone-tag"><?= e($r['zone_name']) ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="adm-empty">
                                <div class="adm-empty-icon">
                                    <i class="fa-solid fa-inbox"></i>
                                </div>
                                <p>Tiada pendaftaran menunggu semakan.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require BASE_PATH . '/partials/footer.php'; ?>