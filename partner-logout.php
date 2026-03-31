<?php
require_once __DIR__ . '/includes/functions.php';

logout_partner_user();
header('Location: partner-login.php?logged_out=1');
exit;
