<?php
// This is the only file that handles API requests.
// It loads the helper tools, works out which route was requested, and runs it.
require_once __DIR__ . '/../../app/helpers.php';

// The HTTP method: GET, POST, PUT, PATCH, or DELETE
$method = $_SERVER['REQUEST_METHOD'];

// The rewrite rule in .htaccess puts the route into $_GET['route'].
// For example, /api/admin/posts/5 becomes "admin/posts/5".
// trim() removes any slashes at the start or end.
$path = trim($_GET['route'] ?? '', '/');

// Split the path into pieces. "admin/posts/5" becomes ['admin', 'posts', '5'].
// An empty path becomes an empty list.
$parts = $path === '' ? [] : explode('/', $path);

// The first piece decides which group of routes to use
$resource = $parts[0] ?? '';

switch ($resource) {

    // A simple route to check that the API is running.
    // Open http://localhost:8080/api/health in your browser to test it.
    case 'health':
        json_response([
            'status' => 'ok',
            'time' => date('c'),   // the current time in a standard format
        ]);

    // Public routes: anyone can use these
    case 'posts':
    case 'subjects':
    case 'classes':
    case 'applications':
        require __DIR__ . '/../../app/routes/public.php';
        handle_public($method, $parts);
        break;

    // Login route
    case 'auth':
        require __DIR__ . '/../../app/routes/auth.php';
        handle_auth($method, $parts);
        break;

    // Admin routes: all of these require a valid login token
    case 'admin':
        require __DIR__ . '/../../app/routes/admin.php';
        handle_admin($method, $parts);
        break;

    // Nothing matched the first piece of the path
    default:
        json_error('Endpoint not found', 404);
}