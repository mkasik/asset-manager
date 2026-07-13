<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../functions.php';
requireLogin();

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

if (!verifyCsrf()) jsonResponse(false, 'Invalid security token.');

$pdo = db();
ensureAccountNomineeColumn($pdo);

switch ($action) {
    // ── Add account ─────────────────────────────────────────────
    case 'add':
        requireAdmin();
        $name    = trim($input['name'] ?? '');
        $type    = $input['type'] ?? 'bank';
        $balance = max(0, (float) ($input['balance'] ?? 0));
        $desc    = trim($input['description'] ?? '');
        $nominee = trim($input['nominee_name'] ?? '');

        if (!$name) jsonResponse(false, 'Account name is required.');
        $allowed = ['bank','cash','mobile_banking','crypto','receivable','other'];
        if (!in_array($type, $allowed)) jsonResponse(false, 'Invalid account type.');
        if ($type !== 'bank') $nominee = '';

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO accounts (name, type, nominee_name, balance, description) VALUES (?,?,?,?,?)");
            $stmt->execute([$name, $type, $nominee, $balance, $desc]);
            $acctId = $pdo->lastInsertId();

            if ($balance > 0) {
                $pdo->prepare("INSERT INTO transactions (type, account_id, amount, balance_after, description, created_by)
                               VALUES ('deposit', ?, ?, ?, 'Opening balance', ?)")
                    ->execute([$acctId, $balance, $balance, currentUser()['id']]);
            }
            $pdo->commit();
            jsonResponse(true, 'Account added successfully.');
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Failed to add account.');
        }

    // ── Edit account ─────────────────────────────────────────────
    case 'edit':
        requireAdmin();
        $id   = (int) ($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $type = $input['type'] ?? '';
        $desc = trim($input['description'] ?? '');
        $nominee = trim($input['nominee_name'] ?? '');
        if ($type !== 'bank') $nominee = '';

        if (!$id || !$name) jsonResponse(false, 'Missing required fields.');
        $pdo->prepare("UPDATE accounts SET name=?, type=?, nominee_name=?, description=?, updated_at=NOW() WHERE id=?")
            ->execute([$name, $type, $nominee, $desc, $id]);
        jsonResponse(true, 'Account updated.');

    // ── Delete account ───────────────────────────────────────────
    case 'delete':
        requireAdmin();
        $id = (int) ($input['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid ID.');
        $used = $pdo->prepare("SELECT COUNT(*) FROM investments WHERE source_account_id=? OR profit_account_id=?");
        $used->execute([$id, $id]);
        if ($used->fetchColumn() > 0) {
            jsonResponse(false, 'Cannot delete: account is used in investments. Use Maintenance reset for test data cleanup.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM transactions WHERE account_id=?")->execute([$id]);
            $deleted = $pdo->prepare("DELETE FROM accounts WHERE id=?");
            $deleted->execute([$id]);
            $pdo->commit();
            if ($deleted->rowCount() < 1) jsonResponse(false, 'Account not found.');
            jsonResponse(true, 'Account and related transactions deleted.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(false, 'Account deletion failed.');
        }

    // ── Add money (deposit) ───────────────────────────────────────
    case 'add_money':
        requireAdmin();
        $acctId = (int) ($input['account_id'] ?? 0);
        $amount = (float) ($input['amount'] ?? 0);
        $desc   = trim($input['description'] ?? 'Manual deposit');

        if (!$acctId || $amount <= 0) jsonResponse(false, 'Invalid account or amount.');

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE accounts SET balance = balance + ?, updated_at=NOW() WHERE id=?")->execute([$amount, $acctId]);
            $newBal = (float) $pdo->prepare("SELECT balance FROM accounts WHERE id=?")->execute([$acctId]) ? 0 : 0;
            $row = $pdo->prepare("SELECT balance FROM accounts WHERE id=?");
            $row->execute([$acctId]);
            $newBal = (float) $row->fetchColumn();
            $pdo->prepare("INSERT INTO transactions (type, account_id, amount, balance_after, description, created_by)
                           VALUES ('deposit', ?, ?, ?, ?, ?)")
                ->execute([$acctId, $amount, $newBal, $desc, currentUser()['id']]);
            $pdo->commit();
            jsonResponse(true, 'Money added successfully. New balance: ' . number_format($newBal, 2));
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Transaction failed.');
        }

    // ── Transfer balance between accounts ─────────────────────────
    case 'transfer':
        requireAdmin();
        $fromId = (int) ($input['from_account_id'] ?? 0);
        $toId   = (int) ($input['to_account_id'] ?? 0);
        $amount = (float) ($input['amount'] ?? 0);
        $desc   = trim($input['description'] ?? 'Balance transfer');

        if (!$fromId || !$toId || $amount <= 0) jsonResponse(false, 'Please select both accounts and enter a valid amount.');
        if ($fromId === $toId) jsonResponse(false, 'Source and destination accounts must be different.');

        $pdo->beginTransaction();
        try {
            $fromStmt = $pdo->prepare("SELECT id, name, balance FROM accounts WHERE id=? FOR UPDATE");
            $fromStmt->execute([$fromId]);
            $from = $fromStmt->fetch();

            $toStmt = $pdo->prepare("SELECT id, name, balance FROM accounts WHERE id=? FOR UPDATE");
            $toStmt->execute([$toId]);
            $to = $toStmt->fetch();

            if (!$from || !$to) {
                $pdo->rollBack();
                jsonResponse(false, 'Selected account was not found.');
            }
            if ((float)$from['balance'] < $amount) {
                $pdo->rollBack();
                jsonResponse(false, 'Insufficient balance in source account. Available: ৳ ' . number_format((float)$from['balance'], 2));
            }

            $fromBal = (float)$from['balance'] - $amount;
            $toBal   = (float)$to['balance'] + $amount;
            $noteOut = $desc ?: 'Transfer to ' . $to['name'];
            $noteIn  = $desc ?: 'Transfer from ' . $from['name'];

            $pdo->prepare("UPDATE accounts SET balance=?, updated_at=NOW() WHERE id=?")->execute([$fromBal, $fromId]);
            $pdo->prepare("INSERT INTO transactions (type, account_id, amount, balance_after, description, created_by)
                           VALUES ('transfer_out', ?, ?, ?, ?, ?)")
                ->execute([$fromId, -$amount, $fromBal, $noteOut, currentUser()['id']]);

            $pdo->prepare("UPDATE accounts SET balance=?, updated_at=NOW() WHERE id=?")->execute([$toBal, $toId]);
            $pdo->prepare("INSERT INTO transactions (type, account_id, amount, balance_after, description, created_by)
                           VALUES ('transfer_in', ?, ?, ?, ?, ?)")
                ->execute([$toId, $amount, $toBal, $noteIn, currentUser()['id']]);

            $pdo->commit();
            jsonResponse(true, 'Balance transferred successfully.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(false, 'Transfer failed.');
        }

    default:
        jsonResponse(false, 'Unknown action.');
}
