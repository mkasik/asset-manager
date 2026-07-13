<?php
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        if (login($username, $password)) {
            header('Location: ' . SITE_URL . '/dashboard.php');
            exit;
        }
        $error = 'Invalid username or password.';
    } else {
        $error = 'Please enter your credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Asset Manager</title>
<link rel="icon" type="image/svg+xml" href="<?= SITE_URL ?>/assets/images/favicon.svg">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body class="login-page">

<div class="login-card">
    <div class="login-card-top">
        <div class="login-logo-icon"><i class="fas fa-dollar-sign"></i></div>
        <h1>Asset Manager</h1>
        <p>Sign in to manage your assets</p>
    </div>

    <div class="login-card-body">
        <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-muted"></i></span>
                    <input type="text" name="username" class="form-control border-start-0" placeholder="Enter username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                    <input type="password" name="password" id="pwdField" class="form-control border-start-0" placeholder="Enter password" required>
                    <button type="button" class="input-group-text bg-light" onclick="togglePwd()">
                        <i class="fas fa-eye text-muted" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-600">
                <i class="fas fa-sign-in-alt me-2"></i>Sign In
            </button>
        </form>

        <p class="text-center text-muted mt-4 mb-0" style="font-size:12px">
            &copy; <?= date('Y') ?> mkasik.com — Personal Asset Manager
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd() {
    const f = document.getElementById('pwdField');
    const i = document.getElementById('eyeIcon');
    if (f.type === 'password') { f.type = 'text'; i.className = 'fas fa-eye-slash text-muted'; }
    else { f.type = 'password'; i.className = 'fas fa-eye text-muted'; }
}
</script>
</body>
</html>
