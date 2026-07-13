<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Accounts';
$pdo = db();
ensureAccountNomineeColumn($pdo);

$accounts     = $pdo->query("SELECT * FROM accounts ORDER BY name")->fetchAll();
$totalBalance = array_sum(array_column($accounts, 'balance'));
$selectedTxAccount = isset($_GET['account_id']) ? (int) $_GET['account_id'] : 0;

include __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
    <div>
        <h2>Accounts</h2>
        <div class="sub">Total Balance: <strong class="text-success"><?= formatMoney($totalBalance) ?></strong></div>
    </div>
    <?php if (isAdmin()): ?>
    <button class="btn btn-primary btn-sm" onclick="openAccountModal()">
        <i class="fas fa-plus me-1"></i> Add Account
    </button>
    <?php endif; ?>
</div>

<!-- Account Cards -->
<div class="row g-3 mb-4">
<?php foreach ($accounts as $acc): ?>
<div class="col-md-4 col-sm-6" id="acct-card-<?= $acc['id'] ?>">
    <div class="card account-card h-100">
        <div class="card-body">
            <div class="d-flex align-items-start gap-3 mb-3">
                <div class="acct-icon <?= $acc['type'] ?>">
                    <i class="fas <?= accountTypeIcon($acc['type']) ?>"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="acct-name"><?= sanitize($acc['name']) ?></div>
                    <div class="acct-type"><?= accountTypeLabel($acc['type']) ?></div>
                </div>
                <button type="button" class="btn-act btn-view" onclick="viewAccount(<?= $acc['id'] ?>)" title="View"><i class="fas fa-eye"></i></button>
            </div>
            <div class="acct-balance mb-2"><?= formatMoney($acc['balance']) ?></div>
            <?php if ($acc['description']): ?>
            <p class="text-muted mb-3" style="font-size:12px"><?= sanitize($acc['description']) ?></p>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
            <div class="d-flex gap-2 mt-auto pt-2 border-top">
                <button class="btn btn-sm btn-outline-success flex-grow-1" onclick="openAddMoney(<?= $acc['id'] ?>, '<?= sanitize($acc['name']) ?>')">
                    <i class="fas fa-plus me-1"></i> Add Money
                </button>
                <button class="btn-act btn-edit" onclick="openAccountModal(<?= $acc['id'] ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                <button class="btn-act btn-delete" onclick="deleteAccount(<?= $acc['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php if (!$accounts): ?>
<div class="col-12"><div class="card"><div class="empty-state">
    <div class="es-icon"><i class="fas fa-wallet"></i></div>
    <p>No accounts yet. Add your first account!</p>
</div></div></div>
<?php endif; ?>
</div>

<!-- Recent Transactions -->
<div class="card">
    <div class="card-header flex-wrap gap-2">
        <h5>Transactions</h5>
        <div class="d-flex gap-2 flex-wrap align-items-center ms-auto">
            <form method="get" class="d-flex gap-2 flex-wrap align-items-center">
                <select name="account_id" class="form-select form-select-sm" style="width:auto;min-width:190px" onchange="this.form.submit()">
                    <option value="0">All Accounts</option>
                    <?php foreach ($accounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>" <?= (int) $acc['id'] === $selectedTxAccount ? 'selected' : '' ?>><?= sanitize($acc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($selectedTxAccount > 0): ?>
                <a href="accounts.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </form>
            <a href="<?= SITE_URL ?>/transactions.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
    </div>
    <?php
    if ($selectedTxAccount > 0) {
        $txStmt = $pdo->prepare("
            SELECT t.*, a.name AS acct_name FROM transactions t
            JOIN accounts a ON t.account_id = a.id
            WHERE t.account_id = ?
            ORDER BY t.created_at DESC
        ");
        $txStmt->execute([$selectedTxAccount]);
        $txList = $txStmt->fetchAll();
    } else {
        $txList = $pdo->query("
            SELECT t.*, a.name AS acct_name FROM transactions t
            JOIN accounts a ON t.account_id = a.id
            ORDER BY t.created_at DESC
        ")->fetchAll();
    }
    ?>
    <?php if ($txList): ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>#</th><th>Account</th><th>Type</th><th>Amount</th><th>Balance After</th><th>Description</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($txList as $idx => $tx): ?>
            <tr>
                <td class="text-muted fs-13"><?= $idx + 1 ?></td>
                <td><?= sanitize($tx['acct_name']) ?></td>
                <td><span class="badge tx-<?= $tx['type'] ?>"><?= ucfirst(str_replace('_',' ',$tx['type'])) ?></span></td>
                <td class="<?= $tx['amount'] >= 0 ? 'amount-pos' : 'amount-neg' ?>"><?= ($tx['amount'] >= 0 ? '+' : '') . formatMoney($tx['amount']) ?></td>
                <td class="fw-600"><?= formatMoney($tx['balance_after'] ?? 0) ?></td>
                <td class="text-muted fs-13"><?= sanitize($tx['description'] ?? '') ?></td>
                <td class="text-muted fs-13"><?= formatDateTime($tx['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="es-icon"><i class="fas fa-exchange-alt"></i></div><p>No transactions yet</p></div>
    <?php endif; ?>
</div>

<!-- ══ Account Modal ══ -->
<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="accountModalTitle">Add Account</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="accountForm" onsubmit="submitAccountForm(event)">
            <div class="modal-body">
                <input type="hidden" name="id" id="acctId">
                <div class="mb-3">
                    <label class="form-label">Account Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="acctName" class="form-control" placeholder="e.g. Islami Bank, bKash" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Account Type <span class="text-danger">*</span></label>
                    <select name="type" id="acctType" class="form-select" required>
                        <option value="bank">Bank</option>
                        <option value="cash">Cash</option>
                        <option value="mobile_banking">Mobile Banking</option>
                        <option value="crypto">Crypto</option>
                        <option value="receivable">Receivable </option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="mb-3" id="nomineeGroup">
                    <label class="form-label">Nominee Name</label>
                    <input type="text" name="nominee_name" id="acctNominee" class="form-control" placeholder="Nominee name">
                </div>
                <div class="mb-3" id="balanceGroup">
                    <label class="form-label">Opening Balance</label>
                    <input type="number" name="balance" id="acctBalance" class="form-control" placeholder="0.00" min="0" step="0.01" value="0">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="acctDesc" class="form-control" rows="2" placeholder="Optional notes"></textarea>
                </div>
                <div id="acctAlert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="acctSubmitBtn">Save Account</button>
            </div>
        </form>
    </div></div>
</div>

<!-- ══ View Account Modal ══ -->
<div class="modal fade" id="accountViewModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Account Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="accountViewBody"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        </div>
    </div></div>
</div>

<!-- ══ Add Money Modal ══ -->
<div class="modal fade" id="addMoneyModal" tabindex="-1">
    <div class="modal-dialog modal-sm"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Add Money</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="addMoneyForm" onsubmit="submitAddMoney(event)">
            <div class="modal-body">
                <input type="hidden" name="account_id" id="addMoneyAcctId">
                <p class="text-muted fs-13 mb-3">Adding to: <strong id="addMoneyAcctName"></strong></p>
                <div class="mb-3">
                    <label class="form-label">Amount <span class="text-danger">*</span></label>
                    <input type="number" name="amount" class="form-control" placeholder="0.00" min="0.01" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Note (optional)</label>
                    <input type="text" name="description" class="form-control" placeholder="Salary, Transfer, etc.">
                </div>
                <div id="addMoneyAlert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success btn-sm">Add Money</button>
            </div>
        </form>
    </div></div>
</div>

<?php
$accountsJson = json_encode($accounts);
$extraJs = <<<JS
<script>
const ACCOUNTS_DATA = $accountsJson;
const ACCOUNT_TYPE_LABELS = {
    bank: "Bank",
    cash: "Cash",
    mobile_banking: "Mobile Banking",
    crypto: "Crypto",
    receivable: "Receivable",
    other: "Other"
};

function escHtml(value) {
    const div = document.createElement("div");
    div.textContent = value == null || value === "" ? "-" : value;
    return div.innerHTML;
}

function accountTypeLabel(type) {
    return ACCOUNT_TYPE_LABELS[type] || "Other";
}

function formatAccountDate(value) {
    if (!value) return "-";
    const date = new Date(String(value).replace(" ", "T"));
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function toggleNomineeField() {
    const type = document.getElementById("acctType")?.value;
    const group = document.getElementById("nomineeGroup");
    const input = document.getElementById("acctNominee");
    if (!group || !input) return;
    group.style.display = type === "bank" ? "block" : "none";
    if (type !== "bank") input.value = "";
}

function viewAccount(id) {
    const acc = ACCOUNTS_DATA.find(a => a.id == id);
    if (!acc) return;

    let html = "";
    html += "<div class=\"detail-row\"><span class=\"label\">Account Name</span><span class=\"value\">" + escHtml(acc.name) + "</span></div>";
    html += "<div class=\"detail-row\"><span class=\"label\">Type</span><span class=\"value\">" + escHtml(accountTypeLabel(acc.type)) + "</span></div>";
    if (acc.type === "bank") {
        html += "<div class=\"detail-row\"><span class=\"label\">Nominee</span><span class=\"value\">" + escHtml(acc.nominee_name) + "</span></div>";
    }
    html += "<div class=\"detail-row\"><span class=\"label\">Balance</span><span class=\"value\">" + fmtMoney(acc.balance || 0) + "</span></div>";
    html += "<div class=\"detail-row\"><span class=\"label\">Status</span><span class=\"value\">" + (acc.status == 1 ? "Active" : "Inactive") + "</span></div>";
    html += "<div class=\"detail-row\"><span class=\"label\">Description</span><span class=\"value\">" + escHtml(acc.description) + "</span></div>";
    html += "<div class=\"detail-row\"><span class=\"label\">Created</span><span class=\"value\">" + escHtml(formatAccountDate(acc.created_at)) + "</span></div>";
    html += "<div class=\"detail-row\"><span class=\"label\">Updated</span><span class=\"value\">" + escHtml(formatAccountDate(acc.updated_at)) + "</span></div>";

    document.getElementById("accountViewBody").innerHTML = html;
    bootstrap.Modal.getOrCreateInstance(document.getElementById("accountViewModal")).show();
}


document.getElementById("acctType")?.addEventListener("change", toggleNomineeField);

function openAccountModal(id = null) {
    const modal = new bootstrap.Modal('#accountModal');
    const form  = document.getElementById('accountForm');
    form.reset();
    document.getElementById('acctAlert').innerHTML = '';
    document.getElementById('balanceGroup').style.display = id ? 'none' : 'block';

    if (id) {
        const acc = ACCOUNTS_DATA.find(a => a.id == id);
        if (!acc) return;
        document.getElementById('accountModalTitle').textContent = 'Edit Account';
        document.getElementById('acctSubmitBtn').textContent    = 'Update Account';
        document.getElementById('acctId').value      = acc.id;
        document.getElementById('acctName').value    = acc.name;
        document.getElementById('acctType').value    = acc.type;
        document.getElementById('acctDesc').value    = acc.description || '';
        document.getElementById('acctNominee').value = acc.nominee_name || '';
    } else {
        document.getElementById('accountModalTitle').textContent = 'Add Account';
        document.getElementById('acctSubmitBtn').textContent    = 'Save Account';
        document.getElementById('acctId').value = '';
        document.getElementById('acctNominee').value = '';
    }
    toggleNomineeField();
    modal.show();
}

async function submitAccountForm(e) {
    e.preventDefault();
    const form = e.target;
    const data = formData(form);
    const isEdit = !!data.id;
    const url  = isEdit ? '/ajax/accounts.php?action=edit' : '/ajax/accounts.php?action=add';
    const btn  = form.querySelector('[type=submit]');
    btn.disabled = true;

    const res = await apiPost(url, data);
    btn.disabled = false;

    if (res.success) {
        bootstrap.Modal.getInstance('#accountModal').hide();
        showToast(res.message, 'success');
        setTimeout(reloadPage, 500);
    } else {
        document.getElementById('acctAlert').innerHTML =
            '<div class="alert alert-danger py-2 mt-2">' + res.message + '</div>';
    }
}

function openAddMoney(id, name) {
    document.getElementById('addMoneyAcctId').value      = id;
    document.getElementById('addMoneyAcctName').textContent = name;
    document.getElementById('addMoneyForm').reset();
    document.getElementById('addMoneyAcctId').value = id;
    document.getElementById('addMoneyAlert').innerHTML = '';
    new bootstrap.Modal('#addMoneyModal').show();
}

async function submitAddMoney(e) {
    e.preventDefault();
    const form = e.target;
    const data = formData(form);
    const btn  = form.querySelector('[type=submit]');
    btn.disabled = true;

    const res = await apiPost('/ajax/accounts.php?action=add_money', data);
    btn.disabled = false;

    if (res.success) {
        bootstrap.Modal.getInstance('#addMoneyModal').hide();
        showToast(res.message, 'success');
        setTimeout(reloadPage, 500);
    } else {
        document.getElementById('addMoneyAlert').innerHTML =
            '<div class="alert alert-danger py-2 mt-2">' + res.message + '</div>';
    }
}

async function deleteAccount(id) {
    if (!confirmAction('Delete this account? All related data will be preserved.')) return;
    const res = await apiPost('/ajax/accounts.php?action=delete', { id });
    if (res.success) { showToast(res.message, 'success'); setTimeout(reloadPage, 500); }
    else showToast(res.message, 'danger');
}
</script>
JS;
include __DIR__ . '/includes/layout_footer.php';
