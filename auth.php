<?php
// auth.php – session-based password protection shared by all pages.
declare(strict_types=1);

function authConfig(): array {
    static $config = null;
    if ($config === null) {
        $file = __DIR__ . '/config.php';
        $config = file_exists($file) ? (require $file) : [];
    }
    return is_array($config) ? $config : [];
}

function authPassword(): string {
    return (string)(authConfig()['password'] ?? '');
}

// Favicon link target: a user-defined file path from config.php
// ('favicon' key) or the built-in inline SVG as fallback.
function faviconHref(): string {
    $favicon = trim((string)(authConfig()['favicon'] ?? ''));
    return $favicon !== '' ? $favicon
        : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%8E%81%3C/text%3E%3C/svg%3E";
}

function startSession(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function isLoggedIn(): bool {
    startSession();
    return !empty($_SESSION['authenticated']);
}

// Redirect to login.php if the visitor has not entered the password.
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Check the submitted password. Protects against timing and hash attacks
// via password_verify on a stored hash if PASSWORD_HASH is configured.
function verifyPassword(string $submitted): bool {
    $stored = authPassword();
    if ($stored === '') {
        return false;
    }
    $hash = (string)(authConfig()['password_hash'] ?? '');
    if ($hash !== '') {
        return password_verify($submitted, $hash);
    }
    return hash_equals($stored, $submitted);
}

function login(): void {
    startSession();
    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
}

function logout(): void {
    startSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
