<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../uploads.php';
require_once __DIR__ . '/../grading.php';
require_once __DIR__ . '/../entries.php';

// Allowed values
const CATEGORIES = ['news', 'event', 'announcement'];
const LEVELS     = ['O Level', 'A Level'];
const TERMS      = ['Term 1', 'Term 2', 'Term 3'];

// Called by api/index.php for every route that starts with "admin"
function handle_admin(string $method, array $parts): void
{
    require_admin();

    $section = $parts[1] ?? '';
    $id = isset($parts[2]) ? (int)$parts[2] : null;

    // ===== POSTS =====
    if ($section === 'posts') {
        if ($method === 'GET' && $id === null)    list_all_posts();
        if ($method === 'POST' && $id === null)   create_post();
        if ($method === 'PUT' && $id !== null)    update_post($id);
        if ($method === 'DELETE' && $id !== null) delete_post($id);
    }

    // ===== SUBJECTS =====
    if ($section === 'subjects') {
        if ($method === 'POST' && $id === null)   create_subject();
        if ($method === 'DELETE' && $id !== null) delete_subject($id);
    }

    // ===== APPLICATIONS =====
    if ($section === 'applications') {
        if ($method === 'GET' && $id === null)    list_applications();
        if ($method === 'PATCH' && $id !== null)  set_application_status($id);
    }

    // ===== STUDENTS =====
    if ($section === 'students') {
        if ($method === 'GET' && $id === null) {
            list_students();
        }
        if ($method === 'PATCH' && $id !== null) {
            update_student_details($id);   // from entries.php
        }
    }

    // ===== RESULTS (old simple version) =====
    if ($section === 'results' && $method === 'POST') {
        save_result();
    }

    // ===== ENTRIES (detailed version from entries.php) =====
    if ($section === 'entries') {
        if ($method === 'GET') {
            list_entries();
        }
        if ($method === 'POST') {
            save_subject_entry();
        }
    }

    // ===== TERM DETAILS =====
    if ($section === 'term-details' && $method === 'POST') {
        save_term_details();
    }

    // ===== REPORTS =====
    if ($section === 'reports' && $method === 'GET') {
        student_report();
    }

    json_error('Endpoint not found', 404);
}

function flag($value): int
{
    return (string)$value === '0' ? 0 : 1;
}


// ===== POSTS =====

function list_all_posts(): void
{
    $stmt = db()->query('SELECT * FROM posts ORDER BY created_at DESC');
    json_response($stmt->fetchAll());
}

function create_post(): void
{
    $data = request_data();

    $title     = clean_text($data, 'title');
    $body      = clean_text($data, 'body');
    $category  = clean_text($data, 'category') ?: 'news';
    $published = flag($data['is_published'] ?? '1');

    if ($title === '' || $body === '') {
        json_error('Title and body are required');
    }
    if (!in_array($category, CATEGORIES, true)) {
        json_error('Invalid category');
    }

    $image = save_upload('image');

    $stmt = db()->prepare(
        'INSERT INTO posts (title, body, category, image_url, is_published)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$title, $body, $category, $image, $published]);

    json_response([
        'id' => (int)db()->lastInsertId(),
        'message' => 'Post saved',
    ], 201);
}

function update_post(int $id): void
{
    $stmt = db()->prepare('SELECT * FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    $post = $stmt->fetch();

    if (!$post) {
        json_error('Post not found', 404);
    }

    $data = request_data();

    $title = clean_text($data, 'title') ?: $post['title'];
    $body  = clean_text($data, 'body') ?: $post['body'];

    $category = clean_text($data, 'category');
    if (!in_array($category, CATEGORIES, true)) {
        $category = $post['category'];
    }

    $published = array_key_exists('is_published', $data)
        ? flag($data['is_published'])
        : (int)$post['is_published'];

    $stmt = db()->prepare(
        'UPDATE posts SET title = ?, body = ?, category = ?, is_published = ?
         WHERE id = ?'
    );
    $stmt->execute([$title, $body, $category, $published, $id]);

    json_response(['message' => 'Post updated']);
}

function delete_post(int $id): void
{
    $stmt = db()->prepare('DELETE FROM posts WHERE id = ?');
    $stmt->execute([$id]);

    json_response(['message' => 'Post deleted']);
}


// ===== SUBJECTS =====

function create_subject(): void
{
    $data = request_data();

    $name        = clean_text($data, 'name');
    $level       = clean_text($data, 'level');
    $description = clean_text($data, 'description');

    if ($name === '' || !in_array($level, LEVELS, true)) {
        json_error('Subject name and a valid level are required');
    }

    $stmt = db()->prepare(
        'INSERT INTO subjects (name, level, description) VALUES (?, ?, ?)'
    );
    $stmt->execute([$name, $level, $description === '' ? null : $description]);

    json_response([
        'id' => (int)db()->lastInsertId(),
        'message' => 'Subject added',
    ], 201);
}

function delete_subject(int $id): void
{
    $stmt = db()->prepare('DELETE FROM subjects WHERE id = ?');
    $stmt->execute([$id]);

    json_response(['message' => 'Subject deleted']);
}


// ===== APPLICATIONS =====

function list_applications(): void
{
    $sql = 'SELECT a.*, c.name AS desired_class
            FROM applications a
            JOIN classes c ON c.id = a.desired_class_id';
    $params = [];

    if (!empty($_GET['status'])) {
        $sql .= ' WHERE a.status = ?';
        $params[] = $_GET['status'];
    }

    $sql .= ' ORDER BY a.submitted_at DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $apps = $stmt->fetchAll();

    // Attach documents for each application (if the table exists)
    foreach ($apps as &$app) {
        try {
            $docStmt = db()->prepare(
                'SELECT doc_type, file_path FROM application_documents WHERE application_id = ?'
            );
            $docStmt->execute([$app['id']]);
            $app['documents'] = $docStmt->fetchAll();
        } catch (Throwable $e) {
            $app['documents'] = [];
        }
    }

    json_response($apps);
}

function set_application_status(int $id): void
{
    $data = request_data();
    $status = clean_text($data, 'status');

    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        json_error('Invalid status');
    }

    $stmt = db()->prepare('SELECT * FROM applications WHERE id = ?');
    $stmt->execute([$id]);
    $app = $stmt->fetch();

    if (!$app) {
        json_error('Application not found', 404);
    }

    $stmt = db()->prepare('UPDATE applications SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);

    if ($status === 'approved') {
        $year = date('Y', strtotime($app['submitted_at']));
        $admissionNo = 'ST-MUG-' . $year . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);

        $stmt = db()->prepare(
            'INSERT INTO students
                (admission_no, full_name, gender, date_of_birth,
                 parent_contact, class_id, passport_photo)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name), class_id = VALUES(class_id)'
        );
        $stmt->execute([
            $admissionNo,
            $app['student_full_name'],
            $app['gender'],
            $app['date_of_birth'],
            $app['parent_contact'],
            $app['desired_class_id'],
            $app['passport_photo'],
        ]);
    }

    json_response(['message' => "Application $status"]);
}


