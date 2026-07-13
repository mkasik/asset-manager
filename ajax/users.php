<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../functions.php';
requireAdmin();

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

if (!verifyCsrf()) jsonResponse(false, 'Invalid security token.');

$pdo = db();

switch ($action) {
    case 'add':
        $fullName = trim($input['full_name'] ?? '');
        $username = trim($input['username']  ?? '');
        $email    = trim($input['email']     ?? '');
        $password = $input['password'] ?? '';
        $role     = in_array($input['role'] ?? '', ['admin','viewer']) ? $input['role'] : 'viewer';
        $status   = (int) ($input['status'] ?? 1);

        if (!$username || !$email || !$password) jsonResponse(false, 'Username, email and password are required.');
        if (strlen($password) < 6) jsonResponse(false, 'Password must be at least 6 characters.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email address.');

        // Check duplicate
        $dup = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
        $dup->execute([$username, $email]);
        if ($dup->fetch()) jsonResponse(false, 'Username or email already exists.');

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (full_name, username, email, password, role, status) VALUES (?,?,?,?,?,?)")
            ->execute([$fullName, $username, $email, $hash, $role, $status]);
        jsonResponse(true, 'User created successfully.');

    case 'edit':
        $id       = (int) ($input['id'] ?? 0);
        $fullName = trim($input['full_name'] ?? '');
        $username = trim($input['username']  ?? '');
        $email    = trim($input['email']     ?? '');
        $password = $input['password'] ?? '';
        $role     = in_array($input['role'] ?? '', ['admin','viewer']) ? $input['role'] : 'viewer';
        $status   = (int) ($input['status'] ?? 1);

        if (!$id || !$username || !$email) jsonResponse(false, 'Missing required fields.');
        if ($password && strlen($password) < 6) jsonResponse(false, 'Password must be at least 6 characters.');

        // Check duplicate excluding self
        $dup = $pdo->prepare("SELECT id FROM users WHERE (username=? OR email=?) AND id != ?");
        $dup->execute([$username, $email, $id]);
        if ($dup->fetch()) jsonResponse(false, 'Username or email already taken by another user.');

        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET full_name=?, username=?, email=?, password=?, role=?, status=?, updated_at=NOW() WHERE id=?")
                ->execute([$fullName, $username, $email, $hash, $role, $status, $id]);
        } else {
            $pdo->prepare("UPDATE users SET full_name=?, username=?, email=?, role=?, status=?, updated_at=NOW() WHERE id=?")
                ->execute([$fullName, $username, $email, $role, $status, $id]);
        }
        jsonResponse(true, 'User updated.');

    case 'delete':
        $id = (int) ($input['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid ID.');
        if ($id == currentUser()['id']) jsonResponse(false, 'Cannot delete your own account.');
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        jsonResponse(true, 'User deleted.');

    default:
        jsonResponse(false, 'Unknown action.');
}
