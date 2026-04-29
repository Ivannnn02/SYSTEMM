<?php
require_once __DIR__ . '/auth.php';
smartenroll_auth_start_session();
smartenroll_require_role('finance');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$students = [];
$error = '';
$studentsPerPage = 12;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$totalStudents = 0;
$totalPages = 1;
$searchQuery = trim((string)($_GET['q'] ?? ''));
$successMessage = $_SESSION['pay_tuition_success'] ?? '';
$warningMessage = $_SESSION['pay_tuition_warning'] ?? '';
unset($_SESSION['pay_tuition_success'], $_SESSION['pay_tuition_warning']);

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

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');

    $whereSql = '';
    $searchLike = '';
    if ($searchQuery !== '') {
        $whereSql = "
            WHERE CONCAT_WS(' ',
                COALESCE(student_id, ''),
                COALESCE(learner_lname, ''),
                COALESCE(learner_fname, ''),
                COALESCE(learner_mname, ''),
                COALESCE(grade_level, ''),
                COALESCE(school_year, ''),
                COALESCE(email, '')
            ) LIKE ?
        ";
        $searchLike = '%' . $searchQuery . '%';
    }

    if ($whereSql !== '') {
        $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM enrollments {$whereSql}");
        $countStmt->bind_param('s', $searchLike);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
    } else {
        $countResult = $conn->query("SELECT COUNT(*) AS total FROM enrollments");
    }
    $totalStudents = (int)($countResult->fetch_assoc()['total'] ?? 0);
    $totalPages = max(1, (int)ceil($totalStudents / $studentsPerPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $studentsPerPage;

    $sql = "
        SELECT id, student_id, learner_lname, learner_fname, learner_mname, grade_level, school_year, email
        FROM enrollments
        {$whereSql}
        ORDER BY learner_lname ASC, learner_fname ASC, id DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $conn->prepare($sql);
    if ($whereSql !== '') {
        $stmt->bind_param('sii', $searchLike, $studentsPerPage, $offset);
    } else {
        $stmt->bind_param('ii', $studentsPerPage, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMARTENROLL | Tuition Receipt</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/pay_tuition.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page dashboard-white-page">
<main class="dashboard-main pay-list-main">
    <div class="dashboard-header tuition-header">
        <div class="student-header-left">
            <a href="dashboard.php" class="dashboard-link back-left"><i class="fa-solid fa-arrow-left"></i></a>
            <div class="student-header-title">
                <h1>Tuition Receipt</h1>
                <p>Open a student first, then continue to the receipt page to add the payment breakdown and save the receipt.</p>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="pay-alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($successMessage): ?>
        <div class="pay-alert success"><?php echo htmlspecialchars($successMessage); ?></div>
    <?php endif; ?>
    <?php if ($warningMessage): ?>
        <div class="pay-alert info"><?php echo htmlspecialchars($warningMessage); ?></div>
    <?php endif; ?>

    <section class="student-directory-card">
        <div class="panel-top student-panel-top">
            <div>
                <span class="eyebrow eyebrow-blue">Step 1</span>
                <h2>All Students</h2>
                <p>Select one student to open the tuition payment page. Showing 12 students per page.</p>
            </div>
            <form method="get" action="tuition_receipt.php" class="search-box search-form">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="studentSearch" name="q" type="text" placeholder="Search student, ID, grade, or email" value="<?php echo htmlspecialchars($searchQuery); ?>">
            </form>
        </div>

        <?php if (!$error && empty($students)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-users"></i>
                <h2>No Students Found</h2>
                <p>There are no enrolled students available for tuition payment yet.</p>
            </div>
        <?php else: ?>
            <div class="student-card-list student-card-list-wide" id="payStudentList">
                <?php foreach ($students as $student): ?>
                    <?php $fullName = format_name($student) ?: 'N/A'; ?>
                    <a
                        class="student-pick-card"
                        href="tuition_receipt_details.php?student_id=<?php echo urlencode((string)$student['student_id']); ?>"
                        data-search="<?php echo htmlspecialchars(strtolower(implode(' ', [
                            (string)($student['student_id'] ?? ''),
                            $fullName,
                            (string)($student['grade_level'] ?? ''),
                            (string)($student['email'] ?? ''),
                            (string)($student['school_year'] ?? '')
                        ]))); ?>"
                    >
                        <div class="student-pick-main">
                            <strong><?php echo htmlspecialchars($fullName); ?></strong>
                            <span>School ID: <?php echo htmlspecialchars((string)($student['student_id'] ?? '')); ?></span>
                        </div>
                        <div class="student-pick-meta">
                            <span><?php echo htmlspecialchars((string)($student['grade_level'] ?? 'N/A')); ?></span>
                            <small><?php echo htmlspecialchars((string)($student['school_year'] ?? 'No school year')); ?></small>
                        </div>
                        <div class="student-pick-action">Open Payment</div>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination-bar">
                    <a class="pagination-btn<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" href="<?php echo $currentPage > 1 ? '?page=' . ($currentPage - 1) . ($searchQuery !== '' ? '&q=' . urlencode($searchQuery) : '') : '#'; ?>">
                        <i class="fa-solid fa-arrow-left"></i>
                        Prev
                    </a>
                    <div class="pagination-status">
                        Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?><?php echo $searchQuery !== '' ? ' • Search: ' . htmlspecialchars($searchQuery) : ''; ?>
                    </div>
                    <a class="pagination-btn<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" href="<?php echo $currentPage < $totalPages ? '?page=' . ($currentPage + 1) . ($searchQuery !== '' ? '&q=' . urlencode($searchQuery) : '') : '#'; ?>">
                        Next
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<script src="js/pay_tuition.js"></script>
</body>
</html>
