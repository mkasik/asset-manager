<?php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'assetmanager');
define('DB_USER', getenv('DB_USER') ?: 'your_db_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_db_password');
define('SITE_URL', '/tools/asset-manager');
define('SITE_NAME', 'Asset Manager');
define('TIMEZONE', 'Asia/Dhaka');
define('ANTHROPIC_API_KEY', getenv('ANTHROPIC_API_KEY') ?: '');

date_default_timezone_set(TIMEZONE);
