<?php
require_once __DIR__ . '/config.php';

// Returns one shared connection to MySQL.
// The first call creates it. Later calls reuse it, so we don't reconnect each time.
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            // Throw an error when something goes wrong, instead of failing silently
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Return rows as named arrays, e.g. $row['full_name']
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use real prepared statements, which protect against SQL injection
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $pdo;
}