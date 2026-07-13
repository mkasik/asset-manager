<?php
require_once __DIR__ . "/auth.php";
header("Location: " . SITE_URL . (isLoggedIn() ? "/dashboard.php" : "/login.php"));
exit;