// ===== STUDENTS =====

function list_students(): void
{
    $search  = '%' . trim($_GET['search'] ?? '') . '%';
    $classId = (int)($_GET['class_id'] ?? 0);

    $sql = 'SELECT s.id, s.admission_no, s.full_name, s.class_id, s.gender,
                   s.lin, s.stream, s.house, s.passport_photo,
                   c.name AS class_name, c.section
            FROM students s
            JOIN classes c ON c.id = s.class_id
            WHERE (s.full_name LIKE ? OR s.admission_no LIKE ?)';
    $params = [$search, $search];

    if ($classId > 0) {
        $sql .= ' AND s.class_id = ?';
        $params[] = $classId;
    }

    $sql .= ' ORDER BY s.full_name LIMIT 50';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_response($stmt->fetchAll());
}


// ===== RESULTS (old simple version) =====

function save_result(): void
{
    $data = request_data();

    $studentId = (int)($data['student_id'] ?? 0);
    $classId   = (int)($data['class_id'] ?? 0);
    $subjectId = (int)($data['subject_id'] ?? 0);
    $term      = clean_text($data, 'term');
    $year      = (int)($data['year'] ?? 0);
    $marksText = clean_text($data, 'marks');

    if ($studentId <= 0 || $classId <= 0 || $subjectId <= 0 ||
        !in_array($term, TERMS, true) || $year < 2000 || $year > 2100) {
        json_error('Check the student, class, term, year, and subject');
    }

    if (!is_numeric($marksText) || $marksText < 0 || $marksText > 100) {
        json_error('Marks must be a number from 0 to 100');
    }

    $marks  = (float)$marksText;
    $grade  = grade_for($marks);

    $stmt = db()->prepare(
        'INSERT INTO results
            (student_id, class_id, term, year, subject_id, marks, grade, remark)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            class_id = VALUES(class_id), marks = VALUES(marks),
            grade = VALUES(grade), remark = VALUES(remark)'
    );
    $stmt->execute([
        $studentId, $classId, $term, $year, $subjectId,
        $marks, $grade, remark_for($grade),
    ]);

    json_response([
        'grade'   => $grade,
        'message' => "Result saved (grade $grade)",
    ], 201);
}


// ===== REPORT CARDS (full version matching frontend) =====

