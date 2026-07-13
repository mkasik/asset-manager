<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../functions.php';
requireLogin();

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

if (!verifyCsrf()) jsonResponse(false, 'Invalid security token.');

$pdo = db();

// ── Helper: record a transaction ─────────────────────────────────
function recordTx(PDO $pdo, string $type, int $acctId, float $amount, string $desc, bool $isAuto = false, ?int $invId = null): void {
    $pdo->prepare("UPDATE accounts SET balance = balance + ?, updated_at=NOW() WHERE id=?")->execute([$amount, $acctId]);
    $bal = (float) $pdo->query("SELECT balance FROM accounts WHERE id=$acctId")->fetchColumn();
    $pdo->prepare("INSERT INTO transactions (type, account_id, amount, balance_after, investment_id, description, is_auto, created_by)
                   VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$type, $acctId, $amount, $bal, $invId, $desc, $isAuto ? 1 : 0, currentUser()['id']]);
}

switch ($action) {
    // ── Add new investment ────────────────────────────────────────
    case 'add':
        requireAdmin();
        $invName   = trim($input['name'] ?? '');
        $catId     = (int) ($input['category_id'] ?? 0);
        $srcAcctId = (int) ($input['source_account_id'] ?? 0);
        $amount    = (float) ($input['amount'] ?? 0);
        $profType  = $input['profit_type'] ?? 'percent';
        $profRate  = (float) ($input['profit_rate'] ?? 0);
        $expProfit = (float) ($input['expected_profit'] ?? 0);
        $profAcctId = (int) ($input['profit_account_id'] ?? 0) ?: null;
        $startDate  = $input['start_date'] ?? date('Y-m-d');
        $matDate    = $input['maturity_date'] ?? null ?: null;
        $notes      = trim($input['notes'] ?? '');

        if (!$invName) jsonResponse(false, 'Investment title is required.');
        if (!$catId || !$srcAcctId || $amount <= 0) jsonResponse(false, 'Category, source account and amount are required.');

        // Check balance
        $row = $pdo->prepare("SELECT balance FROM accounts WHERE id=?");
        $row->execute([$srcAcctId]);
        $balance = (float) $row->fetchColumn();
        if ($balance < $amount) jsonResponse(false, 'Insufficient balance in source account. Available: ৳ ' . number_format($balance, 2));

        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO investments
                (name, category_id, source_account_id, profit_account_id, amount, profit_type, profit_rate,
                 expected_profit, start_date, maturity_date, status, notes, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$invName, $catId, $srcAcctId, $profAcctId, $amount, $profType, $profRate,
                           $expProfit, $startDate, $matDate, 'active', $notes, currentUser()['id']]);
            $invId = (int) $pdo->lastInsertId();

            // Deduct from source account
            $cat = $pdo->prepare("SELECT name FROM categories WHERE id=?");
            $cat->execute([$catId]);
            $catName = $cat->fetchColumn();
            recordTx($pdo, 'investment', $srcAcctId, -$amount, "Investment in $catName", false, $invId);

            $pdo->commit();
            jsonResponse(true, 'Investment created successfully.');
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Failed to create investment: ' . $e->getMessage());
        }

    // ── Edit investment (active only) ─────────────────────────────
    case 'edit':
        requireAdmin();
        $id        = (int) ($input['id'] ?? 0);
        $invName   = trim($input['name'] ?? '');
        $profType  = $input['profit_type']  ?? 'percent';
        $profRate  = (float) ($input['profit_rate'] ?? 0);
        $expProfit = (float) ($input['expected_profit'] ?? 0);
        $profAcctId = (int) ($input['profit_account_id'] ?? 0) ?: null;
        $matDate   = $input['maturity_date'] ?? null ?: null;
        $notes     = trim($input['notes'] ?? '');

        if (!$id) jsonResponse(false, 'Invalid investment ID.');
        if (!$invName) jsonResponse(false, 'Investment title is required.');
        $inv = $pdo->prepare("SELECT * FROM investments WHERE id=? AND status='active'");
        $inv->execute([$id]);
        if (!$inv->fetch()) jsonResponse(false, 'Investment not found or not editable.');

        $pdo->prepare("UPDATE investments SET name=?, profit_type=?, profit_rate=?, expected_profit=?,
                       profit_account_id=?, maturity_date=?, notes=?, updated_at=NOW() WHERE id=?")
            ->execute([$invName, $profType, $profRate, $expProfit, $profAcctId, $matDate, $notes, $id]);
        jsonResponse(true, 'Investment updated.');

    // ── Delete investment ─────────────────────────────────────────
    case 'delete':
        requireAdmin();
        $id = (int) ($input['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid ID.');

        $stmt = $pdo->prepare("SELECT * FROM investments WHERE id=?");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) jsonResponse(false, 'Investment not found.');
        if (!in_array($inv['status'], ['active'])) jsonResponse(false, 'Only active investments can be deleted.');

        $pdo->beginTransaction();
        try {
            // Reverse the account deduction
            $pdo->prepare("UPDATE accounts SET balance = balance + ?, updated_at=NOW() WHERE id=?")
                ->execute([$inv['amount'], $inv['source_account_id']]);
            // Delete related transactions
            $pdo->prepare("DELETE FROM transactions WHERE investment_id=? AND type='investment'")->execute([$id]);
            $pdo->prepare("DELETE FROM investments WHERE id=?")->execute([$id]);
            $pdo->commit();
            jsonResponse(true, 'Investment deleted and balance restored.');
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Deletion failed.');
        }

    // ── Renew investment ──────────────────────────────────────────
    case 'renew':
        requireAdmin();
        $id           = (int) ($input['id'] ?? 0);
        $actualProfit = (float) ($input['actual_profit'] ?? 0);
        $profAcctId   = (int) ($input['profit_account_id'] ?? 0);
        $newAmount    = (float) ($input['new_amount'] ?? 0);
        $newMatDate   = $input['new_maturity_date'] ?? null ?: null;
        $newExpectedProfit = isset($input["new_expected_profit"]) && $input["new_expected_profit"] !== "" ? (float)$input["new_expected_profit"] : null;
        $notes        = trim($input['notes'] ?? '');

        if (!$id) jsonResponse(false, 'Invalid investment ID.');

        $stmt = $pdo->prepare("SELECT * FROM investments WHERE id=? AND status='active'");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) jsonResponse(false, 'Investment not found or not active.');
        if (!$profAcctId) jsonResponse(false, 'Please select a profit account.');

        $pdo->beginTransaction();
        try {
            // Mark old as renewed
            $pdo->prepare("UPDATE investments SET status='renewed', actual_profit=?, updated_at=NOW() WHERE id=?")
                ->execute([$actualProfit, $id]);

            // Credit profit to profit account
            if ($actualProfit > 0) {
                $cat = $pdo->prepare("SELECT name FROM categories WHERE id=?");
                $cat->execute([$inv['category_id']]);
                $catName = $cat->fetchColumn();
                recordTx($pdo, 'profit', $profAcctId, $actualProfit,
                    "Profit from $catName investment (Renewal)", true, $id);
            }

            // Create new investment
            $renewAmount = $newAmount > 0 ? $newAmount : (float) $inv['amount'];
            $renewRate = (float) $inv['profit_rate'];
            $renewExpectedProfit = $newExpectedProfit ?? ($actualProfit > 0 ? $actualProfit : (float) ($inv['expected_profit'] ?? 0));

            $pdo->prepare("INSERT INTO investments
                (name, category_id, source_account_id, profit_account_id, amount, profit_type, profit_rate,
                 expected_profit, start_date, maturity_date, status, notes, parent_id, created_by)
                VALUES (?,?,?,?,?,?,?,?,CURDATE(),?,?,?,?,?)")
                ->execute([
                    $inv['name'] ?: 'Renewed investment #' . $id,
                    $inv['category_id'], $inv['source_account_id'],
                    $profAcctId ?: $inv['profit_account_id'],
                    $renewAmount,
                    $inv['profit_type'],
                    $renewRate,
                    $renewExpectedProfit, $newMatDate, 'active',
                    $notes ?: 'Renewed from investment #' . $id,
                    $id, currentUser()['id']
                ]);

            $pdo->commit();
            jsonResponse(true, 'Investment renewed. Profit of ৳ ' . number_format($actualProfit, 2) . ' credited.');
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Renewal failed: ' . $e->getMessage());
        }

    // ── Withdraw investment ───────────────────────────────────────
    case 'withdraw':
        requireAdmin();
        $id            = (int) ($input['id'] ?? 0);
        $actualProfit  = (float) ($input['actual_profit'] ?? 0);
        $returnAcctId  = (int) ($input['return_account_id'] ?? 0);
        $profAcctId    = (int) ($input['profit_account_id'] ?? 0);
        $notes         = trim($input['notes'] ?? '');

        if (!$id || !$returnAcctId) jsonResponse(false, 'Investment ID and return account are required.');

        $stmt = $pdo->prepare("SELECT * FROM investments WHERE id=? AND status='active'");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) jsonResponse(false, 'Investment not found or not active.');

        $pdo->beginTransaction();
        try {
            // Mark withdrawn
            $pdo->prepare("UPDATE investments SET status='withdrawn', actual_profit=?, updated_at=NOW() WHERE id=?")
                ->execute([$actualProfit, $id]);

            $cat = $pdo->prepare("SELECT name FROM categories WHERE id=?");
            $cat->execute([$inv['category_id']]);
            $catName = $cat->fetchColumn();

            // Return principal
            recordTx($pdo, 'deposit', $returnAcctId, (float)$inv['amount'],
                "Principal returned: $catName withdrawal", true, $id);

            // Credit profit (if any)
            if ($actualProfit > 0 && $profAcctId) {
                recordTx($pdo, 'profit', $profAcctId, $actualProfit,
                    "Profit from $catName withdrawal", true, $id);
            }

            $pdo->commit();
            jsonResponse(true, 'Investment withdrawn. Principal ৳ ' . number_format($inv['amount'], 2) . ' returned.');
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Withdrawal failed: ' . $e->getMessage());
        }

    // ── View investment details ───────────────────────────────────
    case 'view':
        $id = (int) ($input['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid ID.');

        $stmt = $pdo->prepare("
            SELECT i.*, c.name AS cat_name, a.name AS src_acct,
                   pa.name AS profit_acct, pi.amount AS parent_amount
            FROM investments i
            JOIN categories c  ON i.category_id = c.id
            JOIN accounts a    ON i.source_account_id = a.id
            LEFT JOIN accounts pa ON i.profit_account_id = pa.id
            LEFT JOIN investments pi ON i.parent_id = pi.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) jsonResponse(false, 'Not found.');

        // Renewal history
        $renewals = $pdo->prepare("
            SELECT i2.*, c.name AS cat_name FROM investments i2
            JOIN categories c ON i2.category_id = c.id
            WHERE i2.parent_id = ?
            ORDER BY i2.created_at
        ");
        $renewals->execute([$id]);
        $renewalList = $renewals->fetchAll();

        // Transactions
        $txStmt = $pdo->prepare("
            SELECT t.*, a.name AS acct_name FROM transactions t
            JOIN accounts a ON t.account_id = a.id
            WHERE t.investment_id = ?
            ORDER BY t.created_at DESC
        ");
        $txStmt->execute([$id]);
        $txList = $txStmt->fetchAll();

        ob_start();
        ?>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="detail-row"><span class="label">Category</span><span class="value"><?= htmlspecialchars($inv['cat_name']) ?></span></div>
                <div class="detail-row"><span class="label">Source Account</span><span class="value"><?= htmlspecialchars($inv['src_acct']) ?></span></div>
                <div class="detail-row"><span class="label">Profit Account</span><span class="value"><?= htmlspecialchars($inv['profit_acct'] ?? '—') ?></span></div>
                <div class="detail-row"><span class="label">Amount</span><span class="value amount-neutral">৳ <?= number_format($inv['amount'],2) ?></span></div>
                <div class="detail-row"><span class="label">Profit Rate</span><span class="value"><?= $inv['profit_rate'] > 0 ? $inv['profit_rate'].'% ('.$inv['profit_type'].')' : '—' ?></span></div>
            </div>
            <div class="col-md-6">
                <div class="detail-row"><span class="label">Start Date</span><span class="value"><?= date('d M Y', strtotime($inv['start_date'])) ?></span></div>
                <div class="detail-row"><span class="label">Maturity Date</span><span class="value"><?= $inv['maturity_date'] ? date('d M Y', strtotime($inv['maturity_date'])) : '—' ?></span></div>
                <div class="detail-row"><span class="label">Expected Profit</span><span class="value">৳ <?= number_format($inv['expected_profit'],2) ?></span></div>
                <div class="detail-row"><span class="label">Actual Profit</span><span class="value amount-pos">৳ <?= number_format($inv['actual_profit'],2) ?></span></div>
                <div class="detail-row"><span class="label">Status</span>
                    <span class="badge status-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span></div>
            </div>
        </div>
        <?php if ($inv['notes']): ?><div class="alert alert-light py-2 fs-13"><strong>Notes:</strong> <?= htmlspecialchars($inv['notes']) ?></div><?php endif; ?>

        <?php if ($renewalList): ?>
        <h6 class="fw-700 mb-2 mt-3">Renewal History (<?= count($renewalList) ?>)</h6>
        <div class="table-responsive"><table class="table table-sm">
            <thead><tr><th>Amount</th><th>Start</th><th>Maturity</th><th>Status</th><th>Profit</th></tr></thead>
            <tbody>
            <?php foreach ($renewalList as $r): ?>
            <tr>
                <td>৳ <?= number_format($r['amount'],2) ?></td>
                <td><?= date('d M Y', strtotime($r['start_date'])) ?></td>
                <td><?= $r['maturity_date'] ? date('d M Y', strtotime($r['maturity_date'])) : '—' ?></td>
                <td><span class="badge status-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                <td class="amount-pos">৳ <?= number_format($r['actual_profit'],2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>

        <?php if ($txList): ?>
        <h6 class="fw-700 mb-2 mt-3">Transactions</h6>
        <div class="table-responsive"><table class="table table-sm">
            <thead><tr><th>Type</th><th>Account</th><th>Amount</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($txList as $tx): ?>
            <tr>
                <td><span class="badge tx-<?= $tx['type'] ?>"><?= ucfirst(str_replace('_',' ',$tx['type'])) ?></span></td>
                <td><?= htmlspecialchars($tx['acct_name']) ?></td>
                <td class="<?= $tx['amount']>=0?'amount-pos':'amount-neg' ?>">৳ <?= number_format(abs($tx['amount']),2) ?></td>
                <td class="text-muted fs-13"><?= date('d M Y', strtotime($tx['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
        <?php
        $html = ob_get_clean();
        jsonResponse(true, '', ['html' => $html]);

    default:
        jsonResponse(false, 'Unknown action.');
}
