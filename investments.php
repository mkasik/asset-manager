<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Investments';
$pdo = db();

$filterStatus = $_GET['status'] ?? 'all';
$filterCategory = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
$validStatuses = ['all', 'active', 'matured', 'renewed', 'withdrawn'];
if (!in_array($filterStatus, $validStatuses, true)) $filterStatus = 'all';

$whereParts = [];
if ($filterStatus !== 'all') {
    $whereParts[] = "i.status = " . $pdo->quote($filterStatus);
}
if ($filterCategory > 0) {
    $whereParts[] = "i.category_id = " . $filterCategory;
}
$where = $whereParts ? "WHERE " . implode(" AND ", $whereParts) : "";

function investmentFilterUrl(string $status, int $categoryId = 0): string {
    $params = ['status' => $status];
    if ($categoryId > 0) $params['category_id'] = $categoryId;
    return "?" . http_build_query($params);
}

$accounts   = $pdo->query("SELECT id, name, type, balance FROM accounts ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$selectedCategoryName = 'All Categories';
foreach ($categories as $c) {
    if ((int) $c['id'] === $filterCategory) {
        $selectedCategoryName = $c['name'];
        break;
    }
}



$investments = $pdo->query("
    SELECT i.*,
           c.name AS cat_name,
           a.name AS src_acct_name,
           pa.name AS profit_acct_name
    FROM investments i
    JOIN categories c  ON i.category_id = c.id
    JOIN accounts a    ON i.source_account_id = a.id
    LEFT JOIN accounts pa ON i.profit_account_id = pa.id
    $where
    ORDER BY i.created_at DESC
")->fetchAll();

// Summary stats
$stats = $pdo->query("
    SELECT
        SUM(CASE WHEN status='active' THEN amount ELSE 0 END) AS active_amt,
        SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active_cnt,
        SUM(CASE WHEN status IN('renewed','withdrawn') THEN actual_profit ELSE 0 END) AS total_profit,
        SUM(CASE WHEN status='matured' THEN amount ELSE 0 END) AS matured_amt
    FROM investments
")->fetch();

include __DIR__ . '/includes/layout_header.php';
?>

<!-- Stats row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card sc-blue"><div class="sc-label">Active Invested</div>
        <div class="sc-value"><?= formatMoney($stats['active_amt'] ?? 0) ?></div>
        <div class="sc-sub"><?= $stats['active_cnt'] ?> investment(s)</div><i class="fas fa-chart-line sc-icon"></i></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card sc-green"><div class="sc-label">Total Profit</div>
        <div class="sc-value"><?= formatMoney($stats['total_profit'] ?? 0) ?></div>
        <div class="sc-sub">Realised</div><i class="fas fa-coins sc-icon"></i></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card sc-amber"><div class="sc-label">Matured</div>
        <div class="sc-value"><?= formatMoney($stats['matured_amt'] ?? 0) ?></div>
        <div class="sc-sub">Awaiting action</div><i class="fas fa-hourglass-end sc-icon"></i></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card sc-purple"><div class="sc-label">Active Investments</div>
        <div class="sc-value"><?= (int)($stats['active_cnt'] ?? 0) ?></div>
        <div class="sc-sub">Currently active</div><i class="fas fa-list sc-icon"></i></div>
    </div>
</div>


<div class="card">
    <!-- Filter & Actions Bar -->
    <div class="card-header flex-wrap gap-2">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <?php foreach (["all","active","matured","renewed","withdrawn"] as $s): ?>
            <a href="<?= investmentFilterUrl($s, $filterCategory) ?>" class="btn btn-sm <?= $filterStatus===$s ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= ucfirst($s) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <form method="get" class="d-flex gap-2 align-items-center flex-wrap ms-auto">
            <input type="hidden" name="status" value="<?= sanitize($filterStatus) ?>">
            <select name="category_id" class="form-select form-select-sm" style="width:auto;min-width:190px" onchange="this.form.submit()">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (int)$c['id'] === $filterCategory ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($filterCategory > 0): ?>
            <a href="<?= investmentFilterUrl($filterStatus) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>
        <?php if (isAdmin()): ?>
        <button class="btn btn-primary btn-sm" onclick="openInvestModal()">
            <i class="fas fa-plus me-1"></i> New Investment
        </button>
        <?php endif; ?>
    </div>

    <?php if ($investments): ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th>#</th><th>Title</th><th>Category</th><th>From Account</th><th>Amount</th>
                <th>Profit</th><th>Start Date</th><th>Maturity</th>
                <th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($investments as $idx => $inv): ?>
            <tr>
                <td class="text-muted"><?= $idx+1 ?></td>
                <td class="fw-600"><?= sanitize($inv['name'] ?? '—') ?></td>
                <td><?= sanitize($inv['cat_name']) ?></td>
                <td><?= sanitize($inv['src_acct_name']) ?></td>
                <td class="amount-neutral"><?= formatMoney($inv['amount']) ?></td>
                <?php $displayProfit = in_array($inv['status'], ['renewed', 'withdrawn'], true) ? (float)($inv['actual_profit'] ?? 0) : (float)($inv['expected_profit'] ?? 0); ?>
                <td class="amount-neutral"><?= $displayProfit > 0 ? formatMoney($displayProfit) : '—' ?></td>
                <td class="text-muted fs-13"><?= formatDate($inv['start_date']) ?></td>
                <td>
                    <?php if ($inv['maturity_date']): ?>
                    <div class="fs-13 mb-1"><?= formatDate($inv['maturity_date']) ?></div>
                    <?php if ($inv['status']==='active') echo maturityBadge($inv['maturity_date']); ?>
                    <?php else: ?><span class="text-muted fs-13">—</span><?php endif; ?>
                </td>
                <td><span class="badge status-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span></td>
                <td>
                    <div class="d-flex gap-1">
                        <button class="btn-act btn-view" onclick="viewInvestment(<?= $inv['id'] ?>)" title="View"><i class="fas fa-eye"></i></button>
                        <?php if (isAdmin() && $inv['status']==='active'): ?>
                        <button class="btn-act btn-renew"    onclick="openRenewModal(<?= $inv['id'] ?>)"    title="Renew"><i class="fas fa-redo"></i></button>
                        <button class="btn-act btn-withdraw" onclick="openWithdrawModal(<?= $inv['id'] ?>)" title="Withdraw"><i class="fas fa-hand-holding-usd"></i></button>
                        <button class="btn-act btn-edit"     onclick="openInvestModal(<?= $inv['id'] ?>)"   title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn-act btn-delete"   onclick="deleteInvest(<?= $inv['id'] ?>)"      title="Delete"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="es-icon"><i class="fas fa-chart-line"></i></div>
    <p>No investments found for <?= sanitize($selectedCategoryName) ?><?= $filterStatus !== 'all' ? ' with status: ' . sanitize($filterStatus) : '' ?>.</p></div>
    <?php endif; ?>
</div>

<!-- ══ Add/Edit Investment Modal ══ -->
<div class="modal fade" id="investModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="investModalTitle">New Investment</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="investForm" onsubmit="submitInvestForm(event)">
            <div class="modal-body">
                <input type="hidden" name="id" id="investId">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Investment Title <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. FDR - Islami Bank, Pond Lease - গ্রামের পুকুর" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select category…</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Source Account <span class="text-danger">*</span></label>
                        <select name="source_account_id" id="srcAcctSelect" class="form-select" required>
                            <option value="">Select account…</option>
                            <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['id'] ?>" data-balance="<?= $a['balance'] ?>">
                                <?= sanitize($a['name']) ?> (<?= formatMoney($a['balance']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" id="srcBalanceHint"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" placeholder="0.00" min="1" step="0.01" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Profit Type</label>
                        <select name="profit_type" class="form-select">
                            <option value="percent">Percent (%)</option>
                            <option value="fixed">Fixed Amount</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Profit Rate</label>
                        <input type="number" name="profit_rate" class="form-control" placeholder="0.00" min="0" step="0.0001">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Expected Profit</label>
                        <input type="number" name="expected_profit" class="form-control" placeholder="Auto-calculated" min="0" step="0.01">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Profit Goes To Account</label>
                        <select name="profit_account_id" class="form-select">
                            <option value="">Same as source / Select later</option>
                            <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= sanitize($a['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Start Date <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Maturity Date</label>
                        <input type="date" name="maturity_date" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Reference number, institution name…"></textarea>
                    </div>
                </div>
                <div id="investAlert" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="investSubmitBtn">Create Investment</button>
            </div>
        </form>
    </div></div>
</div>

<!-- ══ Renew Modal ══ -->
<div class="modal fade" id="renewModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Renew Investment</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="renewForm" onsubmit="submitRenew(event)">
            <div class="modal-body">
                <input type="hidden" name="id" id="renewId">
                <div class="alert alert-info py-2 fs-13 mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Profit will be credited to the profit account. Principal stays invested.
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Actual Profit Received <span class="text-danger">*</span></label>
                        <input type="number" name="actual_profit" class="form-control" placeholder="0.00" min="0" step="0.01" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Credit Profit To Account</label>
                        <select name="profit_account_id" id="renewProfitAcct" class="form-select">
                            <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= sanitize($a['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">New Amount</label>
                        <input type="number" name="new_amount" id="renewAmount" class="form-control" placeholder="Same as original" min="0.01" step="0.01">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">New Maturity Date</label>
                        <input type="date" name="new_maturity_date" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">New Expected Profit</label>
                        <input type="number" name="new_expected_profit" class="form-control" placeholder="Leave blank to keep same" min="0" step="0.01">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div id="renewAlert" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning btn-sm text-white">Renew Investment</button>
            </div>
        </form>
    </div></div>
</div>

<!-- ══ Withdraw Modal ══ -->
<div class="modal fade" id="withdrawModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Withdraw Investment</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="withdrawForm" onsubmit="submitWithdraw(event)">
            <div class="modal-body">
                <input type="hidden" name="id" id="withdrawId">
                <div class="alert alert-warning py-2 fs-13 mb-3">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Principal will be returned to the selected account.
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Actual Profit Received</label>
                        <input type="number" name="actual_profit" class="form-control" placeholder="0.00" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Return Principal To <span class="text-danger">*</span></label>
                        <select name="return_account_id" class="form-select" required>
                            <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= sanitize($a['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Credit Profit To</label>
                        <select name="profit_account_id" id="wdProfitAcct" class="form-select">
                            <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= sanitize($a['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div id="withdrawAlert" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm">Confirm Withdrawal</button>
            </div>
        </form>
    </div></div>
</div>

<!-- ══ View Modal ══ -->
<div class="modal fade" id="viewInvestModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Investment Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="viewInvestBody">
            <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>
        </div>
    </div></div>
</div>

<?php
$investJson = json_encode($investments, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]';
$extraJs = <<<'JSEOF'
<script>
const INV_DATA = JSON.parse(document.getElementById('invDataJson')?.textContent || '[]');

document.getElementById('srcAcctSelect')?.addEventListener('change', function () {
    const opt   = this.options[this.selectedIndex];
    const bal   = opt.dataset.balance;
    const hint  = document.getElementById('srcBalanceHint');
    if (hint) hint.textContent = bal ? 'Available: ৳ ' + parseFloat(bal).toLocaleString() : '';
});

function openInvestModal(id = null) {
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('investModal'));
    const form  = document.getElementById('investForm');
    form.reset();
    document.getElementById('srcBalanceHint').textContent = '';
    document.getElementById('investAlert').innerHTML = '';
    document.getElementById('investId').value = '';
    document.querySelector('#investForm [name="start_date"]').value = new Date().toISOString().slice(0,10);

    if (id) {
        const inv = INV_DATA.find(x => x.id == id);
        if (!inv) return;
        document.getElementById('investModalTitle').textContent  = 'Edit Investment';
        document.getElementById('investSubmitBtn').textContent   = 'Update Investment';
        document.getElementById('investId').value = inv.id;
        // Fill fields
        form.querySelector('[name="name"]').value              = inv.name || '';
        form.querySelector('[name="category_id"]').value       = inv.category_id;
        form.querySelector('[name="source_account_id"]').value = inv.source_account_id;
        form.querySelector('[name="amount"]').value            = inv.amount;
        form.querySelector('[name="profit_type"]').value       = inv.profit_type;
        form.querySelector('[name="profit_rate"]').value       = inv.profit_rate;
        form.querySelector('[name="expected_profit"]').value   = inv.expected_profit;
        form.querySelector('[name="profit_account_id"]').value = inv.profit_account_id || '';
        form.querySelector('[name="start_date"]').value        = inv.start_date;
        form.querySelector('[name="maturity_date"]').value     = inv.maturity_date || '';
        form.querySelector('[name="notes"]').value             = inv.notes || '';
    } else {
        document.getElementById('investModalTitle').textContent = 'New Investment';
        document.getElementById('investSubmitBtn').textContent  = 'Create Investment';
    }
    modal.show();
}

async function submitInvestForm(e) {
    e.preventDefault();
    const data = formData(e.target);
    const url  = data.id ? '/ajax/investments.php?action=edit' : '/ajax/investments.php?action=add';
    const btn  = e.target.querySelector('[type=submit]');
    btn.disabled = true;

    try {
        const res = await apiPost(url, data);
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('investModal'))?.hide();
            showToast(res.message, 'success');
            setTimeout(reloadPage, 500);
        } else {
            document.getElementById('investAlert').innerHTML = '<div class="alert alert-danger py-2">' + res.message + '</div>';
        }
    } catch (err) {
        document.getElementById('investAlert').innerHTML = '<div class="alert alert-danger py-2">Request failed. Please refresh and try again.</div>';
    } finally {
        btn.disabled = false;
    }
}

function openRenewModal(id) {
    const inv = INV_DATA.find(x => x.id == id);
    if (!inv) return;
    document.getElementById('renewForm').reset();
    document.getElementById('renewAlert').innerHTML = '';
    document.getElementById('renewId').value   = id;
    document.querySelector('#renewForm [name="actual_profit"]').value = parseFloat(inv.expected_profit || 0).toFixed(2);
    document.querySelector('#renewForm [name="new_expected_profit"]').value = parseFloat(inv.expected_profit || 0).toFixed(2);
    document.getElementById('renewAmount').value = inv.amount;
    if (inv.profit_account_id)
        document.getElementById('renewProfitAcct').value = inv.profit_account_id;
    new bootstrap.Modal('#renewModal').show();
}

async function submitRenew(e) {
    e.preventDefault();
    const data = formData(e.target);
    data.actual_profit = e.target.querySelector('[name="actual_profit"]').value;
    const btn  = e.target.querySelector('[type=submit]');
    btn.disabled = true;
    const res = await apiPost('/ajax/investments.php?action=renew', data);
    btn.disabled = false;
    if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('renewModal'))?.hide();
        showToast(res.message, 'success');
        setTimeout(reloadPage, 500);
    } else {
        document.getElementById('renewAlert').innerHTML = '<div class="alert alert-danger py-2">' + res.message + '</div>';
    }
}

function openWithdrawModal(id) {
    const inv = INV_DATA.find(x => x.id == id);
    if (!inv) return;
    document.getElementById('withdrawForm').reset();
    document.getElementById('withdrawAlert').innerHTML = '';
    document.getElementById('withdrawId').value = id;
    document.querySelector('#withdrawForm [name="actual_profit"]').value = parseFloat(inv.expected_profit || 0).toFixed(2);
    if (inv.profit_account_id) {
        document.getElementById('wdProfitAcct').value = inv.profit_account_id;
        document.querySelector('#withdrawForm [name="return_account_id"]').value = inv.profit_account_id;
    }
    new bootstrap.Modal('#withdrawModal').show();
}

async function submitWithdraw(e) {
    e.preventDefault();
    const data = formData(e.target);
    data.actual_profit = e.target.querySelector('[name="actual_profit"]').value;
    const btn  = e.target.querySelector('[type=submit]');
    btn.disabled = true;
    const res = await apiPost('/ajax/investments.php?action=withdraw', data);
    btn.disabled = false;
    if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('withdrawModal'))?.hide();
        showToast(res.message, 'success');
        setTimeout(reloadPage, 500);
    } else {
        document.getElementById('withdrawAlert').innerHTML = '<div class="alert alert-danger py-2">' + res.message + '</div>';
    }
}

async function viewInvestment(id) {
    const modal = new bootstrap.Modal('#viewInvestModal');
    document.getElementById('viewInvestBody').innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>';
    modal.show();
    const res = await apiPost('/ajax/investments.php?action=view', { id });
    if (res.success) {
        document.getElementById('viewInvestBody').innerHTML = res.data.html;
    }
}

async function deleteInvest(id) {
    if (!confirmAction('Delete this investment? This will also reverse the account balance.')) return;
    const res = await apiPost('/ajax/investments.php?action=delete', { id });
    if (res.success) { showToast(res.message, 'success'); setTimeout(reloadPage, 500); }
    else showToast(res.message, 'danger');
}
</script>
JSEOF;

// Embed investments data safely
$invDataScript = '<script id="invDataJson" type="application/json">' . $investJson . '</script>';
$extraJs = $invDataScript . $extraJs;
include __DIR__ . '/includes/layout_footer.php';
