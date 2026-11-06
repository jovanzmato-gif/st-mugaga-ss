<?php
date_default_timezone_set('Africa/kampala');
// ===== DATABASE SETTINGS =====
// XAMPP's default MySQL login is "root" with no password.
// Change these if you set a password for MySQL later.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'st_mugaga_ss');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ===== ADMIN LOGIN =====
// How many hours an admin stays logged in before being asked to log in again
define('TOKEN_LIFETIME_HOURS', 8);

// ===== UPLOADS =====
// Where passport photos and post images are saved (inside public/)
define('UPLOAD_DIR', __DIR__ . '/../public/uploads/');
define('UPLOAD_URL', '/st-mugaga-ss/public/uploads/');
define('MAX_UPLOAD_BYTES', 50 * 1024 * 1024);   // 50 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// ===== ERROR DISPLAY =====
// Keep errors out of the page, so visitors never see technical details.
// Errors are still logged for you to read.
ini_set('display_errors', '0');
error_reporting(E_ALL);