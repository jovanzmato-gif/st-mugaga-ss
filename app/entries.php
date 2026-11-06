<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/grading.php';

// Turns an O Level topic average (0 to 3) into a rating word
function descriptor_for(float $score): string
{
    if ($score >= 2.5) return 'Outstanding';
    if ($score >= 1.5) return 'Moderate';
    if ($score >= 0.9) return 'Basic';
    return 'Below basic';
}

// Returns a number from the request, or null when the field is empty
function number_or_null(array $data, string $key): ?float
{
    $value = trim((string)($data[$key] ?? ''));
    return $value === '' ? null : (float)$value;
}

// Returns text from the request, or null when the field is empty
function text_or_null(array $data, string $key): ?string
{
    $value = trim((string)($data[$key] ?? ''));
    return $value === '' ? null : $value;
}

// Finds the student and their class section (O Level or A Level)
function student_with_section(int $studentId): array
{
    $stmt = db()->prepare(
        'SELECT s.id, s.class_id, c.section
         FROM students s
         JOIN classes c ON c.id = s.class_id
         WHERE s.id = ?'
    );
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        json_error('Student not found', 404);
    }
    return $student;
}

// Inserts or updates one subject row in results, and returns its id
function save_result_row(array $student, int $subjectId, string $term, int $year, array $f): int
{
    $pdo = db();

    $pdo->prepare(
        'INSERT INTO results
            (student_id, class_id, term, year, subject_id, marks, grade, remark,
             formative_1, formative_2, formative_3, eot_score, generic_skills, teacher_name)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            class_id = VALUES(class_id), marks = VALUES(marks), grade = VALUES(grade),
            remark = VALUES(remark), formative_1 = VALUES(formative_1),
            formative_2 = VALUES(formative_2), formative_3 = VALUES(formative_3),
            eot_score = VALUES(eot_score), generic_skills = VALUES(generic_skills),
            teacher_name = VALUES(teacher_name)'
    )->execute([
        $student['id'], $student['class_id'], $term, $year, $subjectId,
        $f['marks'], $f['grade'], $f['remark'],
        $f['formative_1'], $f['formative_2'], $f['formative_3'], $f['eot_score'],
        $f['generic_skills'], $f['teacher_name'],
    ]);

    $stmt = $pdo->prepare(
        'SELECT id FROM results WHERE student_id = ? AND term = ? AND year = ? AND subject_id = ?'
    );
    $stmt->execute([$student['id'], $term, $year, $subjectId]);
    return (int)$stmt->fetchColumn();
}

