<?php
$success = flash('success');
$error = flash('error');
$errors = validation_errors();
$info = flash('info');
$warning = flash('warning');

$toasts = [];
if ($success) {
    $toasts[] = [
        'type' => 'success',
        'icon' => 'fa-circle-check',
        'title' => 'Berjaya',
        'message' => $success,
    ];
}
if ($error) {
    $toasts[] = [
        'type' => 'danger',
        'icon' => 'fa-circle-exclamation',
        'title' => 'Ralat',
        'message' => $error,
    ];
}
if ($warning) {
    $toasts[] = [
        'type' => 'warning',
        'icon' => 'fa-triangle-exclamation',
        'title' => 'Perhatian',
        'message' => $warning,
    ];
}
if ($info) {
    $toasts[] = [
        'type' => 'info',
        'icon' => 'fa-circle-info',
        'title' => 'Maklumat',
        'message' => $info,
    ];
}
if ($errors) {
    $toasts[] = [
        'type' => 'danger',
        'icon' => 'fa-circle-xmark',
        'title' => 'Sila Semak Maklumat',
        'items' => $errors,
    ];
}
?>
<?php if (!empty($toasts)): ?>
<?php
$renderToastHtml = static function (?string $text): string {
    if ($text === null || $text === '') {
        return '';
    }
    // Allow safe text-formatting tags only
    $allowedTags = '<strong><b><em><small><code><span><br>';
    $clean = strip_tags($text, $allowedTags);
    // Strip attributes from opening tags to prevent any event handlers
    return (string)preg_replace('/<([a-z1-6]+)\b[^>]*>/i', '<$1>', $clean);
};
?>
<div class="chrome-toast-container" id="chromeToastContainer">
    <?php foreach ($toasts as $index => $toast): ?>
        <div class="chrome-toast chrome-toast-<?= e($toast['type']) ?>" id="toast-<?= $index ?>" role="alert">
            <div class="chrome-toast-header">
                <img src="<?= e(url('assets/images/Logo KVSkills.png')) ?>" alt="Logo" class="chrome-toast-app-icon">
                <span class="chrome-toast-app-name">KVSkills Hub</span>
                <span class="chrome-toast-dot">•</span>
                <span class="chrome-toast-time">Baru sahaja</span>
                <button type="button" class="chrome-toast-close" onclick="dismissChromeToast('toast-<?= $index ?>')" aria-label="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="chrome-toast-body">
                <div class="chrome-toast-icon">
                    <i class="fa-solid <?= e($toast['icon']) ?>"></i>
                </div>
                <div class="chrome-toast-content">
                    <h6 class="chrome-toast-title"><?= e($toast['title']) ?></h6>
                    <?php if (!empty($toast['message'])): ?>
                        <p class="chrome-toast-msg"><?= $renderToastHtml($toast['message']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($toast['items'])): ?>
                        <ul class="chrome-toast-list">
                            <?php foreach ($toast['items'] as $item): ?>
                                <li><?= $renderToastHtml($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <div class="chrome-toast-progress">
                <div class="chrome-toast-progress-bar"></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
function dismissChromeToast(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('dismissing');
    setTimeout(() => {
        el.remove();
        const container = document.getElementById('chromeToastContainer');
        if (container && container.children.length === 0) {
            container.remove();
        }
    }, 320);
}

document.addEventListener('DOMContentLoaded', () => {
    const toasts = document.querySelectorAll('.chrome-toast');
    toasts.forEach((t) => {
        setTimeout(() => {
            if (document.body.contains(t)) {
                dismissChromeToast(t.id);
            }
        }, 5000);
    });
});
</script>
<?php endif; ?>

