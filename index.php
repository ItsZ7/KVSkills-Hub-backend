<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

$competition = null;
$stats = [
    'zones'        => 0,
    'skills'       => 0,
    'participants' => 0,
    'venues'       => 0,
];

try {
    $competition = active_competition();
    $stats['zones']        = (int) db()->query('SELECT COUNT(*) FROM zones WHERE is_active = 1')->fetchColumn();
    $stats['skills']       = (int) db()->query('SELECT COUNT(*) FROM skills WHERE is_active = 1')->fetchColumn();
    $stats['participants'] = (int) db()->query("SELECT COUNT(*) FROM registrations WHERE status = 'approved'")->fetchColumn();
    $stats['venues']       = (int) db()->query('SELECT COUNT(*) FROM venues WHERE is_active = 1')->fetchColumn();
} catch (Throwable $e) {
    $setupError = APP_ENV !== 'production' && APP_DEBUG ? $e->getMessage() : 'Pangkalan data belum disediakan.';
}

$pageTitle  = 'Portal Rasmi';
$activePage = 'home';
$currentUser = Auth::user();

require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/public_navbar.php'; // Glassmorphism navbar — awam sahaja
require BASE_PATH . '/partials/flash.php';
?>

<style>
/* ==========================================================================
   PUBLIC HERO AMBIENT ANIMATION (Aesthetic Earth Orbital Motion: Kiri ke Kanan)
   ========================================================================== */
.public-animated-hero {
    position: relative;
    overflow: hidden;
    background: #eaebec;
}
.public-hero-backdrop {
    position: absolute;
    inset: -30px -40px;
    pointer-events: none;
    user-select: none;
    z-index: 0;
    overflow: hidden;
}
.public-hero-orbital-drift {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    will-change: transform;
    animation: earthOrbitalMotion 22s cubic-bezier(0.42, 0, 0.58, 1) infinite;
    transform-origin: 82% 50%;
}
.public-hero-bg-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center center;
    display: block;
}
.public-hero-veil {
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
    background: radial-gradient(ellipse 65% 75% at 50% 48%, rgba(255, 255, 255, 0.82) 0%, rgba(255, 255, 255, 0.45) 55%, rgba(255, 255, 255, 0.08) 100%);
}
.public-animated-hero > .container {
    position: relative;
    z-index: 2;
    pointer-events: auto;
}

/* Pergerakan orbit bumi yang perlahan, anggun & estetik: Dari Kiri ke Kanan */
@keyframes earthOrbitalMotion {
    0% {
        transform: translate3d(-26px, 4px, 0) scale(1.03) rotate(-0.5deg);
    }
    25% {
        transform: translate3d(-7px, 8px, 0) scale(1.045) rotate(0deg);
    }
    50% {
        transform: translate3d(26px, 2px, 0) scale(1.03) rotate(0.5deg);
    }
    75% {
        transform: translate3d(0px, -6px, 0) scale(1.015) rotate(0deg);
    }
    100% {
        transform: translate3d(-26px, 4px, 0) scale(1.03) rotate(-0.5deg);
    }
}

@media (max-width: 768px) {
    .public-hero-backdrop {
        inset: -15px -20px;
    }
    @keyframes earthOrbitalMotion {
        0% { transform: translate3d(-12px, 2px, 0) scale(1.02); }
        25% { transform: translate3d(-3px, 4px, 0) scale(1.03); }
        50% { transform: translate3d(12px, 1px, 0) scale(1.02); }
        75% { transform: translate3d(0px, -3px, 0) scale(1.01); }
        100% { transform: translate3d(-12px, 2px, 0) scale(1.02); }
    }
}

@media (prefers-reduced-motion: reduce) {
    .public-hero-orbital-drift {
        animation: none !important;
        transform: none !important;
    }
}
</style>

