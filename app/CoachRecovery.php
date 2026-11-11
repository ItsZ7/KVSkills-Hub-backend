<?php
declare(strict_types=1);

final class CoachRecovery
{
    public static function request(string $email): void
    {
        if (!AccessLimit::reserve('recovery', $email, 3, 25) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $stmt = db()->prepare("SELECT id, email, password_hash FROM users WHERE role='coach' AND status='active' AND email=?");
        $stmt->execute([$email]);
        if ($coach = $stmt->fetch()) {
            $link = url('jurulatih/forgot_password.php?token=' . self::token($coach));
            if (!@mail($coach['email'], 'Pemulihan akaun KVSkills', "Buka pautan ini dalam masa 30 minit untuk menetapkan kata laluan anda.\n\n" . $link)) {
                error_log('KVSkills: Penghantaran e-mel pemulihan gagal.');
            }
        }
    }

    public static function token(array $coach): string
    {
        $payload = $coach['id'] . '.' . (time() + 1800);
        return $payload . '.' . self::signature($payload, $coach);
    }

    private static function signature(string $payload, array $coach): string
    {
        return hash_hmac('sha256', 'coach-reset|' . $payload . '|' . $coach['email'] . '|' . $coach['password_hash'], APP_KEY);
    }

    public static function redeem(string $token, string $password): bool
    {
        if (!preg_match('/\A([1-9][0-9]{0,18})\.([0-9]{10})\.([a-f0-9]{64})\z/', $token, $parts) || (int)$parts[2] <= time()) {
            return false;
        }
        $stmt = db()->prepare("SELECT id, email, password_hash FROM users WHERE id=? AND role='coach' AND status='active'");
        $stmt->execute([$parts[1]]);
        $coach = $stmt->fetch();
        if (!$coach || !hash_equals(self::signature($parts[1] . '.' . $parts[2], $coach), $parts[3])) {
            return false;
        }
        $update = db()->prepare("UPDATE users SET password_hash=? WHERE id=? AND BINARY password_hash=? AND email=? AND role='coach' AND status='active'");
        $update->execute([password_hash($password, PASSWORD_DEFAULT), $coach['id'], $coach['password_hash'], $coach['email']]);
        if ($update->rowCount() !== 1) return false;
        audit((int)$coach['id'], 'forgot_password_reset', 'user', (int)$coach['id']);
        return true;
    }
}
