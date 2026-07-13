<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Reports';
$pdo = db();

// Account summary
$accountSummary = $pdo->query("
    SELECT a.name, a.type, a.balance,
           COALESCE(SUM(CASE WHEN t.type='deposit' THEN t.amount ELSE 0 END),0) AS total_in,
           COALESCE(SUM(CASE WHEN t.type IN('withdrawal','investment') THEN ABS(t.amount) ELSE 0 END),0) AS total_out
    FROM accounts a
    LEFT JOIN transactions t ON t.account_id = a.id
    GROUP BY a.id
    ORDER BY a.balance DESC
")->fetchAll();

// Investment by category
$catSummary = $pdo->query("
    SELECT c.name,
           COUNT(i.id) AS total_count,
           SUM(CASE WHEN i.status='active' THEN 1 ELSE 0 END) AS active_count,
           COALESCE(SUM(CASE WHEN i.status='active' THEN i.amount ELSE 0 END),0) AS active_amount,
           COALESCE(SUM(i.actual_profit),0) AS total_profit
    FROM categories c
    LEFT JOIN investments i ON i.category_id = c.id
    GROUP BY c.id
    ORDER BY active_amount DESC
")->fetchAll();

// Monthly transactions (last 12 months)
$monthly = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS month,
           SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END) AS deposits,
           SUM(CASE WHEN type IN('withdrawal','investment') THEN ABS(amount) ELSE 0 END) AS outflow,
           SUM(CASE WHEN type='profit' THEN amount ELSE 0 END) AS profit
    FROM transactions
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month ASC
")->fetchAll();

// Yearly profit summary
$yearlyProfit = $pdo->query("
    SELECT YEAR(created_at) AS year,
           COALESCE(SUM(amount),0) AS profit
    FROM transactions
    WHERE type='profit'
    GROUP BY year
    ORDER BY year DESC
")->fetchAll();

$currentYearProfit = 0.0;
foreach ($yearlyProfit as $row) {
    if ((int) $row['year'] === (int) date('Y')) {
        $currentYearProfit = (float) $row['profit'];
        break;
    }
}

// Maturity upcoming (next 90 days)
$upcoming = $pdo->query("
    SELECT i.*, c.name AS cat_name, a.name AS acct_name
    FROM investments i
    JOIN categories c ON i.category_id = c.id
    JOIN accounts a   ON i.source_account_id = a.id
    WHERE i.status = 'active' AND i.maturity_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    ORDER BY i.maturity_date ASC
")->fetchAll();

include __DIR__ . '/includes/layout_header.php';
?>

<div class="row g-3 mb-4">
    <!-- Account Summary -->
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><h5><i class="fas fa-wallet me-2 text-primary"></i>Account Summary</h5></div>
            <div class="table-responsive report-table-wrap">
                <table class="table report-table report-account-table">
                    <thead><tr><th>Account</th><th>Balance</th><th>Total In</th><th class="report-optional-sm">Total Out</th></tr></thead>
                    <tbody>
                    <?php foreach ($accountSummary as $acc): ?>
                    <tr>
                        <td class="fw-600"><?= sanitize($acc['name']) ?></td>
                        <td class="amount-neutral"><?= formatMoney($acc['balance']) ?></td>
                        <td class="amount-pos"><?= formatMoney($acc['total_in']) ?></td>
                        <td class="amount-neg report-optional-sm"><?= formatMoney($acc['total_out']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$accountSummary): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">No data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Category Investment Summary -->
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><h5><i class="fas fa-tags me-2 text-purple"></i>Investment by Category</h5></div>
            <div class="table-responsive report-table-wrap">
                <table class="table report-table report-category-table">
                    <thead><tr><th>Category</th><th>Active</th><th>Invested</th><th>Profit Earned</th></tr></thead>
                    <tbody>
                    <?php foreach ($catSummary as $cat): ?>
                    <tr>
                        <td class="fw-600"><?= sanitize($cat['name']) ?></td>
                        <td><span class="badge status-active"><?= $cat['active_count'] ?></span></td>
                        <td class="amount-neutral"><?= formatMoney($cat['active_amount']) ?></td>
                        <td class="amount-pos"><?= formatMoney($cat['total_profit']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$catSummary): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">No data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Chart -->
<?php if ($monthly): ?>
<div class="card mb-4">
    <div class="card-header"><h5><i class="fas fa-chart-bar me-2 text-success"></i>Monthly Cash Flow (Last 12 Months)</h5></div>
    <div class="card-body"><div class="report-chart"><canvas id="monthlyChart"></canvas></div></div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><h5><i class="fas fa-coins me-2 text-success"></i>Monthly Profit</h5></div>
            <div class="table-responsive report-table-wrap">
                <table class="table report-table report-profit-table">
                    <thead><tr><th>Month</th><th>Profit</th></tr></thead>
                    <tbody>
                    <?php foreach ($monthly as $m): ?>
                    <tr>
                        <td class="fw-600"><?= date('M Y', strtotime($m['month'] . '-01')) ?></td>
                        <td class="amount-pos"><?= formatMoney((float) $m['profit']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$monthly): ?>
                    <tr><td colspan="2" class="text-center text-muted py-3">No profit data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h5><i class="fas fa-calendar-alt me-2 text-primary"></i>Yearly Profit</h5></div>
            <div class="card-body pb-0">
                <div class="stat-card sc-green mb-3">
                    <div class="sc-label"><?= date('Y') ?> Profit</div>
                    <div class="sc-value"><?= formatMoney($currentYearProfit) ?></div>
                    <div class="sc-sub">Current year</div>
                    <i class="fas fa-coins sc-icon"></i>
                </div>
            </div>
            <div class="table-responsive report-table-wrap">
                <table class="table report-table report-profit-table">
                    <thead><tr><th>Year</th><th>Total Profit</th></tr></thead>
                    <tbody>
                    <?php foreach ($yearlyProfit as $row): ?>
                    <tr>
                        <td class="fw-600"><?= (int) $row['year'] ?></td>
                        <td class="amount-pos"><?= formatMoney((float) $row['profit']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$yearlyProfit): ?>
                    <tr><td colspan="2" class="text-center text-muted py-3">No yearly profit data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Upcoming Maturities -->
<?php if ($upcoming): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="fas fa-clock me-2 text-warning"></i>Maturing in Next 90 Days</h5>
        <span class="badge bg-warning text-dark"><?= count($upcoming) ?></span>
    </div>
    <div class="table-responsive report-table-wrap">
        <table class="table report-table">
            <thead><tr><th>Category</th><th class="report-optional-sm">Account</th><th>Amount</th><th>Maturity Date</th><th class="report-optional-sm">Days Left</th></tr></thead>
            <tbody>
            <?php foreach ($upcoming as $inv): ?>
            <tr>
                <td class="fw-600"><?= sanitize($inv['cat_name']) ?></td>
                <td class="report-optional-sm"><?= sanitize($inv['acct_name']) ?></td>
                <td class="amount-neutral"><?= formatMoney($inv['amount']) ?></td>
                <td><?= formatDate($inv['maturity_date']) ?></td>
                <td class="report-optional-sm"><?= maturityBadge($inv['maturity_date']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($monthly):
    $labels    = json_encode(array_map(fn($m) => $m['month'], $monthly));
    $deposits  = json_encode(array_map(fn($m) => (float)$m['deposits'], $monthly));
    $outflows  = json_encode(array_map(fn($m) => (float)$m['outflow'],  $monthly));
    $profits   = json_encode(array_map(fn($m) => (float)$m['profit'],   $monthly));
    $extraJs = <<<JS
<script>
(function(){
    const ctx = document.getElementById('monthlyChart');
    if(!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: $labels,
            datasets: [
                { label: 'Deposits',  data: $deposits, backgroundColor: 'rgba(16,185,129,.7)', borderRadius: 4 },
                { label: 'Outflow',   data: $outflows,  backgroundColor: 'rgba(239,68,68,.7)',  borderRadius: 4 },
                { label: 'Profit',    data: $profits,   backgroundColor: 'rgba(59,130,246,.7)', borderRadius: 4 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            datasets: { bar: { maxBarThickness: 34, categoryPercentage: .68, barPercentage: .82 } },
            plugins: { legend: { position:'top' } },
            scales: { x: { ticks: { maxRotation: 0, autoSkip: true } }, y: { beginAtZero: true } }
        }
    });
})();
</script>
JS;
endif;
include __DIR__ . '/includes/layout_footer.php';
