<?php
require_once __DIR__ . '/../helpers.php';

// Called by api/index.php for every route that starts with "auth"
function handle_auth(string $method, array $parts): void
{
    // POST /api/auth/login is the only auth route
    if ($method === 'POST' && ($parts[1] ?? '') === 'login') {
        login();
    }

    json_error('Method not allowed', 405);
}

// Checks the username and password, then issues a login token
function login(): void
{
    $data = request_data();

    $username = clean_text($data, 'username');
    $password = (string)($data['password'] ?? '');

    if ($username === '' || $password === '') {
        json_error('Username and password are required');
    }

    // Find the admin by username
    $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    // Check the password against the stored hash.
    // The same message is used for "no such user" and "wrong password",
    // so an attacker can't tell which usernames exist.
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        json_error('Invalid username or password', 401);
    }

    // Create a random token: 32 random bytes written as 64 hexadecimal characters
    $token = bin2hex(random_bytes(32));

    // Work out when the token expires, using the lifetime from config.php
    $expires = date('Y-m-d H:i:s', time() + TOKEN_LIFETIME_HOURS * 3600);

    // Save the token so the server can recognise it later
    $stmt = db()->prepare(
        'INSERT INTO admin_tokens (token, admin_id, expires_at) VALUES (?, ?, ?)'
    );
    $stmt->execute([$token, $admin['id'], $expires]);

    // Clean up tokens that have already expired, so the table doesn't grow forever
    db()->exec('DELETE FROM admin_tokens WHERE expires_at < NOW()');

    json_response([
        'token'    => $token,
        'username' => $admin['username'],
    ]);
}