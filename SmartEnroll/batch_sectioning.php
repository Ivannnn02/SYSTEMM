<?php
require_once __DIR__ . '/auth.php';

smartenroll_require_role('finance');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$selectionRows = [];
$schoolYearOptions = [];
$gradeLevelOptions = [];
$errorMessage = '';
$selectedSchoolYear = isset($_GET['school_year']) ? trim((string)$_GET['school_year']) : '';
$selectedGradeLevel = isset($_GET['grade_level']) ? trim((string)$_GET['grade_level']) : '';

function connectEnrollmentDb(): mysqli
{
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');
    return $conn;
}

function bindParamsSafe(mysqli_stmt $stmt, string $types, array $values): void
{
    $refs = [$types];
    foreach ($values as $key => $value) {
        $refs[] = &$values[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

try {
    $conn = connectEnrollmentDb();

    $schoolYearRes = $conn->query("
        SELECT DISTINCT COALESCE(school_year, '') AS school_year
        FROM enrollments
        WHERE COALESCE(school_year, '') <> ''
        ORDER BY school_year DESC
    ");
    while ($row = $schoolYearRes->fetch_assoc()) {
        $schoolYearOptions[] = $row['school_year'];
    }

    $gradeLevelRes = $conn->query("
        SELECT DISTINCT COALESCE(grade_level, '') AS grade_level
        FROM enrollments
        WHERE COALESCE(grade_level, '') <> ''
        ORDER BY grade_level ASC
    ");
    while ($row = $gradeLevelRes->fetch_assoc()) {
        $gradeLevelOptions[] = $row['grade_level'];
    }

    $sql = "
        SELECT DISTINCT
            COALESCE(school_year, '') AS school_year,
            COALESCE(grade_level, '') AS grade_level
        FROM enrollments
        WHERE COALESCE(school_year, '') <> ''
          AND COALESCE(grade_level, '') <> ''
    ";

    $params = [];
    $types = '';
    if ($selectedSchoolYear !== '') {
        $sql .= " AND COALESCE(school_year, '') = ? ";
        $params[] = $selectedSchoolYear;
        $types .= 's';
    }
    if ($selectedGradeLevel !== '') {
        $sql .= " AND COALESCE(grade_level, '') = ? ";
        $params[] = $selectedGradeLevel;
        $types .= 's';
    }
    $sql .= " ORDER BY school_year DESC, grade_level ASC ";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        bindParamsSafe($stmt, $types, $params);
        $stmt->execute();
        $resOptions = $stmt->get_result();
    } else {
        $resOptions = $conn->query($sql);
    }

    while ($row = $resOptions->fetch_assoc()) {
        $selectionRows[] = $row;
    }

    if (empty($selectionRows)) {
        $errorMessage = 'No School Year and Grade Level records found yet.';
    }
} catch (Throwable $e) {
    $errorMessage = 'Unable to load School Year and Grade Level records.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMARTENROLL | Batch and Sectioning</title>
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
                <a href="dashboard.php" class="back-btn" aria-label="Back to dashboard" title="Back to dashboard">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="bs-header-title">
                    <h1>Batch and Sectioning</h1>
                    <p>Select a school year and grade level to start organizing students into batches and sections.</p>
                </div>
            </div>
        </div>

        <section class="card">
            <h2>Select School Year and Grade Level</h2>
            <form method="get" action="batch_sectioning.php" class="sort-form" style="margin: 14px 0 18px 0; flex-wrap: wrap;">
                <select name="school_year" class="sort-select">
                    <option value="">All School Years</option>
                    <?php foreach ($schoolYearOptions as $schoolYear): ?>
                        <option value="<?= htmlspecialchars($schoolYear) ?>" <?= $selectedSchoolYear === $schoolYear ? 'selected' : '' ?>>
                            <?= htmlspecialchars($schoolYear) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="grade_level" class="sort-select">
                    <option value="">All Grade Levels</option>
                    <?php foreach ($gradeLevelOptions as $gradeLevel): ?>
                        <option value="<?= htmlspecialchars($gradeLevel) ?>" <?= $selectedGradeLevel === $gradeLevel ? 'selected' : '' ?>>
                            <?= htmlspecialchars($gradeLevel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="sort-btn">
                    <i class="fas fa-filter"></i> Apply Filter
                </button>
                <a href="batch_sectioning.php" class="sort-btn">
                    <i class="fas fa-rotate-left"></i> Clear
                </a>
            </form>

            <?php if ($errorMessage !== ''): ?>
                <p class="error-text"><?= htmlspecialchars($errorMessage) ?></p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="selection-table">
                        <thead>
                            <tr>
                                <th>School Year</th>
                                <th>Grade Level</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($selectionRows as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['school_year']) ?></td>
                                    <td><?= htmlspecialchars($row['grade_level']) ?></td>
                                    <td>
                                        <a
                                            class="table-action-btn"
                                            href="batch_sectioning_list.php?school_year=<?= urlencode($row['school_year']) ?>&grade_level=<?= urlencode($row['grade_level']) ?>"
                                        >
                                            View List
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
