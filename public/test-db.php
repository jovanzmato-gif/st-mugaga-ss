<?php
require_once __DIR__ . '/../app/db.php';

$count = db()->query('SELECT COUNT(*) AS total FROM classes')->fetch();
echo 'Connected. Classes in database: ' . $count['total'];