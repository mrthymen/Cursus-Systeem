<?php
/**
 * Inventijn Certificate Management v4.1.1
 * Fixed version with defensive path handling
 * Previous: v4.1.0 (path issues) → Current: v4.1.1 (defensive)
 * Updated: 2025-06-09
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=certificates.php');
    exit;
}

// Defensive path handling - try multiple possible locations
$possible_paths = [
    '../includes/',
    './includes/',
    'includes/',
    '../../includes/'
];

$template_included = false;
$config_included = false;

// Try to find and include admin_template.php
foreach ($possible_paths as $path) {
    if (file_exists($path . 'admin_template.php') && !$template_included) {
        require_once $path . 'admin_template.php';
        $template_included = true;
        break;
    }
}

// Try to find and include config.php
foreach ($possible_paths as $path) {
    if (file_exists($path . 'config.php') && !$config_included) {
        require_once $path . 'config.php';
        $config_included = true;
        break;
    }
}

// Fallback if unified template not available
if (!$template_included || !function_exists('renderAdminHeader')) {
    // Minimal fallback HTML
    echo '<!DOCTYPE html>
    <html lang="nl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Certificate Management - Inventijn Admin</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body { font-family: Arial, sans-serif; margin: 2rem; background: #f5f5f5; }
            .header { background: #3e5cc6; color: white; padding: 1rem; margin-bottom: 2rem; border-radius: 0.5rem; }
            .nav a { color: white; margin-right: 1rem; text-decoration: none; }
            .nav a:hover { text-decoration: underline; }
            .content { background: white; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .btn { background: #3e5cc6; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.25rem; cursor: pointer; margin-right: 0.5rem; }
            .btn:hover { background: #2d4aa7; }
            .certificate-item { background: #f8f9fa; padding: 1rem; margin: 1rem 0; border-radius: 0.25rem; border-left: 4px solid #3e5cc6; }
            .success { background: #d4edda; color: #155724; padding: 1rem; border-radius: 0.25rem; margin: 1rem 0; }
            .error { background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 0.25rem; margin: 1rem 0; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><i class="fas fa-certificate"></i> Inventijn Certificate Management</h1>
            <div class="nav">
                <a href