<?php
require_once __DIR__ . '/auth.php';

smartenroll_require_role('finance');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$students = [];
$errorMessage = '';
$successMessage = 'Batch has been successfully saved!';
$showSavedPopup = isset($_GET['saved']) && $_GET['saved'] === '1';
$selectedSchoolYear = isset($_GET['school_year']) ? trim((string)$_GET['school_year']) : '';
$selectedGrade = isset($_GET['grade_level']) ? trim((string)$_GET['grade_level']) : '';

function connectEnrollmentDb(): mysqli
{
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');
    return $conn;
}

function getBatchOptionsForGrade(string $gradeLevel): array
{
    $batchMap = [
        'Toddler' => [
            'Batch 1 (8:00AM to 10:00AM)',
            'Batch 2 (10:15AM to 12:15PM)',
            'Batch 3 (1:20PM to 3:20PM)',
        ],
        'Casa' => [
            'Batch 1 (8:15AM to 10:15AM)',
            'Batch 2 (10:30AM to 12:30PM)',
            'Batch 3 (1:30PM to 3:30PM)',
        ],
        'Brave' => [
            'Batch 1 (8:20AM to 9:50AM)',
            'Batch 2 (10:00AM to 11:30AM)',
            'Batch 3 (12:30PM to 2:00PM)',
        ],
        'Kindergarten' => [
            'Batch 1 (8:00AM to 11:00AM)',
            'Batch 2 (12:15PM to 3:15PM)',
        ],
        'Grade 1' => [
            'Batch 1 (MORNING)',
            'Batch 2 (AFTERNOON)',
        ],
        'Grade 2' => [
            'Batch 1 (MORNING)',
            'Batch 2 (AFTERNOON)',
        ],
        'Grade 3' => [
            'Batch 1 (MORNING)',
            'Batch 2 (AFTERNOON)',
        ],
    ];

    return $batchMap[$gradeLevel] ?? [];
}

$batchOptions = getBatchOptionsForGrade($selectedGrade);
$selectedBatchFilter = isset($_GET['filter_batch']) ? trim((string)$_GET['filter_batch']) : '';
if ($selectedBatchFilter !== '' && !in_array($selectedBatchFilter, $batchOptions, true)) {
    $selectedBatchFilter = '';
}

