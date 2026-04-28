<?php
require_once __DIR__ . '/includes/functions.php';

// Sign the partner out and redirect back to the dedicated partner login page.
logout_partner_user();
header('Location: partner-login.php?logged_out=1');
exit;