// POST /api/admin/entries
// Saves one subject. O Level students get topic scores. A Level students get formative and EOT scores.
function save_subject_entry(): void
{
    $data = request_data();

    $studentId = (int)($data['student_id'] ?? 0);
    $subjectId = (int)($data['subject_id'] ?? 0);
    $term      = clean_text($data, 'term');
    $year      = (int)($data['year'] ?? 0);

    if ($studentId <= 0 || $subjectId <= 0 || !in_array($term, TERMS, true) ||
        $year < 2000 || $year > 2100) {
        json_error('Check the student, subject, term, and year');
    }

    $student = student_with_section($studentId);
    $teacher = text_or_null($data, 'teacher_name');
    $remark  = text_or_null($data, 'remark');

    // ---- O Level: topic scores, rated with descriptors ----
    if ($student['section'] === 'O Level') {
        $topics = $data['topics'] ?? [];
        if (!is_array($topics)) {
            json_error('Topics were sent in the wrong format');
        }

        $clean = [];
        foreach ($topics as $t) {
            $name  = trim((string)($t['topic'] ?? ''));
            $score = $t['score'] ?? '';

            if ($name === '' && (string)$score === '') {
                continue;   // skip empty rows
            }
            if ($name === '' || !is_numeric($score) || $score < 0 || $score > 3) {
                json_error('Each topic needs a name and a score from 0 to 3');
            }
            $clean[] = ['topic' => $name, 'score' => (float)$score];
        }

        if (!$clean) {
            json_error('Add at least one topic with a score');
        }

        $average    = array_sum(array_column($clean, 'score')) / count($clean);
        $descriptor = descriptor_for($average);

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $resultId = save_result_row($student, $subjectId, $term, $year, [
                'marks' => null,
                'grade' => $descriptor,
                'remark' => $remark,
                'formative_1' => null, 'formative_2' => null, 'formative_3' => null,
                'eot_score' => null,
                'generic_skills' => text_or_null($data, 'generic_skills'),
                'teacher_name' => $teacher,
            ]);

            // Replace the topics for this subject with the new list
            $pdo->prepare('DELETE FROM competency_scores WHERE result_id = ?')
                ->execute([$resultId]);

            $insert = $pdo->prepare(
                'INSERT INTO competency_scores (result_id, topic, score) VALUES (?, ?, ?)'
            );
            foreach ($clean as $t) {
                $insert->execute([$resultId, $t['topic'], $t['score']]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        json_response(['message' => "Saved. Subject rating: $descriptor"], 201);
    }

    // ---- A Level: three formative scores and an end of term exam ----
    $f1  = number_or_null($data, 'formative_1');
    $f2  = number_or_null($data, 'formative_2');
    $f3  = number_or_null($data, 'formative_3');
    $eot = number_or_null($data, 'eot_score');

    foreach ([$f1, $f2, $f3] as $score) {
        if ($score === null || $score < 0 || $score > 3) {
            json_error('Each formative score must be from 0 to 3');
        }
    }
    if ($eot === null || $eot < 0 || $eot > 100) {
        json_error('The end of term score must be from 0 to 100');
    }

    // Same working as the A Level card: formative average scaled to 20%,
    // end of term scaled to 80%
    $formativeAverage = ($f1 + $f2 + $f3) / 3;
    $total = round(($formativeAverage / 3) * 20 + $eot * 0.8, 2);
    $grade = grade_for($total);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        save_result_row($student, $subjectId, $term, $year, [
            'marks' => $total,
            'grade' => $grade,
            'remark' => $remark ?? remark_for($grade),
            'formative_1' => $f1, 'formative_2' => $f2, 'formative_3' => $f3,
            'eot_score' => $eot,
            'generic_skills' => null,
            'teacher_name' => $teacher,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_response(['message' => "Saved. Total $total, grade $grade", 'grade' => $grade], 201);
}

// POST /api/admin/term-details
// Saves attendance, comments, fees, dates, and project work for one term
function save_term_details(): void
{
    $data = request_data();

    $studentId = (int)($data['student_id'] ?? 0);
    $term      = clean_text($data, 'term');
    $year      = (int)($data['year'] ?? 0);

    if ($studentId <= 0 || !in_array($term, TERMS, true) || $year < 2000 || $year > 2100) {
        json_error('Check the student, term, and year');
    }
    student_with_section($studentId);   // confirms the student exists

    // Dates must be YYYY-MM-DD, or left empty
    $date = function (string $key) use ($data): ?string {
        $value = text_or_null($data, $key);
        if ($value !== null && !DateTime::createFromFormat('Y-m-d', $value)) {
            json_error('Dates must be in the format YYYY-MM-DD');
        }
        return $value;
    };

    $projectScore = number_or_null($data, 'project_score');
    if ($projectScore !== null && ($projectScore < 0 || $projectScore > 10)) {
        json_error('Project score must be from 0 to 10');
    }

    $int = fn($v) => $v === null ? null : (int)$v;
    $daysPresent = $int(number_or_null($data, 'days_present'));
    $daysAbsent  = $int(number_or_null($data, 'days_absent'));
    $feesBalance = number_or_null($data, 'fees_balance');
    $feesNext    = number_or_null($data, 'fees_next_term');

    db()->prepare(
        'INSERT INTO term_reports
            (student_id, term, year, days_present, days_absent,
             class_teacher_comment, headteacher_comment, term_ended_on, next_term_begins,
             fees_balance, fees_next_term, project_title, project_score,
             project_grade, project_remark, project_teacher)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            days_present = VALUES(days_present), days_absent = VALUES(days_absent),
            class_teacher_comment = VALUES(class_teacher_comment),
            headteacher_comment = VALUES(headteacher_comment),
            term_ended_on = VALUES(term_ended_on), next_term_begins = VALUES(next_term_begins),
            fees_balance = VALUES(fees_balance), fees_next_term = VALUES(fees_next_term),
            project_title = VALUES(project_title), project_score = VALUES(project_score),
            project_grade = VALUES(project_grade), project_remark = VALUES(project_remark),
            project_teacher = VALUES(project_teacher)'
    )->execute([
        $studentId, $term, $year, $daysPresent, $daysAbsent,
        text_or_null($data, 'class_teacher_comment'),
        text_or_null($data, 'headteacher_comment'),
        $date('term_ended_on'), $date('next_term_begins'),
        $feesBalance, $feesNext,
        text_or_null($data, 'project_title'), $projectScore,
        text_or_null($data, 'project_grade'),
        text_or_null($data, 'project_remark'),
        text_or_null($data, 'project_teacher'),
    ]);

    json_response(['message' => 'Term details saved']);
}

// PATCH /api/admin/students/5
// Saves the LIN, stream, and house shown on the report card
function update_student_details(int $id): void
{
    $data = request_data();

    $check = db()->prepare('SELECT id FROM students WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        json_error('Student not found', 404);
    }

    db()->prepare('UPDATE students SET lin = ?, stream = ?, house = ? WHERE id = ?')
        ->execute([
            text_or_null($data, 'lin'),
            text_or_null($data, 'stream'),
            text_or_null($data, 'house'),
            $id,
        ]);

    json_response(['message' => 'Student details saved']);
}

// GET /api/admin/entries?student_id=3&term=Term 2&year=2026
// Returns the saved subjects and term details, so the forms can show them
function list_entries(): void
{
    $studentId = (int)($_GET['student_id'] ?? 0);
    $term      = $_GET['term'] ?? '';
    $year      = (int)($_GET['year'] ?? 0);

    if ($studentId <= 0 || !in_array($term, TERMS, true) || $year <= 0) {
        json_error('Student, term, and year are required');
    }

    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT sub.name AS subject, sub.level, r.marks, r.grade, r.remark, r.teacher_name
         FROM results r
         JOIN subjects sub ON sub.id = r.subject_id
         WHERE r.student_id = ? AND r.term = ? AND r.year = ?
         ORDER BY sub.name'
    );
    $stmt->execute([$studentId, $term, $year]);

    $termStmt = $pdo->prepare(
        'SELECT * FROM term_reports WHERE student_id = ? AND term = ? AND year = ?'
    );
    $termStmt->execute([$studentId, $term, $year]);

    json_response([
        'subjects' => $stmt->fetchAll(),
        'term'     => $termStmt->fetch() ?: null,
    ]);
}