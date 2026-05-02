<?php
require_once __DIR__ . '/Database.php';

class Auth {

    public static function boot(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function register(string $name, string $email, string $password): array {
        $email = strtolower(trim($email));

        if (Database::fetchOne('SELECT id FROM users WHERE email = ?', [$email])) {
            return ['ok' => false, 'msg' => 'Email already registered.'];
        }

        $uuid = self::uuid4();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        Database::insert(
            'INSERT INTO users (uuid, name, email, password) VALUES (?, ?, ?, ?)',
            [$uuid, trim($name), $email, $hash]
        );

        $user = Database::fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
        self::loginSession($user);
        return ['ok' => true];
    }

    public static function login(string $email, string $password, bool $remember = false): array {
        $email = strtolower(trim($email));
        $user  = Database::fetchOne('SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]);

        if (!$user || !password_verify($password, $user['password'])) {
            return ['ok' => false, 'msg' => 'Invalid email or password.'];
        }

        self::loginSession($user);

        if ($remember) {
            self::createRememberToken($user['id']);
        }

        return ['ok' => true];
    }

    public static function logout(): void {
        self::boot();

        if (isset($_COOKIE['rc_remember'])) {
            Database::query('DELETE FROM user_sessions WHERE token = ?', [$_COOKIE['rc_remember']]);
            setcookie('rc_remember', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        }

        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool {
        self::boot();

        if (!empty($_SESSION['user_id'])) {
            return true;
        }

        // Try remember-me cookie
        if (!empty($_COOKIE['rc_remember'])) {
            $row = Database::fetchOne(
                'SELECT u.* FROM user_sessions s JOIN users u ON u.id = s.user_id
                 WHERE s.token = ? AND s.expires_at > NOW() AND u.is_active = 1',
                [$_COOKIE['rc_remember']]
            );
            if ($row) {
                self::loginSession($row);
                return true;
            }
        }

        return false;
    }

    public static function user(): ?array {
        if (!self::check()) return null;
        return Database::fetchOne('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
    }

    public static function id(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    public static function requireLogin(string $redirect = 'login.php'): void {
        if (!self::check()) {
            header('Location: ' . APP_URL . '/' . $redirect);
            exit;
        }
    }

    public static function redirectIfLoggedIn(string $redirect = 'dashboard.php'): void {
        if (self::check()) {
            header('Location: ' . APP_URL . '/' . $redirect);
            exit;
        }
    }

    public static function csrfToken(): string {
        self::boot();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(string $token): bool {
        self::boot();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    // ----------------------------------------------------------------

    private static function loginSession(array $user): void {
        self::boot();
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
    }

    private static function createRememberToken(int $userId): void {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

        Database::insert(
            'INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at)
             VALUES (?, ?, ?, ?, ?)',
            [$userId, $token, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', $expires]
        );

        setcookie('rc_remember', $token, time() + SESSION_LIFETIME, '/', '', isset($_SERVER['HTTPS']), true);
    }

    private static function uuid4(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
