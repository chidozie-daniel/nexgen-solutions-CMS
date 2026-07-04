<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once 'includes/auth.php';
Auth::logout();
?>