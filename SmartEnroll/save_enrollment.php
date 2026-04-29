<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/enrollment_fields.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'error: invalid request method';
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function normalize_key(string $key): string
{
    $key = preg_replace('/([a-z])([A-Z])/', '$1_$2', $key);
    $key = str_replace(['-', ' '], '_', $key);
    $key = strtolower($key);
    $key = preg_replace('/[^a-z0-9_]/', '', $key);
    return trim($key, '_');
}

try {
    smartenroll_require_role('finance');

    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');
    smartenroll_ensure_student_status_column($conn);

    // Accept both standard form posts and raw JSON payload.
    $input = $_POST;
    if (empty($input)) {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $input = $json;
            }
        }
    }

    if (empty($input)) {
        throw new RuntimeException('No form data received.');
    }

    // Build normalized input map so camelCase and snake_case both work.
    $inputMap = [];
    foreach ($input as $k => $v) {
        if (is_array($v)) {
            $v = implode(', ', $v);
        }
        $value = trim((string)$v);
        $inputMap[(string)$k] = $value;
        $inputMap[normalize_key((string)$k)] = $value;
    }

    // Read actual DB columns.
    $columns = [];
    $colRes = $conn->query("SHOW COLUMNS FROM `enrollments`");
    while ($row = $colRes->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    if (empty($columns)) {
        throw new RuntimeException('Unable to read enrollments table columns.');
    }

    $skip = ['id', 'created_at', 'student_id'];
    $data = [];

    foreach ($columns as $col) {
        if (in_array($col, $skip, true)) {
            continue;
        }

        $normalizedCol = normalize_key($col);
        $value = null;

        if (array_key_exists($col, $inputMap)) {
            $value = $inputMap[$col];
        } elseif (array_key_exists($normalizedCol, $inputMap)) {
            $value = $inputMap[$normalizedCol];
        }

        if ($value !== null && $value !== '') {
            $data[$col] = $value;
        }
    }

    // If no extension name is provided, save blank string instead of NULL.
    if (in_array('learner_ext', $columns, true) && !array_key_exists('learner_ext', $data)) {
        $data['learner_ext'] = '';
    }

    // If medication details is not provided, save blank string instead of NULL.
    if (in_array('medication_details', $columns, true) && !array_key_exists('medication_details', $data)) {
        $data['medication_details'] = '';
    }

    // Auto-compute school year from completion_date when provided (e.g., 2025-2026).
    if (in_array('school_year', $columns, true)) {
        $completionDateRaw = $inputMap['completion_date'] ?? '';
        $ts = $completionDateRaw !== '' ? strtotime($completionDateRaw) : false;

        if ($ts === false) {
            $ts = time();
        }

        $month = (int)date('n', $ts);
        $year = (int)date('Y', $ts);
        $startYear = ($month >= 6) ? $year : ($year - 1);
        $endYear = $startYear + 1;
        $data['school_year'] = $startYear . '-' . $endYear;
    }

    // Auto-set student_status to 'new' if not provided during new enrollment
    if (in_array('student_status', $columns, true) && !array_key_exists('student_status', $data)) {
        $data['student_status'] = 'new';
    }

    if (empty($data)) {
        throw new RuntimeException('No valid fields matched table columns.');
    }

    $conn->begin_transaction();

    $fields = array_keys($data);
    $fieldSql = '`' . implode('`,`', $fields) . '`';
    $placeholderSql = implode(',', array_fill(0, count($fields), '?'));
    $sql = "INSERT INTO `enrollments` ($fieldSql) VALUES ($placeholderSql)";

    $stmt = $conn->prepare($sql);
    $types = str_repeat('s', count($fields));
    $values = array_values($data);

    $bind = [$types];
    foreach ($values as $i => $v) {
        $bind[] = &$values[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    $stmt->execute();
    $stmt->close();

    $newId = (int)$conn->insert_id;
    if ($newId <= 0) {
        throw new RuntimeException('Insert failed to return ID.');
    }

    // Always set student_id after insert: 202600 + sequential number (starts at 1).
    if (in_array('student_id', $columns, true)) {
        $prefix = '202600';
        $nextNumber = 1;

        // Find first available sequential number: 2026001, 2026002, ...
        while (true) {
            $candidate = $prefix . $nextNumber;
            $chk = $conn->prepare("SELECT 1 FROM `enrollments` WHERE `student_id` = ? LIMIT 1");
            $chk->bind_param('s', $candidate);
            $chk->execute();
            $exists = $chk->get_result()->num_rows > 0;
            $chk->close();
            if (!$exists) {
                break;
            }
            $nextNumber++;
        }

        $studentId = $prefix . $nextNumber;
        $up = $conn->prepare("UPDATE `enrollments` SET `student_id` = ? WHERE `id` = ?");
        $up->bind_param('si', $studentId, $newId);
        $up->execute();
        $up->close();
    }

    $conn->commit();
    echo 'success';
    exit;
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $ignore) {
        }
    }
    http_response_code(500);
    echo 'error: ' . $e->getMessage();
    exit;
}
