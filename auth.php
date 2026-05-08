<?php
// auth.php — Session guard
// Include this at the top of every page that requires login.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: " . str_repeat("../", substr_count($_SERVER['SCRIPT_NAME'], "/") - 2) . "login.php");
    exit;
}
?>
