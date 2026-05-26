<?php
declare(strict_types=1);

namespace App;

class Auth
{
    public static function register(string $email, string $password, string $displayName): array
    {
        $email = trim($email);
        $displayName = trim($displayName);

        if ($email === '' || $password === '' || $displayName === '') {
            http_response_code(400);
            return ['error' => 'Email, password and display name are required.'];
        }
        if (strlen($password) < 6) {
            http_response_code(400);
            return ['error' => 'Password must be at least 6 characters.'];
        }

        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            return ['error' => 'Email is already registered.'];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, display_name) VALUES (?, ?, ?)');
        $stmt->execute([$email, $hash, $displayName]);

        $_SESSION['user_id'] = (int) $pdo->lastInsertId();
        return [
            'user'      => self::currentUser(),
            'csrfToken' => self::ensureCsrfToken(),
        ];
    }

    public static function login(string $email, string $password): array
    {
        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT id, email, display_name, password_hash FROM users WHERE email = ?');
        $stmt->execute([trim($email)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            return ['error' => 'Invalid email or password.'];
        }

        $_SESSION['user_id'] = (int) $user['id'];
        return [
            'user' => [
                'id'          => (int) $user['id'],
                'email'       => $user['email'],
                'displayName' => $user['display_name'],
            ],
            'csrfToken' => self::ensureCsrfToken(),
        ];
    }

    // CSRF fix: issue and reuse a per-session token
    public static function ensureCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function currentUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function currentUser(): ?array
    {
        $id = self::currentUserId();
        if ($id === null) {
            return null;
        }
        $stmt = Db::pdo()->prepare('SELECT id, email, display_name FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if (!$u) {
            return null;
        }
        return [
            'id'          => (int) $u['id'],
            'email'       => $u['email'],
            'displayName' => $u['display_name'],
        ];
    }
}
