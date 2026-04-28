<?php
require_once __DIR__ . '/includes/functions.php';

// Clear both customer and partner sessions before returning the visitor to the home page.
logout_partner_user();
logout_user();
header('Location: Home.php');
exit;
