<?php
declare(strict_types=1);

require_once __DIR__ . '/AccessLimit.php';

final class Auth
{
    public static function user(): ?array
    {
        return isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])
            ? $_SESSION['auth_user'] : null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['auth_user']['id']) ? (int)$_SESSION['auth_user']['id'] : null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isTechnical(): bool
    {
        return self::role() === 'technical';
    }

    public static function isCoach(): bool
    {
        return self::role() === 'coach';
    }

    public static function attempt(string $email, string $password, string|array|null $requiredRole = null): bool
    {
        $email = mb_strtolower(trim($email));
        if (!AccessLimit::reserve('login', $email, 8, 100)) return false;

        $stmt = db()->prepare('SELECT u.*, z.name AS zone_name FROM users u LEFT JOIN zones z ON z.id=u.zone_id WHERE u.email=? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $valid = $user && password_verify($password, (string)$user['password_hash'])
            && $user['status'] === 'active'
            && ($requiredRole === null || (is_array($requiredRole) ? in_array($user['role'], $requiredRole, true) : $user['role'] === $requiredRole));

        if (!$valid) {
            return false;
        }

        AccessLimit::successfulLogin($email);
        $_SESSION['credential_tag'] = hash('sha256', $user['password_hash']);
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        unset($user['password_hash']);
        $_SESSION['auth_user'] = $user;
        db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([(int)$user['id']]);
        audit((int)$user['id'], 'login', 'user', (int)$user['id']);
        return true;
    }

    public static function changeOwnPassword(string $password): void
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, self::id()]);
        $_SESSION['credential_tag'] = hash('sha256', $hash);
        session_regenerate_id(true);
    }

    public static function refresh(): void
    {
        if (!self::id()) return;
        $stmt = db()->prepare('SELECT u.*, z.name AS zone_name FROM users u LEFT JOIN zones z ON z.id=u.zone_id WHERE u.id=? LIMIT 1');
        $stmt->execute([self::id()]);
        $user = $stmt->fetch();
        if (!$user || $user['status'] !== 'active' || !hash_equals($_SESSION['credential_tag'] ?? '', hash('sha256', $user['password_hash']))) {
            self::logout();
            return;
        }
        unset($user['password_hash']);
        $_SESSION['auth_user'] = $user;
    }

    public static function requireLogin(string|array|null $role = null): void
    {
        if (!self::check()) {
            flash('error', 'Sila log masuk untuk meneruskan.');
            redirect('jawatan.php');
        }
        if ($role !== null && !(is_array($role) ? in_array(self::user()['role'] ?? '', $role, true) : (self::user()['role'] ?? null) === $role)) {
            http_response_code(403);
            exit('Akses tidak dibenarkan.');
        }
    }

    public static function logout(): void
    {
        $id = self::id();
        if ($id) audit($id, 'logout', 'user', $id);
        $_SESSION = [];
        session_regenerate_id(true);
    }
}