<!-- Institutional Hero & Competition Header (Public Page with subtle ambient animation) -->
<section class="dashboard-header public-animated-hero pb-5">
    <div class="public-hero-backdrop" id="publicHeroBackdrop" aria-hidden="true">
        <div class="public-hero-orbital-drift">
            <img src="<?= e(url('assets/images/public-hero-bg.jpg')) ?>" alt="" class="public-hero-bg-img">
        </div>
        <div class="public-hero-veil"></div>
    </div>
    <div class="container text-center">
        <!-- Institutional Eyebrow Badge -->
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-3 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-award text-warning"></i>
            <span>Kementerian Pendidikan Malaysia &middot; BPLTV</span>
        </div>

        <h1 class="display-6 fw-bold text-dark mb-2">KVSkills Hub Malaysia</h1>
        <p class="lead mx-auto mb-4 text-muted" style="max-width: 740px; font-size: 1.05rem; line-height: 1.6;">
            Portal pengurusan rasmi pertandingan kemahiran Kolej Vokasional peringkat kebangsaan yang berpusat, telus dan berintegriti tinggi.
        </p>

        <?php if ($competition): ?>
            <!-- Competition Details Metadata Chips -->
            <div class="d-flex flex-wrap justify-content-center align-items-center gap-2 mb-4">
                <span class="event-pill">
                    <i class="fa-solid fa-trophy"></i>
                    <span><?= e($competition['name']) ?></span>
                </span>
                <span class="event-pill">
                    <i class="fa-solid fa-calendar-days"></i>
                    <span><?= e(format_date($competition['start_date'])) ?> - <?= e(format_date($competition['end_date'])) ?></span>
                </span>
                <span class="event-pill">
                    <i class="fa-solid fa-map-location-dot"></i>
                    <span>Zon Hos <?= e($competition['host_zone']) ?></span>
                </span>
            </div>

            <!-- PRECISION COUNTDOWN TIMER CONSOLE -->
            <div class="countdown-container">
                <div class="countdown-title">
                    <i class="fa-solid fa-hourglass-half me-1"></i> Kira Detik Menuju Pertandingan
                </div>
                <div class="countdown-grid" id="kvCountdown" 
                     data-start="<?= e($competition['start_date']) ?>T08:00:00" 
                     data-end="<?= e($competition['end_date']) ?>T18:00:00"
                     data-status="<?= e($competition['status']) ?>">
                    <div class="countdown-card">
                        <span class="countdown-num" id="cdDays">00</span>
                        <span class="countdown-txt">HARI</span>
                    </div>
                    <div class="countdown-colon">:</div>
                    <div class="countdown-card">
                        <span class="countdown-num" id="cdHours">00</span>
                        <span class="countdown-txt">JAM</span>
                    </div>
                    <div class="countdown-colon">:</div>
                    <div class="countdown-card">
                        <span class="countdown-num" id="cdMinutes">00</span>
                        <span class="countdown-txt">MINIT</span>
                    </div>
                    <div class="countdown-colon">:</div>
                    <div class="countdown-card">
                        <span class="countdown-num" id="cdSeconds">00</span>
                        <span class="countdown-txt">SAAT</span>
                    </div>
                </div>
                <div id="countdownBadge" class="mt-3" style="display:none;"></div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Error Alert Handling -->
<?php if (isset($setupError)): ?>
    <div class="container my-3">
        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>Sistem memerlukan konfigurasi pangkalan data. <?= e($setupError) ?></div>
        </div>
    </div>
<?php endif; ?>

<!-- Institutional KPI Metrics (Real Data Only) -->
<section class="container py-4">
    <div class="row g-3">
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-stat-card accent-navy">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-map-location-dot"></i>
                    </div>
                    <span class="kpi-tag">Kebangsaan</span>
                </div>
                <div>
                    <div class="kpi-value"><?= (int) $stats['zones'] ?></div>
                    <div class="kpi-label">Zon Kontinjen</div>
                    <p class="kpi-desc">Penyertaan kontinjen zon seluruh Malaysia</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-stat-card accent-amber">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                    </div>
                    <span class="kpi-tag">Piawaian</span>
                </div>
                <div>
                    <div class="kpi-value"><?= (int) $stats['skills'] ?></div>
                    <div class="kpi-label">Bidang Kemahiran</div>
                    <p class="kpi-desc">Acara kemahiran TVET yang dipertandingkan</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-stat-card accent-blue">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                    <span class="kpi-tag">Zon Hos</span>
                </div>
                <div>
                    <div class="kpi-value"><?= (int) $stats['venues'] ?></div>
                    <div class="kpi-label">Pusat Pertandingan</div>
                    <p class="kpi-desc">Kolej Vokasional hos terpilih dan bengkel</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-stat-card accent-emerald">
                <div class="kpi-header">
                    <div class="kpi-icon-wrap">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <span class="kpi-tag">Disahkan</span>
                </div>
                <div>
                    <div class="kpi-value"><?= (int) $stats['participants'] ?></div>
                    <div class="kpi-label">Calon Peserta</div>
                    <p class="kpi-desc">Pendaftaran rasmi disahkan pegawai teknikal</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Bento Grid: 4 Primary Public Services -->
