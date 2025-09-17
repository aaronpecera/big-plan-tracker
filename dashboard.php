<?php
/**
 * BIG PLAN - Dashboard Entry Point
 * Main dashboard for authenticated users
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: /login');
    exit;
}

// User is logged in, show dashboard
include 'views/dashboard.php';
?>