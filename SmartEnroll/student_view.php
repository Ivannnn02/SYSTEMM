<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/enrollment_fields.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

smartenroll_require_role('finance');

$student = null;
$columns = [];
$error = '';
$GLOBALS['smartenroll_custom_field_map'] = [];

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');
    smartenroll_ensure_student_status_column($conn);

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        throw new RuntimeException('Invalid student ID.');
    }

    $colRes = $conn->query("SHOW COLUMNS FROM `enrollments`");
    while ($row = $colRes->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    $stmt = $conn->prepare("SELECT * FROM `enrollments` WHERE `id` = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if (!$student) {
        throw new RuntimeException('Student record not found.');
    }

    $GLOBALS['smartenroll_custom_field_map'] = smartenroll_get_field_label_map($conn);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

function labelize(string $key): string
{
    return smartenroll_field_labelize($key, $GLOBALS['smartenroll_custom_field_map'] ?? []);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMARTENROLL | Student Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/student_view.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page dashboard-white-page">

<main class="dashboard-main">
    <div class="dashboard-header student-header">
        <div class="student-header-left">
            <a href="student_list.php" class="dashboard-link back-left">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="student-header-title">
                <h1>Student Details</h1>
                <p>Full enrollment information.</p>
            </div>
        </div>
    </div>

    <div class="student-detail-card">
        <?php if ($error): ?>
            <div class="student-error">
                <strong>Unable to load student.</strong>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php else: ?>
            <?php
                $fullName = trim(
                    ($student['learner_lname'] ?? '') . ', ' .
                    ($student['learner_fname'] ?? '') . ' ' .
                    ($student['learner_mname'] ?? '')
                );
                $fullName = trim(preg_replace('/\s+/', ' ', $fullName), " ,");
                $studentId = $student['student_id'] ?? '';
                $gradeLevel = $student['grade_level'] ?? '';
            ?>
            <div class="student-summary">
                <div>
                    <span class="summary-label">Student Name</span>
                    <h2 class="summary-name"><?php echo htmlspecialchars($fullName !== '' ? $fullName : 'Student'); ?></h2>
                </div>
                <div class="summary-meta">
                    <div>
                        <span class="summary-label">Student ID</span>
                        <span class="summary-value"><?php echo htmlspecialchars($studentId !== '' ? $studentId : '—'); ?></span>
                    </div>
                    <div>
                        <span class="summary-label">Grade Level</span>
                        <span class="summary-value"><?php echo htmlspecialchars($gradeLevel !== '' ? $gradeLevel : '—'); ?></span>
                    </div>
                </div>
            </div>
            <?php
                $sectionMap = smartenroll_build_sections($columns);
                $skipCols = ['id'];
            ?>

            <?php foreach ($sectionMap as $sectionTitle => $fields): ?>
                <div class="detail-section">
                    <h3 class="detail-section-title"><?php echo htmlspecialchars($sectionTitle); ?></h3>
                    <div class="student-detail-grid">
                        <?php foreach ($fields as $col): ?>
                            <?php if (in_array($col, $skipCols, true)) { continue; } ?>
                            <div class="detail-item">
                                <span class="detail-label"><?php echo htmlspecialchars(labelize($col)); ?></span>
                                <?php $val = trim((string)($student[$col] ?? '')); ?>
                                <span class="detail-value"><?php echo htmlspecialchars($val !== '' ? $val : '—'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
