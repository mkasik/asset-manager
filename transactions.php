<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Transactions';
$pdo = db();

$filterType    = $_GET['type']    ?? 'all';
$filterAccount = $_GET['account'] ?? 'all';
$page          = max(1, (int) ($_GET['p'] ?? 1));
$perPage       = 25;
$offset        = ($page - 1) * $perPage;

$conditions = [];
$params     = [];
if ($filterType !== 'all') { $conditions[] = 't.type = ?'; $params[] = $filterType; }
if ($filterAccount !== 'all') { $conditions[] = 't.account_id = ?'; $params[] = $filterAccount; }

$whereSQL = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM transactions t $whereSQL");
$total->execute($params);
$totalRows = (int) $total->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

$stmt = $pdo->prepare("
    SELECT t.*, a.name AS acct_name
    FROM transactions t
    JOIN accounts a ON t.account_id = a.id
    $whereSQL
    ORDER BY t.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$accounts = $pdo->query("SELECT id, name, type, balance FROM accounts ORDER BY name")->fetchAll();
$types    = ['deposit','withdrawal','investment','profit','transfer_in','transfer_out','renewal'];

// Summary stats
$sumDeposit   = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='deposit'")->fetchColumn();
$sumWithdraw  = (float) $pdo->query("SELECT COALESCE(SUM(ABS(amount)),0) FROM transactions WHERE type='withdrawal'")->fetchColumn();
$sumInvested  = (float) $pdo->query("SELECT COALESCE(SUM(ABS(amount)),0) FROM transactions WHERE type='investment'")->fetchColumn();
$sumProfit    = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='profit'")->fetchColumn();

include __DIR__ . '/includes/layout_header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card sc-green">
        <div class="sc-label">Total Deposits</div><div class="sc-value"><?= formatMoney($sumDeposit) ?></div>
        <i class="fas fa-arrow-down sc-icon"></i>
    </div></div>
    <div class="col-6 col-md-3"><div class="stat-card sc-red">
        <div class="sc-label">Total Withdrawals</div><div class="sc-value"><?= formatMoney($sumWithdraw) ?></div>
        <i class="fas fa-arrow-up sc-icon"></i>
    </div></div>
    <div class="col-6 col-md-3"><div class="stat-card sc-blue">
        <div class="sc-label">Total Invested</div><div class="sc-value"><?= formatMoney($sumInvested) ?></div>
        <i class="fas fa-chart-line sc-icon"></i>
    </div></div>
    <div class="col-6 col-md-3"><div class="stat-card sc-purple">
        <div class="sc-label">Total Profit Credited</div><div class="sc-value"><?= formatMoney($sumProfit) ?></div>
        <i class="fas fa-coins sc-icon"></i>
    </div></div>
</div>

<div class="card">
    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-center w-100">
            <select name="type" class="form-select" style="max-width:160px">
                <option value="all">All Types</option>
                <?php foreach ($types as $t): ?>
                <option value="<?= $t ?>" <?= $filterType===$t ? 'selected' : '' ?>>
                    <?= ucfirst(str_replace('_',' ',$t)) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="account" class="form-select" style="max-width:180px">
                <option value="all">All Accounts</option>
                <?php foreach ($accounts as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $filterAccount==(string)$a['id'] ? 'selected' : '' ?>>
                    <?= sanitize($a['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <?php if ($filterType!=='all' || $filterAccount!=='all'): ?>
            <a href="transactions.php" class="btn btn-outline-secondary btn-sm">Clear</a>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
            <button type="button" class="btn btn-success btn-sm" onclick="openTransferModal()">
                <i class="fas fa-exchange-alt me-1"></i> Transfer
            </button>
            <?php endif; ?>
            <span class="ms-auto text-muted fs-13"><?= $totalRows ?> record(s)</span>
        </form>
    </div>

    <?php if ($transactions): ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th>#</th><th>Account</th><th>Type</th><th>Amount</th>
                <th>Balance After</th><th>Description</th><th>Date & Time</th>
            </tr></thead>
            <tbody>
            <?php foreach ($transactions as $idx => $tx): ?>
            <tr>
                <td class="text-muted fs-13"><?= $offset + $idx + 1 ?></td>
                <td class="fw-600"><?= sanitize($tx['acct_name']) ?></td>
                <td><span class="badge tx-<?= $tx['type'] ?>"><?= ucfirst(str_replace('_',' ',$tx['type'])) ?></span>
                    <?php if ($tx['is_auto']): ?><span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size:10px">Auto</span><?php endif; ?>
                </td>
                <td class="<?= $tx['amount'] >= 0 ? 'amount-pos' : 'amount-neg' ?>">
                    <?= ($tx['amount'] >= 0 ? '+' : '') . formatMoney($tx['amount']) ?>
                </td>
                <td class="fw-600"><?= formatMoney($tx['balance_after'] ?? 0) ?></td>
                <td class="text-muted fs-13" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?= sanitize($tx['description'] ?? '') ?>
                </td>
                <td class="text-muted fs-13 text-nowrap"><?= formatDateTime($tx['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
        <span class="text-muted fs-13">Page <?= $page ?> of <?= $totalPages ?></span>
        <div class="d-flex gap-1">
            <?php if ($page > 1): ?>
            <a href="?type=<?= $filterType ?>&account=<?= $filterAccount ?>&p=<?= $page-1 ?>" class="btn btn-sm btn-outline-secondary">Prev</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?type=<?= $filterType ?>&account=<?= $filterAccount ?>&p=<?= $page+1 ?>" class="btn btn-sm btn-outline-secondary">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state"><div class="es-icon"><i class="fas fa-exchange-alt"></i></div>
    <p>No transactions found for selected filters.</p></div>
    <?php endif; ?>
</div>

<?php if (isAdmin()): ?>
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Transfer Balance</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="transferForm" onsubmit="submitTransfer(event)">
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">From Account <span class="text-danger">*</span></label>
                        <select name="from_account_id" id="transferFrom" class="form-select" required>
                            <option value="">Select source account...</option>
                            <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['id'] ?>" data-balance="<?= $a['balance'] ?>"><?= sanitize($a['name']) ?> (<?= formatMoney($a['balance']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" id="transferBalanceHint"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">To Account <span class="text-danger">*</span></label>
                        <select name="to_account_id" id="transferTo" class="form-select" required>
                            <option value="">Select destination account...</option>
                            <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= sanitize($a['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" min="0.01" step="0.01" placeholder="0.00" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Note</label>
                        <input type="text" name="description" class="form-control" placeholder="Bank to cash, bank to bank, etc.">
                    </div>
                </div>
                <div id="transferAlert" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success btn-sm">Transfer</button>
            </div>
        </form>
    </div></div>
</div>
<?php endif; ?>

<?php
$extraJs = <<<'JSEOF'
<script>
function openTransferModal() {
    const form = document.getElementById("transferForm");
    if (!form) return;
    form.reset();
    document.getElementById("transferAlert").innerHTML = "";
    document.getElementById("transferBalanceHint").textContent = "";
    bootstrap.Modal.getOrCreateInstance(document.getElementById("transferModal")).show();
}

document.getElementById("transferFrom")?.addEventListener("change", function () {
    const opt = this.options[this.selectedIndex];
    const bal = opt?.dataset?.balance;
    document.getElementById("transferBalanceHint").textContent = bal ? "Available: ৳ " + parseFloat(bal).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : "";
});

async function submitTransfer(e) {
    e.preventDefault();
    const form = e.target;
    const data = formData(form);
    const btn = form.querySelector("[type=submit]");
    const alertBox = document.getElementById("transferAlert");
    btn.disabled = true;
    try {
        const res = await apiPost("/ajax/accounts.php?action=transfer", data);
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById("transferModal"))?.hide();
            showToast(res.message, "success");
            setTimeout(reloadPage, 500);
        } else {
            alertBox.innerHTML = "<div class=\"alert alert-danger py-2\">" + res.message + "</div>";
        }
    } catch (err) {
        alertBox.innerHTML = "<div class=\"alert alert-danger py-2\">Transfer request failed. Please try again.</div>";
    } finally {
        btn.disabled = false;
    }
}
</script>
JSEOF;
?>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
