<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . '/dashboard.php?err=unauthorized');
        exit;
    }
}

function login(string $username, string $password): bool {
    $stmt = db()->prepare("SELECT * FROM users WHERE username = ? AND status = 1 LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_fullname'] = $user['full_name'] ?? $user['username'];
        $_SESSION['user_role']     = $user['role'];
        return true;
    }
    return false;
}

function logout(): void {
    session_unset();
    session_destroy();
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

function currentUser(): array {
    return [
        'id'       => $_SESSION['user_id']       ?? 0,
        'username' => $_SESSION['user_username']  ?? '',
        'fullname' => $_SESSION['user_fullname']  ?? '',
        'role'     => $_SESSION['user_role']      ?? 'viewer',
    ];
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): bool {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
