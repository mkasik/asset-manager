<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../functions.php';
requireAdmin();

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

if (!verifyCsrf()) jsonResponse(false, 'Invalid security token.');

$pdo = db();

switch ($action) {
    case 'reset_asset_data':
        $confirm = trim((string) ($input['confirm'] ?? ''));
        if ($confirm !== 'RESET') {
            jsonResponse(false, 'Type RESET to confirm.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->exec('DELETE FROM transactions');
            $pdo->exec('DELETE FROM investments');
            $pdo->exec('DELETE FROM categories');
            $pdo->exec('DELETE FROM accounts');
            $pdo->commit();
            jsonResponse(true, 'Asset data reset completed. Users were kept.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsonResponse(false, 'Reset failed.');
        }

    default:
        jsonResponse(false, 'Unknown action.');
}
