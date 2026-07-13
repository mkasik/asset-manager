<?php
/**
 * Asset Manager – Database Installer
 * Run this ONCE then DELETE or RENAME this file.
 */
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'assetmanager');
define('DB_USER', getenv('DB_USER') ?: 'your_db_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_db_password');
define('SITE_URL', '/tools/asset-manager');

$errors  = [];
$success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $success[] = 'Database connection successful.';

        // Run schema
        $sql = "
        SET NAMES utf8mb4;
        SET foreign_key_checks = 0;

        CREATE TABLE IF NOT EXISTS `users` (
            `id`         int(11) NOT NULL AUTO_INCREMENT,
            `username`   varchar(50) NOT NULL,
            `email`      varchar(100) NOT NULL,
            `password`   varchar(255) NOT NULL,
            `full_name`  varchar(100) DEFAULT NULL,
            `role`       enum('admin','viewer') NOT NULL DEFAULT 'viewer',
            `status`     tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `accounts` (
            `id`          int(11) NOT NULL AUTO_INCREMENT,
            `name`        varchar(100) NOT NULL,
            `type`        enum('bank','cash','mobile_banking','crypto','receivable','other') NOT NULL DEFAULT 'bank',
            `nominee_name` varchar(100) DEFAULT NULL,
            `balance`     decimal(15,2) NOT NULL DEFAULT 0.00,
            `description` text DEFAULT NULL,
            `status`      tinyint(1) NOT NULL DEFAULT 1,
            `created_at`  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `categories` (
            `id`          int(11) NOT NULL AUTO_INCREMENT,
            `name`        varchar(100) NOT NULL,
            `description` text DEFAULT NULL,
            `created_at`  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `investments` (
            `id`                 int(11) NOT NULL AUTO_INCREMENT,
            `name`               varchar(150) DEFAULT NULL,
            `category_id`        int(11) NOT NULL,
            `source_account_id`  int(11) NOT NULL,
            `profit_account_id`  int(11) DEFAULT NULL,
            `amount`             decimal(15,2) NOT NULL,
            `profit_type`        enum('percent','fixed') DEFAULT 'percent',
            `profit_rate`        decimal(10,4) DEFAULT 0.0000,
            `expected_profit`    decimal(15,2) DEFAULT 0.00,
            `actual_profit`      decimal(15,2) DEFAULT 0.00,
            `start_date`         date NOT NULL,
            `maturity_date`      date DEFAULT NULL,
            `status`             enum('active','matured','renewed','withdrawn') NOT NULL DEFAULT 'active',
            `notes`              text DEFAULT NULL,
            `parent_id`          int(11) DEFAULT NULL,
            `created_by`         int(11) DEFAULT NULL,
            `created_at`         timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`         timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `category_id` (`category_id`),
            KEY `source_account_id` (`source_account_id`),
            CONSTRAINT `fk_inv_cat`  FOREIGN KEY (`category_id`)       REFERENCES `categories`(`id`),
            CONSTRAINT `fk_inv_acct` FOREIGN KEY (`source_account_id`) REFERENCES `accounts`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `transactions` (
            `id`            int(11) NOT NULL AUTO_INCREMENT,
            `type`          enum('deposit','withdrawal','investment','profit','transfer_in','transfer_out','renewal') NOT NULL,
            `account_id`    int(11) NOT NULL,
            `amount`        decimal(15,2) NOT NULL,
            `balance_after` decimal(15,2) DEFAULT NULL,
            `investment_id` int(11) DEFAULT NULL,
            `description`   text DEFAULT NULL,
            `is_auto`       tinyint(1) DEFAULT 0,
            `created_by`    int(11) DEFAULT NULL,
            `created_at`    timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `account_id` (`account_id`),
            CONSTRAINT `fk_tx_acct` FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        SET foreign_key_checks = 1;
        ";

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
            if ($query) $pdo->exec($query);
        }
        $success[] = 'Tables created successfully.';

        // Create default admin user
        $adminUser = trim($_POST['admin_username'] ?? 'admin');
        $adminPass = trim($_POST['admin_password'] ?? '');
        $adminEmail= trim($_POST['admin_email']    ?? 'admin@example.com');
        $adminName = trim($_POST['admin_fullname'] ?? 'Administrator');

        if (!$adminUser || !$adminPass) {
            $errors[] = 'Admin username and password are required.';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE username=?");
            $check->execute([$adminUser]);
            if ($check->fetch()) {
                $success[] = 'Admin user already exists, skipped.';
            } else {
                $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?,?,?,?,?)")
                    ->execute([$adminUser, $adminEmail, $hash, $adminName, 'admin']);
                $success[] = 'Admin user "' . htmlspecialchars($adminUser) . '" created.';
            }
        }

    } catch (PDOException $e) {
        $errors[] = 'DB Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install – Asset Manager</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body class="login-page">
<div class="login-card" style="max-width:500px">
    <div class="login-card-top">
        <div class="login-logo-icon"><i class="fas fa-database"></i></div>
        <h1>Asset Manager</h1>
        <p>Database Installation</p>
    </div>
    <div class="login-card-body">
        <?php if ($errors): ?>
        <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger py-2 mb-2"><i class="fas fa-times-circle me-2"></i><?= $e ?></div>
        <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($success): ?>
        <?php foreach ($success as $s): ?>
        <div class="alert alert-success py-2 mb-2"><i class="fas fa-check-circle me-2"></i><?= $s ?></div>
        <?php endforeach; ?>
        <?php if (!$errors): ?>
        <div class="alert alert-warning py-2 mb-3 fs-13">
            <strong><i class="fas fa-exclamation-triangle me-1"></i> Security:</strong>
            Delete or rename <code>install.php</code> after setup!
        </div>
        <a href="<?= SITE_URL ?>/login.php" class="btn btn-success w-100">Go to Login &rarr;</a>
        <?php endif; ?>
        <?php else: ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Admin Full Name</label>
                <input type="text" name="admin_fullname" class="form-control" value="Mk Asik">
            </div>
            <div class="mb-3">
                <label class="form-label">Admin Username <span class="text-danger">*</span></label>
                <input type="text" name="admin_username" class="form-control" value="admin" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Admin Email <span class="text-danger">*</span></label>
                <input type="email" name="admin_email" class="form-control" value="pagemanagerbd@gmail.com" required>
            </div>
            <div class="mb-4">
                <label class="form-label">Admin Password <span class="text-danger">*</span></label>
                <input type="password" name="admin_password" class="form-control" placeholder="Min 6 chars" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-rocket me-2"></i>Install Database & Create Admin
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
