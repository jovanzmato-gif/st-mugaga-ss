<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../uploads.php';

// Called by api/index.php for every route that starts with posts, subjects,
// classes, or applications. It picks the right function for the method and path.
function handle_public(string $method, array $parts): void
{
    $resource = $parts[0];

    if ($method === 'GET' && $resource === 'posts') {
        list_posts();
    }

    if ($method === 'GET' && $resource === 'subjects') {
        list_subjects();
    }

    if ($method === 'GET' && $resource === 'classes') {
        list_classes();
    }

    if ($method === 'POST' && $resource === 'applications') {
        submit_application();
    }

    // The path matched, but the method isn't allowed for it (for example, DELETE /posts)
    json_error('Method not allowed', 405);
}


// ===== NEWS, EVENTS, AND ANNOUNCEMENTS =====

// GET /api/posts?limit=3 or /api/posts?category=event
// Returns only published posts, newest first
function list_posts(): void
{
    // Convert the limit to a whole number and keep it between 1 and 50
    $limit = (int)($_GET['limit'] ?? 10);
    $limit = max(1, min($limit, 50));

    $sql = 'SELECT id, title, body, category, image_url, created_at
            FROM posts
            WHERE is_published = 1';
    $params = [];

    // Add the category filter only when one was asked for
    if (!empty($_GET['category'])) {
        $sql .= ' AND category = ?';
        $params[] = $_GET['category'];
    }

    // $limit is already a checked whole number, so it's safe to place it directly in the SQL.
    // Placeholders can't be used for LIMIT in all cases, so this is the usual approach.
    $sql .= ' ORDER BY created_at DESC LIMIT ' . $limit;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    json_response($stmt->fetchAll());
}


// ===== SUBJECTS =====

// GET /api/subjects or /api/subjects?level=O Level
// Returns O Level subjects first, then A Level, in alphabetical order inside each level
function list_subjects(): void
{
    $sql = 'SELECT id, name, level, description FROM subjects';
    $params = [];

    if (!empty($_GET['level'])) {
        $sql .= ' WHERE level = ?';
        $params[] = $_GET['level'];
    }

    $sql .= " ORDER BY FIELD(level, 'O Level', 'A Level'), name";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    json_response($stmt->fetchAll());
}


// ===== CLASSES =====

// GET /api/classes
// Returns S1 to S6, used by the dropdowns on the apply and admin pages
function list_classes(): void
{
    $stmt = db()->query('SELECT id, name FROM classes ORDER BY id');
    json_response($stmt->fetchAll());
}


// ===== ONLINE APPLICATION =====

// POST /api/applications
// Receives the form from apply.html, checks each field, and saves it
// POST /api/applications
// Checks the details and documents for the applicant type, then saves the
// application and its documents together, or nothing at all.
function submit_application(): void
{
    $data = request_data();

    $type = clean_text($data, 'applicant_type');
    if (!in_array($type, ['primary_leaver', 'transfer'], true)) {
        json_error('Please choose whether the student is new or transferring');
    }

    $name     = clean_text($data, 'student_full_name');
    $gender   = clean_text($data, 'gender');
    $dob      = clean_text($data, 'date_of_birth');
    $contact  = clean_text($data, 'parent_contact');
    $previous = clean_text($data, 'previous_school');
    $ple      = clean_text($data, 'ple_aggregates');
    $classId  = clean_text($data, 'desired_class_id');

    if ($name === '' || $gender === '' || $dob === '' || $contact === '' ||
        $previous === '' || $classId === '') {
        json_error('Please fill in all required fields');
    }
    if (!in_array($gender, ['Male', 'Female'], true)) {
        json_error('Invalid gender');
    }
    if (!DateTime::createFromFormat('Y-m-d', $dob)) {
        json_error('Invalid date of birth');
    }

    // PLE aggregates are optional, but if given they must be between 4 and 36
    $pleValue = null;
    if ($ple !== '') {
        if (!is_numeric($ple) || $ple < 4 || $ple > 36) {
            json_error('PLE aggregates must be between 4 and 36');
        }
        $pleValue = (int)$ple;
    }

    // Transfer details: only for students moving from another secondary school
    $previousClass = null;
    $reason = null;
    if ($type === 'transfer') {
        $previousClass = clean_text($data, 'previous_class');
        $reason        = clean_text($data, 'reason_for_transfer');
        if ($previousClass === '' || $reason === '') {
            json_error('Please complete the transfer details');
        }
    }

    // Documents required for this applicant type.
    // The keys must match the file input names in apply.html.
    $documents = [
        'passport_photo'    => 'Passport photo',
        'birth_certificate' => 'Birth certificate',
    ];
    if ($type === 'transfer') {
        $documents['transfer_letter'] = 'Transfer letter';
        $documents['previous_report'] = 'Latest report card';
    } else {
        $documents['ple_slip'] = 'PLE result slip';
    }

    // Check every required document is attached before saving anything
    foreach ($documents as $field => $label) {
        if (!has_upload($field)) {
            json_error("Please upload: $label");
        }
    }

    // Check the chosen class exists
    $check = db()->prepare('SELECT id FROM classes WHERE id = ?');
    $check->execute([(int)$classId]);
    if (!$check->fetch()) {
        json_error('Please choose a valid class');
    }

    // Save the application and its documents together.
    // If anything fails, the transaction is rolled back and nothing is kept.
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $photo = save_upload('passport_photo');

        $stmt = $pdo->prepare(
            'INSERT INTO applications
                (applicant_type, student_full_name, gender, date_of_birth, parent_contact,
                 previous_school, ple_aggregates, previous_class, reason_for_transfer,
                 desired_class_id, passport_photo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $type, $name, $gender, $dob, $contact,
            $previous, $pleValue, $previousClass, $reason,
            (int)$classId, $photo,
        ]);
        $appId = (int)$pdo->lastInsertId();

        $docStmt = $pdo->prepare(
            'INSERT INTO application_documents (application_id, doc_type, file_path)
             VALUES (?, ?, ?)'
        );
        foreach ($documents as $field => $label) {
            if ($field === 'passport_photo') {
                continue;   // already saved in the applications table above
            }
            $docStmt->execute([$appId, $field, save_upload($field)]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_response([
        'message' => 'Application submitted successfully. The school will contact you.'
    ], 201);
}