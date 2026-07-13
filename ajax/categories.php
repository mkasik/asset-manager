<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../functions.php';
requireLogin();

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

if (!verifyCsrf()) jsonResponse(false, 'Invalid security token.');

$pdo = db();

switch ($action) {
    case 'add':
        requireAdmin();
        $name = trim($input['name'] ?? '');
        $desc = trim($input['description'] ?? '');
        if (!$name) jsonResponse(false, 'Category name is required.');
        // Check duplicate
        $dup = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $dup->execute([$name]);
        if ($dup->fetch()) jsonResponse(false, 'Category "' . htmlspecialchars($name) . '" already exists.');
        $pdo->prepare("INSERT INTO categories (name, description) VALUES (?,?)")->execute([$name, $desc]);
        jsonResponse(true, 'Category added successfully.');

    case 'edit':
        requireAdmin();
        $id   = (int) ($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $desc = trim($input['description'] ?? '');
        if (!$id || !$name) jsonResponse(false, 'Missing required fields.');
        // Check duplicate (exclude self)
        $dup = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
        $dup->execute([$name, $id]);
        if ($dup->fetch()) jsonResponse(false, 'Category name already exists.');
        $pdo->prepare("UPDATE categories SET name=?, description=? WHERE id=?")->execute([$name, $desc, $id]);
        jsonResponse(true, 'Category updated.');

    case 'delete':
        requireAdmin();
        $id = (int) ($input['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid ID.');
        // Check in use
        $used = $pdo->prepare("SELECT COUNT(*) FROM investments WHERE category_id = ?");
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) jsonResponse(false, 'Cannot delete: category is used in investments.');
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        jsonResponse(true, 'Category deleted.');

    default:
        jsonResponse(false, 'Unknown action.');
}