function student_report(): void
{
    $studentId = (int)($_GET['student_id'] ?? 0);
    $classId   = (int)($_GET['class_id'] ?? 0);
    $term      = $_GET['term'] ?? '';
    $year      = (int)($_GET['year'] ?? 0);

    if ($studentId <= 0 || $classId <= 0 || !in_array($term, TERMS, true) || $year <= 0) {
        json_error('Student, class, term, and year are required');
    }

    $pdo = db();

    // Student + class section
    $stmt = $pdo->prepare(
        'SELECT s.id, s.admission_no, s.full_name, s.gender, s.lin, s.stream, s.house,
                s.passport_photo, s.class_id, c.name AS class_name, c.section
         FROM students s
         JOIN classes c ON c.id = s.class_id
         WHERE s.id = ? AND s.class_id = ?'
    );
    $stmt->execute([$studentId, $classId]);
    $student = $stmt->fetch();

    if (!$student) {
        json_error('Student not found in this class', 404);
    }

    $isALevel = ($student['section'] === 'A Level');

    // Term details
    $termStmt = $pdo->prepare(
        'SELECT * FROM term_reports WHERE student_id = ? AND term = ? AND year = ?'
    );
    $termStmt->execute([$studentId, $term, $year]);
    $termDetails = $termStmt->fetch() ?: null;

    // Results
    $stmt = $pdo->prepare(
        'SELECT r.id AS result_id, r.subject_id, sub.name AS subject, sub.level,
                r.marks, r.grade, r.remark, r.teacher_name,
                r.formative_1, r.formative_2, r.formative_3, r.eot_score,
                r.generic_skills
         FROM results r
         JOIN subjects sub ON sub.id = r.subject_id
         WHERE r.student_id = ? AND r.term = ? AND r.year = ?
         ORDER BY sub.name'
    );
    $stmt->execute([$studentId, $term, $year]);
    $rows = $stmt->fetchAll();

    if (!$rows) {
        json_error('No results recorded for this term and year', 404);
    }

    $subjects = [];

    if ($isALevel) {
        $totals = [];
        foreach ($rows as $row) {
            $f1  = $row['formative_1'] !== null ? (float)$row['formative_1'] : null;
            $f2  = $row['formative_2'] !== null ? (float)$row['formative_2'] : null;
            $f3  = $row['formative_3'] !== null ? (float)$row['formative_3'] : null;
            $eot = $row['eot_score']   !== null ? (float)$row['eot_score']   : null;

            $formativeAvg = null;
            $formativePct = null;
            $eotPct = null;
            $total = null;

            if ($f1 !== null && $f2 !== null && $f3 !== null && $eot !== null) {
                $formativeAvg = ($f1 + $f2 + $f3) / 3;
                $formativePct = round(($formativeAvg / 3) * 20, 2);
                $eotPct       = round($eot * 0.8, 2);
                $total        = round($formativePct + $eotPct, 2);
                $totals[]     = $total;
            }

            $subjects[] = [
                'subject'      => $row['subject'],
                'f1'           => $f1,
                'f2'           => $f2,
                'f3'           => $f3,
                'formativeAvg' => $formativeAvg,
                'formativePct' => $formativePct,
                'eot'          => $eot,
                'eotPct'       => $eotPct,
                'total'        => $total,
                'grade'        => $row['grade'],
                'remark'       => $row['remark'],
                'teacher'      => $row['teacher_name'],
            ];
        }

        $average = $totals ? round(array_sum($totals) / count($totals), 2) : null;
        $summary = [
            'average' => $average,
            'grade'   => $average !== null ? grade_for($average) : null,
        ];

        $gradeScale = [
            ['grade' => 'A', 'min_mark' => 80],
            ['grade' => 'B', 'min_mark' => 70],
            ['grade' => 'C', 'min_mark' => 60],
            ['grade' => 'D', 'min_mark' => 50],
            ['grade' => 'E', 'min_mark' => 40],
            ['grade' => 'F', 'min_mark' => 0],
        ];
    } else {
        // O Level
        $summary = null;
        $gradeScale = null;

        foreach ($rows as $row) {
            $topicStmt = $pdo->prepare(
                'SELECT topic, score FROM competency_scores WHERE result_id = ? ORDER BY id'
            );
            $topicStmt->execute([$row['result_id']]);
            $topics = $topicStmt->fetchAll();

            $subjects[] = [
                'subject'        => $row['subject'],
                'topics'         => array_map(fn($t) => [
                    'topic' => $t['topic'],
                    'score' => $t['score'] !== null ? (float)$t['score'] : null,
                ], $topics),
                'generic_skills' => $row['generic_skills'],
                'remark'         => $row['remark'],
                'teacher'        => $row['teacher_name'],
                'grade'          => $row['grade'],
            ];
        }
    }

    json_response([
        'section'     => $student['section'],
        'student'     => $student,
        'term'        => $term,
        'year'        => $year,
        'termDetails' => $termDetails,
        'subjects'    => $subjects,
        'summary'     => $summary ?? null,
        'gradeScale'  => $gradeScale ?? null,
    ]);
}