<section class="container py-4">
    <div class="mb-4">
        <div class="text-uppercase text-primary fw-bold small tracking-wide mb-1" style="letter-spacing: 0.08em; font-size: 11.5px;">Direktori Utama Pertandingan</div>
        <h2 class="h4 fw-bold text-dark mb-1">Perkhidmatan &amp; Maklumat Rasmi</h2>
        <p class="text-muted small mb-0">Akses terus kepada jadual penataran teknikal, panduan 9 lokasi pusat pertandingan, direktori kontinjen dan keputusan rasmi kebangsaan.</p>
    </div>
    <div class="row g-4">
        <div class="col-md-6 col-lg-3">
            <a href="<?= e(url('maklumat/jadual.php')) ?>" class="bento-service-card bento-accent-navy">
                <div>
                    <div class="bento-header">
                        <div class="bento-icon">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                        <span class="bento-badge">Taklimat Maya</span>
                    </div>
                    <h3 class="bento-title">Jadual &amp; Penataran</h3>
                    <p class="bento-desc">Tarikh, masa, pautan Google Meet dan jadual giliran pertandingan bagi kesemua 22 bidang kemahiran.</p>
                </div>
                <div class="bento-footer">
                    <span>Buka Jadual &amp; Dokumen</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a href="<?= e(url('maklumat/lokasi.php')) ?>" class="bento-service-card bento-accent-teal">
                <div>
                    <div class="bento-header">
                        <div class="bento-icon">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <span class="bento-badge">9 Pusat KV</span>
                    </div>
                    <h3 class="bento-title">Lokasi Pusat</h3>
                    <p class="bento-desc">Panduan 9 lokasi rasmi Kolej Vokasional hos yang menempatkan bengkel pertandingan mengikut bidang kemahiran.</p>
                </div>
                <div class="bento-footer">
                    <span>Lihat Direktori Lokasi</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a href="<?= e(url('maklumat/peserta.php')) ?>" class="bento-service-card bento-accent-indigo">
                <div>
                    <div class="bento-header">
                        <div class="bento-icon">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <span class="bento-badge">10 Kontinjen</span>
                    </div>
                    <h3 class="bento-title">Senarai Peserta</h3>
                    <p class="bento-desc">Carian dan semakan profil kontinjen peserta mengikut zon dan bidang selepas disahkan oleh pegawai teknikal.</p>
                </div>
                <div class="bento-footer">
                    <span>Semak Direktori Peserta</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a href="<?= e(url('maklumat/keputusan.php')) ?>" class="bento-service-card bento-accent-amber">
                <div>
                    <div class="bento-header">
                        <div class="bento-icon">
                            <i class="fa-solid fa-trophy"></i>
                        </div>
                        <span class="bento-badge">Papan Pingat</span>
                    </div>
                    <h3 class="bento-title">Keputusan Rasmi</h3>
                    <p class="bento-desc">Kedudukan mata rasmi kontinjen, penentuan medal emas, perak, gangsa dan anugerah kecemerlangan TVET.</p>
                </div>
                <div class="bento-footer">
                    <span>Lihat Keputusan Rasmi</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>
        </div>
    </div>
</section>

