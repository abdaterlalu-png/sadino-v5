<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache ?: null;
    $id = (int)($_SESSION['user_id'] ?? 0);
    if ($id < 1) return $cache = null;
    $stmt = db()->prepare('SELECT * FROM users WHERE id=? AND is_active=1 LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) return $cache = null;
    $expected=(int)($_SESSION['session_version']??0);
    if($expected<1 || $expected!==(int)($user['session_version']??1)){
        $_SESSION=[]; return $cache=null;
    }
    return $cache = $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) redirect('/?page=login');
    return $user;
}

function login_throttled(string $username, string $ip): bool
{
    $window = (int)env('LOGIN_RATE_LIMIT_WINDOW_SECONDS', 900);
    $max = (int)env('LOGIN_RATE_LIMIT_MAX_ATTEMPTS', 5);
    $cutoff = date('Y-m-d H:i:s', time() - max(60, $window));
    $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE username=? AND ip_address=? AND succeeded=0 AND created_at >= ?');
    $stmt->execute([$username, $ip, $cutoff]);
    return (int)$stmt->fetchColumn() >= $max;
}

function attempt_login(string $username, string $password): array
{
    $username = trim($username); $ip = client_ip();
    if ($username === '' || $password === '') return [false, 'Username dan password wajib diisi.'];
    if (login_throttled($username, $ip)) return [false, 'Terlalu banyak percobaan login. Coba kembali beberapa menit lagi.'];
    $stmt = db()->prepare('SELECT * FROM users WHERE username=? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    $ok = $user && (int)$user['is_active'] === 1 && password_verify($password, (string)$user['password_hash']);
    db()->prepare('INSERT INTO login_attempts (username, ip_address, succeeded, created_at) VALUES (?,?,?,NOW())')->execute([$username, $ip, $ok ? 1 : 0]);
    if (!$ok) return [false, 'Username atau password salah.'];
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['session_version'] = (int)($user['session_version']??1);
    db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([(int)$user['id']]);
    audit('login', 'user', (string)$user['id']);
    return [true, 'Login berhasil.'];
}

function logout_user(): void
{
    $u = current_user();
    if ($u) audit('logout', 'user', (string)$u['id']);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
