<?php
require_once __DIR__ . '/auth.php';

smartenroll_auth_start_session();
smartenroll_require_role('finance');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$students = [];
$error = '';
$successMessage = $_SESSION['requirements_success'] ?? '';
unset($_SESSION['requirements_success']);
$search = trim((string)($_GET['q'] ?? ''));
$requirementFilter = trim((string)($_GET['requirement_filter'] ?? 'all'));
$isPrintMode = (string)($_GET['print'] ?? '') === '1';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$totalStudents = 0;
$filteredStudents = 0;
$totalPages = 1;
$requirementLabels = [
    'picture_2x2' => '2x2 Picture',
    'birth_certificate' => 'Birth Certificate',
    'medical_certificate' => 'Medical Certificate',
];
$requirementFilterOptions = [
    'all' => 'All Students',
    'picture_2x2' => '2x2 Picture',
    'birth_certificate' => 'Birth Certificate',
    'medical_certificate' => 'Medical Certificate',
    'complete' => 'All Requirements',
    'none' => 'No Requirements Yet',
];

if (!isset($requirementFilterOptions[$requirementFilter])) {
    $requirementFilter = 'all';
}

function format_name(array $row): string
{
    $m = trim((string)($row['learner_mname'] ?? ''));
    $mi = $m !== '' ? strtoupper(mb_substr($m, 0, 1)) . '.' : '';
    $full = trim(
        ($row['learner_lname'] ?? '') . ', ' .
        ($row['learner_fname'] ?? '') . ' ' . $mi
    );

    return trim(preg_replace('/\s+/', ' ', $full), ' ,');
}

