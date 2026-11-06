<?php
require_once __DIR__ . '/db.php';

// ===== SENDING ANSWERS =====

// Sends data back to the browser as JSON, then stops the script.
// $status is the HTTP status code: 200 means OK, 404 means not found, and so on.
function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Sends an error message in the same format the front end expects: {"message": "..."}
function json_error(string $message, int $status = 400): void
{
    json_response(['message' => $message], $status);
}


// ===== READING REQUESTS =====

// Returns the request body as an array.
// Login and other JSON requests send JSON. Forms send $_POST.
function request_data(): array
{
    $type = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains($type, 'application/json')) {
        $json = json_decode(file_get_contents('php://input'), true);
        return is_array($json) ? $json : [];
    }

    return $_POST;
}

// Returns a trimmed text value, or an empty string if the field is missing
function clean_text(array $data, string $key): string
{
    return trim((string)($data[$key] ?? ''));
}


// ===== ADMIN LOGIN CHECK =====

// Reads the token from the "Authorization: Bearer <token>" header.
// Returns the token text, or null if the header is missing.
function bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    // Some Apache setups hide this header, so check the alternative name too
    if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
        return $matches[1];
    }
    return null;
}

// Stops the request unless a valid, unexpired admin token was sent.
// Call this at the start of every admin route.
// Returns the admin's id and username when the check passes.
function require_admin(): array
{
    $token = bearer_token();

    if ($token === null) {
        json_error('Please log in as admin', 401);
    }

    $stmt = db()->prepare(
        'SELECT a.id, a.username
         FROM admin_tokens t
         JOIN admins a ON a.id = t.admin_id
         WHERE t.token = ? AND t.expires_at > NOW()'
    );
    $stmt->execute([$token]);
    $admin = $stmt->fetch();

    if (!$admin) {
        json_error('Session expired. Please log in again', 401);
    }

    return $admin;
}