function ensureBatchAssignmentsTable(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS batch_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            enrollment_id INT NOT NULL,
            student_id VARCHAR(50) NOT NULL,
            school_year VARCHAR(20) NOT NULL,
            grade_level VARCHAR(50) NOT NULL,
            batch_name VARCHAR(50) NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_enrollment (enrollment_id),
            UNIQUE KEY uniq_student_sy_grade (student_id, school_year, grade_level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Backward compatibility if table existed before enrollment_id column was added.
    $colCheck = $conn->query("SHOW COLUMNS FROM batch_assignments LIKE 'enrollment_id'");
    if ($colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE batch_assignments ADD COLUMN enrollment_id INT NOT NULL DEFAULT 0 AFTER id");
        $conn->query("ALTER TABLE batch_assignments ADD UNIQUE KEY uniq_enrollment (enrollment_id)");
    }
}

if ($selectedSchoolYear === '' || $selectedGrade === '') {
    $errorMessage = 'Please select both School Year and Grade Level first.';
} else {
    try {
        $conn = connectEnrollmentDb();
        ensureBatchAssignmentsTable($conn);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $conn->begin_transaction();

            $saveSql = "
                INSERT INTO batch_assignments (enrollment_id, student_id, school_year, grade_level, batch_name)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    student_id = VALUES(student_id),
                    school_year = VALUES(school_year),
                    grade_level = VALUES(grade_level),
                    batch_name = VALUES(batch_name)
            ";
            $saveStmt = $conn->prepare($saveSql);

            foreach ($_POST as $key => $value) {
                if (strpos((string)$key, 'batch_for_row_') !== 0) {
                    continue;
                }

                $enrollmentId = (int)substr((string)$key, 14);
                $studentIdField = 'student_id_row_' . $enrollmentId;
                $studentId = isset($_POST[$studentIdField]) ? trim((string)$_POST[$studentIdField]) : '';
                $batchName = trim((string)$value);

                if ($enrollmentId <= 0 || $batchName === '') {
                    continue;
                }

                $saveStmt->bind_param('issss', $enrollmentId, $studentId, $selectedSchoolYear, $selectedGrade, $batchName);
                $saveStmt->execute();
            }

            $saveStmt->close();
            $conn->commit();
            $redirectQuery = $_SERVER['QUERY_STRING'] ?? '';
            $target = 'batch_sectioning_list.php' . ($redirectQuery !== '' ? ('?' . $redirectQuery . '&saved=1') : '?saved=1');
            header('Location: ' . $target);
            exit;
        }

        $sql = "
            SELECT
                e.id AS enrollment_id,
                COALESCE(e.student_id, '') AS student_id,
                COALESCE(e.learner_lname, '') AS learner_lname,
                COALESCE(e.learner_fname, '') AS learner_fname,
                COALESCE(e.learner_mname, '') AS learner_mname,
                COALESCE(e.sex, '') AS sex,
                COALESCE(e.grade_level, '') AS grade_level,
                COALESCE(e.school_year, '') AS school_year,
                COALESCE(ba.batch_name, '') AS assigned_batch
            FROM enrollments e
            LEFT JOIN batch_assignments ba
                ON ba.enrollment_id = e.id
            WHERE COALESCE(e.grade_level, '') = ?
              AND COALESCE(e.school_year, '') = ?
        ";

        if ($selectedBatchFilter !== '') {
            $sql .= " AND COALESCE(ba.batch_name, '') = ? ";
        }

        $sql .= "
            ORDER BY
                CASE WHEN COALESCE(ba.batch_name, '') = '' THEN 1 ELSE 0 END,
                ba.batch_name ASC,
                CASE
                    WHEN LOWER(COALESCE(e.sex, '')) = 'male' THEN 1
                    WHEN LOWER(COALESCE(e.sex, '')) = 'female' THEN 2
                    ELSE 3
                END,
                e.learner_lname ASC,
                e.learner_fname ASC
        ";

        $stmt = $conn->prepare($sql);
        if ($selectedBatchFilter !== '') {
            $stmt->bind_param('sss', $selectedGrade, $selectedSchoolYear, $selectedBatchFilter);
        } else {
            $stmt->bind_param('ss', $selectedGrade, $selectedSchoolYear);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $middleInitial = '';
            if (!empty($row['learner_mname'])) {
                $middleInitial = strtoupper(substr($row['learner_mname'], 0, 1)) . '.';
            }

            $fullName = trim($row['learner_lname'] . ', ' . $row['learner_fname'] . ' ' . $middleInitial);
            $row['full_name'] = preg_replace('/\s+/', ' ', $fullName);
            $students[] = $row;
        }

        $stmt->close();
    } catch (Throwable $e) {
        if (isset($conn) && $conn instanceof mysqli) {
            try {
                $conn->rollback();
            } catch (Throwable $ignore) {
            }
        }
        $errorMessage = 'Unable to load or save batch assignments. ' . $e->getMessage();
    }
}

