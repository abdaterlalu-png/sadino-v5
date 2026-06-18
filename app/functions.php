<?php
declare(strict_types=1);

function load_env(string $path): void
{
    if (!is_file($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key); $value = trim($value);
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    if ($value === false) return $default;
    return match (strtolower((string)$value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'null', '(null)' => null,
        default => $value,
    };
}

function is_https_request(): bool
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off' && $https !== '0') return true;
    if ((bool)env('TRUST_PROXY', false)) {
        $proto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        return $proto === 'https';
    }
    return false;
}


function secure_cookie_enabled(): bool
{
    $policy = strtolower((string)env('SESSION_SECURE_COOKIE', 'auto'));
    if (in_array($policy, ['false','0','off','no'], true)) return false;
    // Secure cookies are enabled only when the current request is actually HTTPS.
    // This keeps direct first-install HTTP access usable while remaining secure behind TLS.
    return is_https_request();
}

function client_ip(): string
{
    if ((bool)env('TRUST_PROXY', false)) {
        $fwd = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0]);
        if ($fwd !== '' && filter_var($fwd, FILTER_VALIDATE_IP)) return $fwd;
    }
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'CLI');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'CLI';
}

function apply_security_headers(): void
{
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'");
    if (is_https_request() && (string)env('APP_ENV', 'local') === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function redirect(string $url): never { header('Location: ' . $url); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['csrf_token'];
}
function verify_csrf(?string $token = null): void
{
    $token ??= (string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        json_response(['ok' => false, 'message' => 'Sesi formulir kedaluwarsa. Muat ulang halaman.'], 419);
    }
}

function flash(string $type, string $message): void { $_SESSION['flash'][] = compact('type', 'message'); }
function consume_flashes(): array { $x = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return is_array($x) ? $x : []; }

function role_label(string $role): string
{
    return [
        'creator' => 'Creator',
        'finance_manager' => 'Finance Manager',
        'accountant' => 'Accountant',
        'director' => 'Director',
    ][$role] ?? $role;
}

function permissions_for_role(string $role): array
{
    $all = [
        'dashboard.view','financial.view','financial.edit','import.upload','import.publish','import.rollback',
        'reports.export','users.manage','design.manage','system.manage','audit.view','creator.data_lab','ai.use'
    ];
    return match ($role) {
        'creator' => $all,
        'finance_manager' => ['dashboard.view','financial.view','financial.edit','import.upload','import.publish','reports.export','audit.view','ai.use'],
        'accountant' => ['dashboard.view','financial.view','financial.edit','import.upload','reports.export','ai.use'],
        'director' => ['dashboard.view','financial.view','reports.export','ai.use'],
        default => [],
    };
}

function effective_permissions(array $user): array
{
    $base = permissions_for_role((string)($user['role'] ?? ''));
    $overrides = json_decode((string)($user['permission_overrides'] ?? '[]'), true);
    if (!is_array($overrides)) $overrides = [];
    foreach ($overrides as $permission => $allowed) {
        if ($allowed && !in_array($permission, $base, true)) $base[] = $permission;
        if (!$allowed) $base = array_values(array_filter($base, fn($p) => $p !== $permission));
    }
    return array_values(array_unique($base));
}

function can(string $permission, ?array $user = null): bool
{
    $user ??= current_user();
    if (!$user) return false;
    if (($user['role'] ?? '') === 'creator') return true;
    return in_array($permission, effective_permissions($user), true);
}

function require_permission(string $permission): void
{
    if (!can($permission)) json_response(['ok' => false, 'message' => 'Akses ditolak.'], 403);
}

function audit(string $action, string $entity = '', ?string $entityId = null, array $details = []): void
{
    try {
        $u = current_user();
        db()->prepare('INSERT INTO audit_logs (user_id, action, entity, entity_id, details_json, ip_address, created_at) VALUES (?,?,?,?,?,?,NOW())')
            ->execute([$u['id'] ?? null, $action, $entity, $entityId, json_encode($details, JSON_UNESCAPED_UNICODE), client_ip()]);
    } catch (Throwable $e) {
        error_log('Audit failed: ' . $e->getMessage());
    }
}

function normalize_month(mixed $value): int
{
    if (is_numeric($value)) return max(1, min(12, (int)$value));
    $s = strtolower(trim((string)$value));
    $map = [
        'jan'=>1,'januari'=>1,'feb'=>2,'februari'=>2,'mar'=>3,'maret'=>3,'apr'=>4,'april'=>4,
        'mei'=>5,'may'=>5,'jun'=>6,'juni'=>6,'jul'=>7,'juli'=>7,'agu'=>8,'agustus'=>8,'aug'=>8,
        'sep'=>9,'september'=>9,'okt'=>10,'oktober'=>10,'oct'=>10,'nov'=>11,'november'=>11,'des'=>12,'desember'=>12,'dec'=>12
    ];
    return $map[$s] ?? 1;
}
function month_short(int $m): string { return ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][$m] ?? 'Jan'; }
function month_long(int $m): string { return ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][$m] ?? 'Januari'; }
function roman_month(int $m): string { return ['','I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'][$m] ?? 'I'; }
function as_float(mixed $v): float
{
    if (is_int($v) || is_float($v)) return (float)$v;
    $s = trim((string)$v);
    if ($s === '') return 0.0;
    $s = preg_replace('/[^0-9,.-]/', '', $s) ?? '0';
    if (substr_count($s, ',') === 1 && substr_count($s, '.') >= 1) $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
    return (float)$s;
}
function clean_text(mixed $v, int $max = 255): string
{
    $s = trim(preg_replace('/\s+/', ' ', (string)$v) ?? '');
    return mb_substr($s, 0, $max);
}

function confirm_current_password(string $password): bool
{
    $user=current_user();
    return $user && $password!=='' && password_verify($password,(string)$user['password_hash']);
}

function app_version(): string { return (string)env('APP_VERSION', '5.0.0'); }
