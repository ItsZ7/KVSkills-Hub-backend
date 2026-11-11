<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return APP_URL . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function redirect(string $path): never
{
    $target = preg_match('#^https?://#i', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $message;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function store_old(array $data): void
{
    foreach (array_keys($data) as $key) {
        if (preg_match('/pass|token/i', (string)$key)) unset($data[$key]);
    }
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function validation_errors(): array
{
    $errors = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_errors']);
    return is_array($errors) ? $errors : [];
}

function fail_validation(array $errors, string $path): never
{
    $_SESSION['_errors'] = $errors;
    store_old($_POST);
    redirect($path);
}

function selected(mixed $value, mixed $expected): string
{
    return (string)$value === (string)$expected ? 'selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? 'checked' : '';
}

function format_date(?string $date, string $format = 'd/m/Y'): string
{
    if (!$date) return '-';
    try { return (new DateTimeImmutable($date))->format($format); }
    catch (Throwable) { return $date; }
}

function format_time(?string $time): string
{
    if (!$time) return '-';
    try { return (new DateTimeImmutable($time))->format('g:i A'); }
    catch (Throwable) { return $time; }
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

function audit(?int $userId, string $action, string $entityType, ?int $entityId = null, array $metadata = []): void
{
    try {
        $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, metadata) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId, $action, $entityType, $entityId, client_ip(),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (Throwable) {
        // Audit failure must not break the primary transaction.
    }
}

function user_avatar_url(?string $path): string
{
    if ($path && (is_file(BASE_PATH . '/' . ltrim($path, '/')) || str_starts_with($path, 'http'))) {
        return url($path);
    }
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' fill='%2316215c'%3E%3Ccircle cx='50' cy='50' r='50' fill='%23e9ecef'/%3E%3Cpath d='M50 48a18 18 0 1 0 0-36 18 18 0 0 0 0 36zm0 10c-18 0-36 10-36 26v4h72v-4c0-16-18-26-36-26z'/%3E%3C/svg%3E";
}

function participant_photo_url(?string $path): string
{
    if ($path && (is_file(BASE_PATH . '/' . ltrim($path, '/')) || str_starts_with($path, 'http'))) {
        return url($path);
    }
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' fill='%23f7941d'%3E%3Ccircle cx='50' cy='50' r='50' fill='%23f8f9fa'/%3E%3Cpath d='M50 48a18 18 0 1 0 0-36 18 18 0 0 0 0 36zm0 10c-18 0-36 10-36 26v4h72v-4c0-16-18-26-36-26z'/%3E%3C/svg%3E";
}

function get_setting(string $key, mixed $default = null): mixed
{
    global $_system_settings_cache;
    if (!is_array($_system_settings_cache)) {
        $_system_settings_cache = [];
    }
    if (array_key_exists($key, $_system_settings_cache)) {
        return $_system_settings_cache[$key];
    }
    try {
        $stmt = db()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        $_system_settings_cache[$key] = ($val !== false) ? $val : $default;
        return $_system_settings_cache[$key];
    } catch (Throwable) {
        return $default;
    }
}

function set_setting(string $key, mixed $value, string $description = ''): bool
{
    global $_system_settings_cache;
    if (!is_array($_system_settings_cache)) {
        $_system_settings_cache = [];
    }
    try {
        $stmt = db()->prepare("
            INSERT INTO system_settings(setting_key, setting_value, description)
            VALUES(?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = IF(VALUES(description) != '', VALUES(description), description)
        ");
        $res = $stmt->execute([$key, (string)$value, $description]);
        if ($res) {
            $_system_settings_cache[$key] = (string)$value;
        }
        return $res;
    } catch (Throwable) {
        return false;
    }
}

function is_pt_score_entry_enabled(): bool
{
    return (string)get_setting('pt_score_entry_enabled', '1') === '1';
}