<!-- Official Technical Regulations for Assistant & Model -->
<section class="container py-4">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="fa-solid fa-clipboard-check text-primary fs-5"></i>
                        <h2 class="h5 mb-0 fw-bold text-dark">Peraturan Khusus Pembantu &amp; Model Peserta</h2>
                    </div>
                    <p class="text-muted small mb-0">Ketetapan teknikal pendaftaran pembantu dan model mengikut piawaian rasmi BPLTV.</p>
                </div>
                <span class="badge bg-light text-secondary border px-3 py-2" style="font-size: 11.5px; font-weight: 600;">
                    <i class="fa-solid fa-circle-check text-success me-1"></i> Pekeliling Teknikal Berkuat Kuasa
                </span>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="regulation-card">
                        <div class="regulation-head">
                            <div class="regulation-icon">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                            </div>
                            <div>
                                <h3 class="regulation-title">Beauty Therapy</h3>
                                <small class="text-muted">Kod Bidang: BT</small>
                            </div>
                        </div>
                        <span class="regulation-badge">1 Model + 1 Pembantu</span>
                        <p class="regulation-text">
                            Wajib mendaftarkan tepat <strong>1 orang model</strong> dan <strong>1 orang pembantu bertanding</strong> yang memenuhi syarat kelayakan teknikal.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="regulation-card">
                        <div class="regulation-head">
                            <div class="regulation-icon">
                                <i class="fa-solid fa-utensils"></i>
                            </div>
                            <div>
                                <h3 class="regulation-title">Cooking</h3>
                                <small class="text-muted">Kod Bidang: CK</small>
                            </div>
                        </div>
                        <span class="regulation-badge">1 Pembantu Bertanding</span>
                        <p class="regulation-text">
                            Dibenarkan mendaftarkan tepat <strong>1 orang pembantu</strong> bertanding bagi tugasan persediaan bahan dan peralatan dapur.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="regulation-card">
                        <div class="regulation-head">
                            <div class="regulation-icon">
                                <i class="fa-solid fa-seedling"></i>
                            </div>
                            <div>
                                <h3 class="regulation-title">Landscape Gardening</h3>
                                <small class="text-muted">Kod Bidang: LG</small>
                            </div>
                        </div>
                        <span class="regulation-badge">1 Pembantu Bertanding</span>
                        <p class="regulation-text">
                            Dibenarkan mendaftarkan tepat <strong>1 orang pembantu</strong> bertanding bagi kerja binaan struktur landskap dan penanaman.
                        </p>
                    </div>
                </div>
            </div>

            <div class="compliance-notice-box mt-4">
                <i class="fa-solid fa-shield-halved text-primary fs-5 mt-1 flex-shrink-0"></i>
                <div class="small text-slate-700">
                    <strong>Peringatan Pematuhan Teknikal:</strong>
                    Bidang kemahiran selain tiga bidang di atas tidak dibenarkan mendaftarkan sebarang pembantu atau model mengikut ketetapan surat pekeliling teknikal rasmi. Sebarang ketidakpatuhan boleh menyebabkan pembatalan pendaftaran peserta berkenaan.
                </div>
            </div>
        </div>
    </div>
</section>



<!-- Precision Countdown Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('kvCountdown');
    if (!el) return;
    const startTarget = new Date(el.getAttribute('data-start')).getTime();
    const endTarget = new Date(el.getAttribute('data-end')).getTime();
    const compStatus = el.getAttribute('data-status') || '';
    const badgeEl = document.getElementById('countdownBadge');
    const cdDays = document.getElementById('cdDays');
    const cdHours = document.getElementById('cdHours');
    const cdMinutes = document.getElementById('cdMinutes');
    const cdSeconds = document.getElementById('cdSeconds');

    function showCompleted() {
        if (badgeEl) {
            badgeEl.style.display = 'inline-flex';
            badgeEl.innerHTML = '<span class="hero-status-pill status-completed"><i class="fa-solid fa-trophy me-1 text-warning"></i> Pertandingan Rasmi Telah Selesai</span>';
        }
        if (cdDays) cdDays.textContent = '00';
        if (cdHours) cdHours.textContent = '00';
        if (cdMinutes) cdMinutes.textContent = '00';
        if (cdSeconds) cdSeconds.textContent = '00';
    }

    // Jika status ialah 'completed' atau 'archived', tunjuk selesai terus tanpa mengira tarikh
    if (compStatus === 'completed' || compStatus === 'archived') {
        showCompleted();
        return; // hentikan — tiada setInterval dipasang
    }

    function updateCountdown() {
        const now = new Date().getTime();
        const diff = startTarget - now;

        if (diff <= 0) {
            if (now < endTarget) {
                if (badgeEl) {
                    badgeEl.style.display = 'inline-flex';
                    badgeEl.innerHTML = '<span class="hero-status-pill status-ongoing"><i class="fa-solid fa-circle-dot me-1"></i> Pertandingan Sedang Berlangsung</span>';
                }
            } else {
                showCompleted();
            }
            if (cdDays) cdDays.textContent = '00';
            if (cdHours) cdHours.textContent = '00';
            if (cdMinutes) cdMinutes.textContent = '00';
            if (cdSeconds) cdSeconds.textContent = '00';
            return;
        }

        if (badgeEl) {
            badgeEl.style.display = 'inline-flex';
            badgeEl.innerHTML = '<span class="hero-status-pill status-upcoming"><i class="fa-solid fa-hourglass-half me-1 text-warning"></i> Kira Detik Menuju Pertandingan</span>';
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        if (cdDays) cdDays.textContent = String(days).padStart(2, '0');
        if (cdHours) cdHours.textContent = String(hours).padStart(2, '0');
        if (cdMinutes) cdMinutes.textContent = String(minutes).padStart(2, '0');
        if (cdSeconds) cdSeconds.textContent = String(seconds).padStart(2, '0');
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
});
</script>

<?php require BASE_PATH . '/partials/footer.php'; ?>