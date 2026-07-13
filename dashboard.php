<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Dashboard';
$pdo = db();

// ── Summary stats ──────────────────────────────────────────
$totalBalance  = (float) $pdo->query("SELECT COALESCE(SUM(balance),0) FROM accounts")->fetchColumn();
$totalInvested = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM investments WHERE status IN ('active','matured')")->fetchColumn();
$totalProfit   = (float) $pdo->query("SELECT COALESCE(SUM(actual_profit),0) FROM investments WHERE status IN ('renewed','withdrawn')")->fetchColumn();
$netWorth      = $totalBalance + $totalInvested;
$activeCount   = (int)   $pdo->query("SELECT COUNT(*) FROM investments WHERE status='active'")->fetchColumn();
$overdueCount  = (int)   $pdo->query("SELECT COUNT(*) FROM investments WHERE status='active' AND maturity_date < CURDATE()")->fetchColumn();

// ── Account balances ──────────────────────────────────────
$accounts = $pdo->query("SELECT id, name, type, balance FROM accounts ORDER BY balance DESC")->fetchAll();

// ── Last 5 active investments ─────────────────────────
$lastInvestments = $pdo->query("
    SELECT i.id, i.name, i.amount, i.status, i.created_at,
           c.name AS cat_name, a.name AS acct_name
    FROM investments i
    JOIN categories c ON i.category_id = c.id
    JOIN accounts   a ON i.source_account_id = a.id
    WHERE i.status = 'active'
    ORDER BY i.created_at DESC
    LIMIT 5
")->fetchAll();

// ── Upcoming profit: active investments with maturity date ─
$upcoming = $pdo->query("
    SELECT i.id, i.name, i.amount, i.profit_rate, i.profit_type,
           i.expected_profit, i.maturity_date,
           c.name AS cat_name, a.name AS acct_name,
           pa.name AS profit_acct_name
    FROM investments i
    JOIN categories c  ON i.category_id = c.id
    JOIN accounts a    ON i.source_account_id = a.id
    LEFT JOIN accounts pa ON i.profit_account_id = pa.id
    WHERE i.status = 'active' AND i.maturity_date IS NOT NULL
    ORDER BY i.maturity_date ASC
")->fetchAll();

// ── Recent profit transactions ─────────────────────────
$recentTx = $pdo->query("
    SELECT t.*, a.name AS acct_name
    FROM transactions t
    JOIN accounts a ON t.account_id = a.id
    WHERE t.type = 'profit'
    ORDER BY t.created_at DESC
    LIMIT 5
")->fetchAll();

include __DIR__ . '/includes/layout_header.php';
?>

<!-- ── Stat Cards ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card sc-blue h-100">
            <div class="sc-label">Total Balance</div>
            <div class="sc-value"><?= formatMoney($totalBalance) ?></div>
            <div class="sc-sub"><?= count($accounts) ?> account(s)</div>
            <i class="fas fa-wallet sc-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card sc-purple h-100">
            <div class="sc-label">Total Invested</div>
            <div class="sc-value"><?= formatMoney($totalInvested) ?></div>
            <div class="sc-sub"><?= $activeCount ?> active</div>
            <i class="fas fa-chart-line sc-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card sc-green h-100">
            <div class="sc-label">Total Profit</div>
            <div class="sc-value"><?= formatMoney($totalProfit) ?></div>
            <div class="sc-sub">Realised profit</div>
            <i class="fas fa-coins sc-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card sc-amber h-100">
            <div class="sc-label">Net Worth</div>
            <div class="sc-value"><?= formatMoney($netWorth) ?></div>
            <div class="sc-sub"><?= $overdueCount > 0 ? '<span style="color:#fde68a">'.$overdueCount.' overdue</span>' : 'All on track' ?></div>
            <i class="fas fa-gem sc-icon"></i>
        </div>
    </div>
</div>

<!-- ── AI Insights ── -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="fas fa-wand-magic-sparkles me-2 text-primary"></i>AI ইনসাইটস</h5>
        <button type="button" id="aiInsightsBtn" class="btn btn-sm btn-outline-primary btn-card-action">
            <i class="fas fa-sparkles"></i> নতুন পরামর্শ নিন
        </button>
    </div>
    <div class="card-body">
        <div id="aiInsightsList" class="row g-3">
            <div class="col-12 text-muted fs-13" id="aiInsightsEmpty">এখনো কোনো পরামর্শ নেই — উপরের বাটনে ক্লিক করো।</div>
        </div>
    </div>
</div>

<!-- ── Account Balances Row ── -->
<?php
$chartColors = ['#3b82f6','#10b981','#8b5cf6','#f59e0b','#ef4444','#06b6d4','#f43f5e','#84cc16'];
?>
<div class="row g-3 mb-4">
    <!-- Left: Donut Chart (centered) -->
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header">
                <h5>Account Balances</h5>
                <a href="<?= SITE_URL ?>/accounts.php" class="btn btn-sm btn-outline-primary btn-card-action">View All</a>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center py-4">
                <?php if ($accounts): ?>
                <div style="position:relative;width:100%;max-width:240px;margin:0 auto">
                    <canvas id="acctChart"></canvas>
                </div>
                <?php else: ?>
                <div class="empty-state"><div class="es-icon"><i class="fas fa-wallet"></i></div><p>No accounts yet</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Account Detail List -->
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header">
                <h5>Account Details</h5>
                <span class="text-muted fs-13"><?= formatMoney($totalBalance) ?> total</span>
            </div>
            <div class="card-body">
                <?php if ($accounts): ?>
                <?php foreach ($accounts as $idx => $acc):
                    $color = $chartColors[$idx % count($chartColors)];
                    $pct   = $totalBalance > 0 ? ($acc['balance'] / $totalBalance * 100) : 0;
                ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="acct-icon <?= $acc['type'] ?>" style="flex-shrink:0">
                        <i class="fas <?= accountTypeIcon($acc['type']) ?>"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                            <span class="fw-600 fs-13 d-flex align-items-center gap-1">
                                <span style="width:8px;height:8px;border-radius:50%;background:<?= $color ?>;display:inline-block;flex-shrink:0"></span>
                                <?= sanitize($acc['name']) ?>
                                <span class="text-muted" style="font-size:11px;font-weight:400"><?= accountTypeLabel($acc['type']) ?></span>
                            </span>
                            <span class="fw-700" style="font-size:13px;white-space:nowrap"><?= formatMoney($acc['balance']) ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:5px;border-radius:4px;background:#f1f5f9">
                                <div class="progress-bar" style="width:<?= number_format($pct,1) ?>%;background:<?= $color ?>;border-radius:4px"></div>
                            </div>
                            <span class="text-muted" style="font-size:11px;min-width:34px;text-align:right"><?= number_format($pct,1) ?>%</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="empty-state py-4"><div class="es-icon"><i class="fas fa-wallet"></i></div><p>No accounts yet</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Upcoming Profit ── -->
<?php if ($upcoming): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="fas fa-hourglass-half me-2 text-warning"></i>Upcoming Profit</h5>
        <a href="<?= SITE_URL ?>/investments.php?status=active" class="btn btn-sm btn-outline-primary btn-card-action">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th>#</th><th>Title</th><th>Category</th><th>From Account</th>
                <th>Amount</th><th>Expected Profit</th><th>Maturity Date</th><th>Days Left</th>
            </tr></thead>
            <tbody>
            <?php foreach ($upcoming as $idx => $inv):
                $days = daysUntil($inv['maturity_date']);
            ?>
            <tr>
                <td class="text-muted"><?= $idx + 1 ?></td>
                <td class="fw-600"><?= sanitize($inv['name'] ?? '—') ?></td>
                <td class="fs-13"><?= sanitize($inv['cat_name']) ?></td>
                <td class="fs-13"><?= sanitize($inv['acct_name']) ?></td>
                <td class="amount-neutral"><?= formatMoney($inv['amount']) ?></td>
                <td class="amount-pos"><?= $inv['expected_profit'] > 0 ? formatMoney($inv['expected_profit']) : '—' ?></td>
                <td class="fs-13"><?= formatDate($inv['maturity_date']) ?></td>
                <td>
                    <?php if ($days === null): ?>
                        <span class="text-muted">—</span>
                    <?php elseif ($days < 0): ?>
                        <span class="maturity-badge overdue"><i class="fas fa-exclamation-circle"></i> Overdue <?= abs($days) ?>d</span>
                    <?php elseif ($days === 0): ?>
                        <span class="maturity-badge overdue"><i class="fas fa-bell"></i> Today!</span>
                    <?php elseif ($days <= 30): ?>
                        <span class="maturity-badge soon"><i class="fas fa-clock"></i> <?= $days ?> days</span>
                    <?php else: ?>
                        <span class="maturity-badge ok"><i class="fas fa-check-circle"></i> <?= $days ?> days</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="fas fa-hourglass-half me-2 text-warning"></i>Upcoming Profit</h5>
        <a href="<?= SITE_URL ?>/investments.php?status=active" class="btn btn-sm btn-outline-primary btn-card-action">View All</a>
    </div>
    <div class="empty-state py-4">
        <div class="es-icon"><i class="fas fa-hourglass-half"></i></div>
        <p>No active investments with a maturity date set</p>
    </div>
</div>
<?php endif; ?>

<!-- ── Recent Investments ── -->
<div class="card mb-4">
    <div class="card-header">
        <h5>Recent Active Investments</h5>
        <a href="<?= SITE_URL ?>/investments.php" class="btn btn-sm btn-outline-primary btn-card-action">View All</a>
    </div>
    <?php if ($lastInvestments): ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th>#</th><th>Title</th><th>Category</th><th>From Account</th><th>Amount</th><th>Status</th><th>Date</th>
            </tr></thead>
            <tbody>
            <?php foreach ($lastInvestments as $idx => $inv): ?>
            <tr>
                <td class="text-muted"><?= $idx + 1 ?></td>
                <td class="fw-600"><?= sanitize($inv['name'] ?? '—') ?></td>
                <td class="fs-13"><?= sanitize($inv['cat_name']) ?></td>
                <td class="text-muted fs-13"><?= sanitize($inv['acct_name']) ?></td>
                <td class="amount-neutral"><?= formatMoney($inv['amount']) ?></td>
                <td><span class="badge status-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span></td>
                <td class="text-muted fs-13"><?= formatDate($inv['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="es-icon"><i class="fas fa-chart-line"></i></div><p>No investments yet</p></div>
    <?php endif; ?>
</div>

<!-- ── Recent Transactions ── -->
<div class="card">
    <div class="card-header">
        <h5>Recent Profit Transactions</h5>
        <a href="<?= SITE_URL ?>/transactions.php" class="btn btn-sm btn-outline-primary btn-card-action">View All</a>
    </div>
    <?php if ($recentTx): ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>#</th><th>Account</th><th>Type</th><th>Amount</th><th>Description</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($recentTx as $idx => $tx): ?>
            <tr>
                <td class="text-muted"><?= $idx + 1 ?></td>
                <td><?= sanitize($tx['acct_name']) ?></td>
                <td><span class="badge tx-<?= $tx['type'] ?>"><?= ucfirst(str_replace('_', ' ', $tx['type'])) ?></span></td>
                <td class="<?= $tx['amount'] >= 0 ? 'amount-pos' : 'amount-neg' ?>"><?= formatMoney(abs($tx['amount'])) ?></td>
                <td class="text-muted fs-13"><?= sanitize($tx['description'] ?? '') ?></td>
                <td class="text-muted fs-13"><?= formatDateTime($tx['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="es-icon"><i class="fas fa-exchange-alt"></i></div><p>No profit transactions yet</p></div>
    <?php endif; ?>
</div>

<?php
$acctLabels       = json_encode(array_map(fn($a) => $a['name'], $accounts));
$acctValues       = json_encode(array_map(fn($a) => (float) $a['balance'], $accounts));
$totalBalFormatted = number_format($totalBalance, 2);

$chartScript = '';
if ($accounts) {
    $chartScript = "<script>
(function(){
    const ctx = document.getElementById('acctChart');
    if(!ctx) return;

    // Inline center-text plugin (total balance in donut hole)
    Chart.register({
        id: 'centerText',
        afterDraw(chart) {
            if (chart.config.type !== 'doughnut') return;
            const {ctx: c, chartArea: {left, top, right, bottom}} = chart;
            const cx = (left + right) / 2;
            const cy = (top + bottom) / 2;
            c.save();
            c.textAlign = 'center';
            c.textBaseline = 'middle';
            c.font = '700 15px Inter, system-ui, sans-serif';
            c.fillStyle = '#1e293b';
            c.fillText('৳ {$totalBalFormatted}', cx, cy - 9);
            c.font = '500 10px Inter, system-ui, sans-serif';
            c.fillStyle = '#94a3b8';
            c.fillText('Total Balance', cx, cy + 11);
            c.restore();
        }
    });

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: {$acctLabels},
            datasets: [{
                data: {$acctValues},
                backgroundColor: ['#3b82f6','#10b981','#8b5cf6','#f59e0b','#ef4444','#06b6d4','#f43f5e','#84cc16'],
                borderWidth: 4,
                borderColor: '#ffffff',
                hoverBorderColor: '#ffffff',
                hoverOffset: 12
            }]
        },
        options: {
            cutout: '68%',
            animation: { animateRotate: true, duration: 900, easing: 'easeInOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#94a3b8',
                    bodyColor: '#f1f5f9',
                    padding: 12,
                    cornerRadius: 8,
                    bodyFont: { size: 13, weight: '700' },
                    callbacks: {
                        label: (c) => '  ৳ ' + c.parsed.toLocaleString('en-US', {minimumFractionDigits:2})
                    }
                }
            }
        }
    });
})();
</script>";
}
$insightsScript = <<<'JS'
<script>
(function(){
    const listEl  = document.getElementById('aiInsightsList');
    const emptyEl = document.getElementById('aiInsightsEmpty');
    const btn     = document.getElementById('aiInsightsBtn');

    function render(suggestions) {
        listEl.querySelectorAll('.ai-suggestion-card').forEach(el => el.remove());
        if (!suggestions.length) {
            emptyEl.style.display = '';
            return;
        }
        emptyEl.style.display = 'none';
        suggestions.forEach(s => {
            const col = document.createElement('div');
            col.className = 'col-md-6 ai-suggestion-card';
            col.innerHTML = `<div class="p-3 h-100" style="background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0">
                <div class="fw-600 fs-13 mb-1"><i class="fas fa-lightbulb text-warning me-1"></i>${s.title}</div>
                <div class="text-muted fs-13">${s.detail}</div>
            </div>`;
            listEl.appendChild(col);
        });
    }

    async function loadExisting() {
        const res = await apiPost('/ajax/insights.php?action=list', {});
        if (res.success) render(res.data || []);
    }

    btn.addEventListener('click', async () => {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> তৈরি হচ্ছে...';
        const res = await apiPost('/ajax/insights.php?action=generate', {});
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sparkles"></i> নতুন পরামর্শ নিন';
        if (res.success) {
            showToast(res.message || 'পরামর্শ তৈরি হয়েছে।', 'success');
            render(res.data || []);
        } else {
            showToast(res.message || 'কিছু ভুল হয়েছে।', 'danger');
        }
    });

    loadExisting();
})();
</script>
JS;

$extraJs = $chartScript . $insightsScript;
include __DIR__ . '/includes/layout_footer.php';