$queryBase = 'school_year=' . urlencode($selectedSchoolYear) . '&grade_level=' . urlencode($selectedGrade);
if ($selectedBatchFilter !== '') {
    $queryBase .= '&filter_batch=' . urlencode($selectedBatchFilter);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMARTENROLL | Batch and Sectioning List</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/batch_sectioning.css">
</head>
<body>
    <main class="bs-main">
        <div class="bs-page-header">
            <div class="bs-header-left">
                <a href="batch_sectioning.php" class="back-btn" aria-label="Back to filter" title="Back to filter">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="bs-header-title">
                    <h1>Batch and Sectioning List</h1>
                    <p>Review students by school year and grade level, then assign or update their batch.</p>
                </div>
            </div>
        </div>

        <section class="card">
            <div class="filter-bar">
                <div class="filter-badge"><strong>S.Y.:</strong> <?= htmlspecialchars($selectedSchoolYear !== '' ? $selectedSchoolYear : 'N/A') ?></div>
                <div class="filter-badge"><strong>Grade Level:</strong> <?= htmlspecialchars($selectedGrade !== '' ? $selectedGrade : 'N/A') ?></div>
                <div class="filter-badge"><strong>Batch Filter:</strong> <?= htmlspecialchars($selectedBatchFilter !== '' ? $selectedBatchFilter : 'All') ?></div>
            </div>

            <div class="section-header">
                <h2 class="list-title">Student List</h2>
                <div class="section-actions">
                    <button type="button" class="print-btn" id="printListBtn" title="Print List">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button type="button" class="print-btn excel-btn" id="excelListBtn" title="Export Excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                    <button type="button" class="print-btn pdf-btn" id="pdfListBtn" title="Export PDF">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    <button type="submit" form="studentSectioningForm" class="save-btn" id="saveBatchesBtn" disabled>
                        <i class="fas fa-save"></i> Save
                    </button>
                    <form action="batch_sectioning_list.php" method="get" class="sort-form">
                        <input type="hidden" name="school_year" value="<?= htmlspecialchars($selectedSchoolYear) ?>">
                        <input type="hidden" name="grade_level" value="<?= htmlspecialchars($selectedGrade) ?>">
                        <select name="filter_batch" class="sort-select">
                            <option value="">All Batches</option>
                            <?php foreach ($batchOptions as $batch): ?>
                                <option value="<?= htmlspecialchars($batch) ?>" <?= $selectedBatchFilter === $batch ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($batch) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="sort-btn">
                            <i class="fas fa-sort"></i> Sort
                        </button>
                    </form>
                </div>
            </div>

            <form id="studentSectioningForm" action="batch_sectioning_list.php?<?= htmlspecialchars($queryBase) ?>" method="post">
                <div class="print-only print-header">
                    <div class="print-meta">
                        <p><strong>School Year:</strong> <?= htmlspecialchars($selectedSchoolYear !== '' ? $selectedSchoolYear : 'N/A') ?></p>
                        <p><strong>Grade Level:</strong> <?= htmlspecialchars($selectedGrade !== '' ? $selectedGrade : 'N/A') ?></p>
                        <p><strong>Printed At:</strong> <span id="printedAtValue">-</span></p>
                    </div>
                    <img src="assets/logo.png" alt="SmartEnroll Logo" class="print-logo">
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Gender</th>
                                <th>School Year</th>
                                <th>Grade Level</th>
                                <th>Batch</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($errorMessage !== ''): ?>
                                <tr>
                                    <td colspan="6" class="empty-row"><?= htmlspecialchars($errorMessage) ?></td>
                                </tr>
                            <?php elseif (empty($students)): ?>
                                <tr>
                                    <td colspan="6" class="empty-row">No students found for this filter.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($student['student_id'] !== '' ? $student['student_id'] : 'N/A') ?></td>
                                        <td><?= htmlspecialchars($student['full_name']) ?></td>
                                        <td><?= htmlspecialchars($student['sex'] !== '' ? $student['sex'] : 'N/A') ?></td>
                                        <td><?= htmlspecialchars($student['school_year'] !== '' ? $student['school_year'] : 'N/A') ?></td>
                                        <td><?= htmlspecialchars($student['grade_level'] !== '' ? $student['grade_level'] : 'N/A') ?></td>
                                        <td>
                                            <?php $hasAssignedBatch = trim((string)$student['assigned_batch']) !== ''; ?>
                                            <input type="hidden" name="student_id_row_<?= (int)$student['enrollment_id'] ?>" value="<?= htmlspecialchars($student['student_id']) ?>">
                                            <div class="batch-cell <?= $hasAssignedBatch ? 'is-locked' : 'is-editing' ?>">
                                                <div class="batch-display <?= $hasAssignedBatch ? '' : 'is-hidden' ?>">
                                                    <span class="batch-value"><?= htmlspecialchars($student['assigned_batch']) ?></span>
                                                    <button type="button" class="batch-edit-btn" title="Edit batch">
                                                        <i class="fas fa-pen"></i>
                                                    </button>
                                                </div>
                                                <select
                                                    class="batch-select <?= $hasAssignedBatch ? 'is-hidden' : '' ?>"
                                                    name="batch_for_row_<?= (int)$student['enrollment_id'] ?>"
                                                    data-original="<?= htmlspecialchars($student['assigned_batch']) ?>"
                                                >
                                                    <option value="">Select Batch</option>
                                                    <?php foreach ($batchOptions as $batch): ?>
                                                        <option value="<?= htmlspecialchars($batch) ?>" <?= $student['assigned_batch'] === $batch ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($batch) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </section>
    </main>

    <div id="successPopup" class="popup-overlay" data-auto-show="<?= $showSavedPopup ? '1' : '0' ?>">
        <div class="popup-box">
            <div class="popup-icon success-icon" id="successIcon">
                <img src="assets/logo.png" id="successLogo" alt="Logo">
                <i class="fas fa-check" id="successCheck"></i>
            </div>
            <h2>Saved Successfully!</h2>
            <p><?= htmlspecialchars($successMessage) ?></p>
            <button class="popup-btn" id="closeSuccess">OK</button>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
    <script src="js/batch_sectioning_list.js"></script>
</body>
</html>
