<?php
function formatMoney(float $amount, string $symbol = '৳'): string {
    return $symbol . ' ' . number_format($amount, 2);
}

function formatDate(?string $date): string {
    if (!$date) return '-';
    return date('d M Y', strtotime($date));
}

function formatDateTime(?string $dt): string {
    if (!$dt) return '-';
    return date('d M Y, h:i A', strtotime($dt));
}

function sanitize(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function ensureAccountNomineeColumn(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;

    $stmt = $pdo->query("SHOW COLUMNS FROM accounts LIKE 'nominee_name'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE accounts ADD COLUMN nominee_name varchar(100) DEFAULT NULL AFTER type");
    }
    $checked = true;
}

function jsonResponse(bool $success, string $message = '', $data = null): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

function badgeClass(string $status): string {
    return match ($status) {
        'active'                  => 'bg-success-subtle text-success',
        'matured'                 => 'bg-warning-subtle text-warning',
        'renewed'                 => 'bg-primary-subtle text-primary',
        'withdrawn'               => 'bg-secondary-subtle text-secondary',
        'deposit'                 => 'bg-success-subtle text-success',
        'withdrawal'              => 'bg-danger-subtle text-danger',
        'investment'              => 'bg-primary-subtle text-primary',
        'profit'                  => 'bg-success-subtle text-success',
        'transfer_in'             => 'bg-info-subtle text-info',
        'transfer_out'            => 'bg-warning-subtle text-warning',
        'renewal'                 => 'bg-primary-subtle text-primary',
        default                   => 'bg-secondary-subtle text-secondary',
    };
}

function accountTypeIcon(string $type): string {
    return match ($type) {
        'bank'           => 'fa-university',
        'cash'           => 'fa-money-bill-wave',
        'mobile_banking' => 'fa-mobile-alt',
        'crypto'         => 'fa-coins',
        'receivable'     => 'fa-hand-holding-usd',
        default          => 'fa-wallet',
    };
}

function accountTypeLabel(string $type): string {
    return match ($type) {
        'bank'           => 'Bank',
        'cash'           => 'Cash',
        'mobile_banking' => 'Mobile Banking',
        'crypto'         => 'Crypto',
        'receivable'     => 'Receivable',
        default          => 'Other',
    };
}

function daysUntil(?string $date): ?int {
    if (!$date) return null;
    $diff = (new DateTime())->diff(new DateTime($date));
    return (new DateTime($date)) >= new DateTime() ? (int) $diff->days : -(int) $diff->days;
}

function maturityBadge(?string $date): string {
    if (!$date) return '';
    $days = daysUntil($date);
    if ($days === null) return '';
    if ($days < 0)      return '<span class="maturity-badge overdue"><i class="fas fa-exclamation-circle"></i> Overdue ' . abs($days) . 'd</span>';
    if ($days <= 30)    return '<span class="maturity-badge soon"><i class="fas fa-clock"></i> ' . $days . 'd left</span>';
    return '<span class="maturity-badge ok"><i class="fas fa-check-circle"></i> ' . $days . 'd left</span>';
}
