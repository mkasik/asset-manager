<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireAdmin();

$pageTitle = 'Maintenance';
$pdo = db();

$counts = [
    'accounts'     => (int) $pdo->query("SELECT COUNT(*) FROM accounts")->fetchColumn(),
    'categories'   => (int) $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
    'investments'  => (int) $pdo->query("SELECT COUNT(*) FROM investments")->fetchColumn(),
    'transactions' => (int) $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn(),
    'users'        => (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
];

include __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
    <div><h2>Maintenance</h2><div class="sub">Production cleanup and database tools</div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card sc-blue h-100">
            <div class="sc-label">Accounts</div>
            <div class="sc-value"><?= $counts["accounts"] ?></div>
            <div class="sc-sub">Ready to reset</div>
            <i class="fas fa-wallet sc-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card sc-teal h-100">
            <div class="sc-label">Categories</div>
            <div class="sc-value"><?= $counts["categories"] ?></div>
            <div class="sc-sub">Investment types</div>
            <i class="fas fa-tags sc-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card sc-green h-100">
            <div class="sc-label">Investments</div>
            <div class="sc-value"><?= $counts["investments"] ?></div>
            <div class="sc-sub">Test entries</div>
            <i class="fas fa-chart-line sc-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card sc-amber h-100">
            <div class="sc-label">Transactions</div>
            <div class="sc-value"><?= $counts["transactions"] ?></div>
            <div class="sc-sub">Ledger rows</div>
            <i class="fas fa-exchange-alt sc-icon"></i>
        </div>
    </div>
</div>

<div class="card">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
        <div>
            <h5 class="fw-700 mb-2">Reset asset data</h5>
            <p class="text-muted mb-2">
                Deletes all accounts, categories, investments, and transactions. User logins stay untouched.
            </p>
            <div class="alert alert-warning py-2 mb-0 fs-13">
                This is permanent. Use it only after confirming the current data is test data.
            </div>
        </div>
        <div class="maintenance-action">
            <label class="form-label">Type RESET to confirm</label>
            <input type="text" id="resetConfirm" class="form-control mb-2" autocomplete="off" placeholder="RESET">
            <button class="btn btn-danger w-100" id="resetBtn" onclick="resetAssetData()">
                <i class="fas fa-trash-alt me-1"></i> Reset Asset Data
            </button>
        </div>
    </div>
</div>

<?php
$extraJs = <<<JS
<script>
async function resetAssetData() {
    const confirmText = document.getElementById('resetConfirm').value.trim();
    if (confirmText !== 'RESET') {
        showToast('Type RESET to confirm.', 'warning');
        return;
    }
    if (!confirmAction('Delete all accounts, categories, investments and transactions? Users will be kept.')) return;

    const btn = document.getElementById('resetBtn');
    btn.disabled = true;
    const res = await apiPost('/ajax/maintenance.php?action=reset_asset_data', { confirm: confirmText });
    btn.disabled = false;

    if (res.success) {
        showToast(res.message, 'success');
        setTimeout(reloadPage, 700);
    } else {
        showToast(res.message, 'danger');
    }
}
</script>
JS;
include __DIR__ . '/includes/layout_footer.php';