function ensure_requirements_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS student_requirements (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            enrollment_id INT NOT NULL,
            student_id VARCHAR(100) NOT NULL,
            requirement_key VARCHAR(100) NOT NULL,
            original_name VARCHAR(255) NOT NULL DEFAULT '',
            stored_name VARCHAR(255) NOT NULL DEFAULT '',
            file_path VARCHAR(255) NOT NULL DEFAULT '',
            uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_student_requirement (enrollment_id, requirement_key),
            KEY idx_student_id (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function uploaded_requirement_labels(array $row, array $requirementLabels): array
{
    $keysRaw = trim((string)($row['uploaded_requirement_keys'] ?? ''));
    if ($keysRaw === '') {
        return [];
    }

    $labels = [];
    foreach (explode(',', $keysRaw) as $key) {
        $key = trim($key);
        if ($key !== '' && isset($requirementLabels[$key])) {
            $labels[] = $requirementLabels[$key];
        }
    }

    return $labels;
}

function requirements_aggregate_sql(): string
{
    return "LEFT JOIN (
            SELECT
                enrollment_id,
                GROUP_CONCAT(requirement_key ORDER BY requirement_key SEPARATOR ',') AS uploaded_requirement_keys,
                MAX(CASE WHEN requirement_key = 'picture_2x2' THEN 1 ELSE 0 END) AS has_picture_2x2,
                MAX(CASE WHEN requirement_key = 'birth_certificate' THEN 1 ELSE 0 END) AS has_birth_certificate,
                MAX(CASE WHEN requirement_key = 'medical_certificate' THEN 1 ELSE 0 END) AS has_medical_certificate,
                COUNT(DISTINCT requirement_key) AS requirement_count
            FROM student_requirements
            GROUP BY enrollment_id
        ) requirement_summary ON requirement_summary.enrollment_id = enrollments.id";
}

function active_filter_label(string $requirementFilter, array $requirementFilterOptions): string
{
    return $requirementFilterOptions[$requirementFilter] ?? $requirementFilterOptions['all'];
}

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');
    ensure_requirements_table($conn);

    $whereClauses = [];
    $queryTypes = '';
    $queryValues = [];

    if ($search !== '') {
        $whereClauses[] = "(enrollments.student_id LIKE ?
            OR enrollments.learner_lname LIKE ?
            OR enrollments.learner_fname LIKE ?
            OR enrollments.learner_mname LIKE ?
            OR enrollments.grade_level LIKE ?
            OR enrollments.school_year LIKE ?
            OR CONCAT_WS(' ', enrollments.learner_fname, enrollments.learner_mname, enrollments.learner_lname) LIKE ?
            OR CONCAT_WS(', ', enrollments.learner_lname, CONCAT_WS(' ', enrollments.learner_fname, enrollments.learner_mname)) LIKE ?)";
        $likeSearch = '%' . $search . '%';
        $queryTypes .= 'ssssssss';
        array_push($queryValues, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch);
    }

    if ($requirementFilter === 'picture_2x2') {
        $whereClauses[] = 'COALESCE(requirement_summary.has_picture_2x2, 0) = 1';
    } elseif ($requirementFilter === 'birth_certificate') {
        $whereClauses[] = 'COALESCE(requirement_summary.has_birth_certificate, 0) = 1';
    } elseif ($requirementFilter === 'medical_certificate') {
        $whereClauses[] = 'COALESCE(requirement_summary.has_medical_certificate, 0) = 1';
    } elseif ($requirementFilter === 'complete') {
        $whereClauses[] = 'COALESCE(requirement_summary.requirement_count, 0) >= ' . count($requirementLabels);
    } elseif ($requirementFilter === 'none') {
        $whereClauses[] = 'COALESCE(requirement_summary.requirement_count, 0) = 0';
    }

    $whereSql = $whereClauses !== [] ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
    $aggregateJoinSql = requirements_aggregate_sql();

    $totalCountResult = $conn->query("SELECT COUNT(*) AS total FROM enrollments");
    $totalStudents = (int)(($totalCountResult->fetch_assoc()['total'] ?? 0));

    $countStmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM enrollments
         $aggregateJoinSql
         $whereSql"
    );
    if ($queryValues) {
        $countStmt->bind_param($queryTypes, ...$queryValues);
    }
    $countStmt->execute();
    $filteredStudents = (int)(($countStmt->get_result()->fetch_assoc()['total'] ?? 0));
    $countStmt->close();

    $totalPages = max(1, (int)ceil($filteredStudents / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $studentSql = "SELECT
            enrollments.id,
            enrollments.student_id,
            enrollments.learner_lname,
            enrollments.learner_fname,
            enrollments.learner_mname,
            enrollments.grade_level,
            enrollments.school_year,
            requirement_summary.uploaded_requirement_keys
         FROM enrollments
         $aggregateJoinSql
         $whereSql
         ORDER BY enrollments.learner_lname ASC, enrollments.learner_fname ASC, enrollments.id DESC";

    $studentTypes = $queryTypes;
    $studentValues = $queryValues;

    $studentSql .= " LIMIT ? OFFSET ?";
    $studentTypes .= 'ii';
    $studentValues[] = $perPage;
    $studentValues[] = $offset;

    $studentStmt = $conn->prepare($studentSql);
    if ($studentValues) {
        $studentStmt->bind_param($studentTypes, ...$studentValues);
    }
    $studentStmt->execute();
    $result = $studentStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $studentStmt->close();
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMARTENROLL | Upload Requirements</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/requirements_upload.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page dashboard-white-page">
<main class="dashboard-main requirements-main">
    <section class="requirements-hero">
        <div class="requirements-hero-top">
            <a href="dashboard.php" class="dashboard-link back-left"><i class="fa-solid fa-arrow-left"></i></a>
            <div class="requirements-hero-copy">
                <span class="eyebrow eyebrow-gold">Requirements Desk</span>
                <h1>Upload Requirements</h1>
                <p>Review student records, open one learner, and complete the document checklist in one place.</p>
            </div>
        </div>
        <div class="requirements-hero-stats">
            <div class="hero-stat">
                <span>Total Students</span>
                <strong><?php echo $totalStudents; ?></strong>
            </div>
            <div class="hero-stat">
                <span>Required Files</span>
                <strong>3 Documents</strong>
            </div>
            <div class="hero-stat">
                <span>Submission Status</span>
                <strong>File Monitoring</strong>
            </div>
        </div>
    </section>

    <?php if ($isPrintMode): ?>
        <section class="requirements-print-summary">
            <strong>Filtered Student List</strong>
            <span>Filter: <?php echo htmlspecialchars(active_filter_label($requirementFilter, $requirementFilterOptions)); ?></span>
            <span>Search: <?php echo htmlspecialchars($search !== '' ? $search : 'All Students'); ?></span>
            <span>Page: <?php echo $page; ?> of <?php echo $totalPages; ?></span>
            <span>Shown: <?php echo count($students); ?></span>
            <span>Matching Records: <?php echo $filteredStudents; ?></span>
        </section>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="requirements-alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($successMessage): ?>
        <div class="requirements-alert success"><?php echo htmlspecialchars($successMessage); ?></div>
    <?php endif; ?>

    <section class="requirements-directory">
        <div class="requirements-directory-head">
            <div>
                <span class="eyebrow eyebrow-blue">Student Directory</span>
                <h2><?php echo $isPrintMode ? 'Printable Requirements List' : 'Choose A Student'; ?></h2>
                <p><?php echo $isPrintMode ? 'This view is ready to print based on the current search and requirement filter.' : 'Open a student record to upload the 2x2 picture, birth certificate, and medical certificate.'; ?></p>
            </div>
            <?php if (!$isPrintMode): ?>
                <div class="requirements-controls">
                    <form method="get" action="requirements_upload.php" class="requirements-filters-form">
                        <a
                            id="requirementsPrintBtn"
                            href="requirements_upload.php?<?php echo htmlspecialchars(http_build_query(array_filter([
                                'q' => $search,
                                'requirement_filter' => $requirementFilter,
                                'page' => $page,
                                'print' => '1',
                            ], static fn($value) => $value !== '' && $value !== null))); ?>"
                            class="requirements-toolbar-btn requirements-toolbar-btn-icon requirements-toolbar-btn-print"
                            aria-label="Print List"
                            title="Print List"
                        >
                            <i class="fa-solid fa-print"></i>
                        </a>
                        <div class="requirements-filter-wrap">
                            <i class="fa-solid fa-filter"></i>
                            <select name="requirement_filter" id="requirementsFilter" class="requirements-filter-select" onchange="this.form.submit()">
                                <?php foreach ($requirementFilterOptions as $filterKey => $filterLabel): ?>
                                    <option value="<?php echo htmlspecialchars($filterKey); ?>" <?php echo $requirementFilter === $filterKey ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($filterLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="requirements-search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input id="requirementsSearch" name="q" type="text" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search student, ID, grade, or school year">
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$error && empty($students)): ?>
            <div class="requirements-empty">No students found.</div>
        <?php else: ?>
            <div class="requirements-directory-table" id="requirementsStudentList">
                <?php foreach ($students as $student): ?>
                    <?php $fullName = format_name($student) ?: 'N/A'; ?>
                    <?php $uploadedRequirementLabels = uploaded_requirement_labels($student, $requirementLabels); ?>
                    <a
                        class="requirements-student-row"
                        href="requirements_upload_details.php?student_id=<?php echo urlencode((string)$student['student_id']); ?>"
                    >
                        <span class="student-row-id"><?php echo htmlspecialchars((string)($student['student_id'] ?? '')); ?></span>
                        <strong class="student-row-name"><?php echo htmlspecialchars($fullName); ?></strong>
                        <span class="student-row-requirement-status <?php echo $uploadedRequirementLabels !== [] ? 'has-items' : 'is-empty'; ?>">
                            <?php if ($uploadedRequirementLabels !== []): ?>
                                <span class="student-row-requirement-tags">
                                    <?php foreach ($uploadedRequirementLabels as $label): ?>
                                        <span class="student-row-requirement-tag"><?php echo htmlspecialchars($label); ?></span>
                                    <?php endforeach; ?>
                                </span>
                            <?php else: ?>
                                <span class="student-row-requirement-empty">No requirements yet</span>
                            <?php endif; ?>
                        </span>
                        <span class="student-row-grade"><?php echo htmlspecialchars((string)($student['grade_level'] ?? 'N/A')); ?></span>
                        <span class="student-row-year"><?php echo htmlspecialchars((string)($student['school_year'] ?? 'N/A')); ?></span>
                        <span class="requirements-open-btn">View Record</span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (!$error && $filteredStudents > 0 && !$isPrintMode): ?>
                <div class="requirements-pagination">
                    <?php
                    $prevPage = max(1, $page - 1);
                    $nextPage = min($totalPages, $page + 1);
                    $prevQuery = http_build_query(array_filter(['q' => $search, 'requirement_filter' => $requirementFilter, 'page' => $prevPage], static fn($value) => $value !== '' && $value !== null));
                    $nextQuery = http_build_query(array_filter(['q' => $search, 'requirement_filter' => $requirementFilter, 'page' => $nextPage], static fn($value) => $value !== '' && $value !== null));
                    ?>
                    <a class="requirements-page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : 'requirements_upload.php?' . $prevQuery; ?>">Prev</a>
                    <span class="requirements-page-status">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    <a class="requirements-page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo $page >= $totalPages ? '#' : 'requirements_upload.php?' . $nextQuery; ?>">Next</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<?php if ($isPrintMode): ?>
<script>
window.addEventListener('load', () => {
    let shouldClose = false;

    const closePrintTab = () => {
        if (shouldClose) {
            window.close();
        }
    };

    window.addEventListener('afterprint', () => {
        shouldClose = true;
        setTimeout(closePrintTab, 150);
    });

    window.addEventListener('focus', () => {
        if (shouldClose) {
            setTimeout(closePrintTab, 150);
        }
    });

    window.print();
});
</script>
<?php endif; ?>
<script src="js/requirements_upload.js"></script>
</body>
</html>
