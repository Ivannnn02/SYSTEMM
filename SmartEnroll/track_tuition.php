<?php
require_once __DIR__ . '/auth.php';
smartenroll_auth_start_session();
smartenroll_require_role('finance');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$rows = [];
$error = '';

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');

    $sql = "SELECT student_id, learner_lname, learner_fname, learner_mname, grade_level, school_year FROM enrollments ORDER BY id DESC";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMARTENROLL | Track Tuition</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/track_tuition.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page dashboard-white-page">

<main class="dashboard-main">
    <div class="dashboard-header tuition-header">
        <div class="student-header-left">
            <a href="dashboard.php" class="dashboard-link back-left"><i class="fa-solid fa-arrow-left"></i></a>
            <div class="student-header-title">
                <h1>Track Tuition</h1>
                <p>View enrolled students and tuition rates by program/grade.</p>
            </div>
        </div>
    </div>

    <section class="tuition-section">
        <div class="tuition-card">
            <div class="tuition-card-header">
                <h2>Student List</h2>
                <div class="tuition-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input id="tuitionSearch" type="text" placeholder="Search by name, ID, or grade">
                </div>
            </div>

            <?php if ($error): ?>
                <div class="student-error"><strong>Unable to load students.</strong> <?php echo htmlspecialchars($error); ?></div>
            <?php elseif (empty($rows)): ?>
                <div class="student-empty">No student records found.</div>
            <?php else: ?>
                <div class="student-table-wrap">
                    <table class="student-table tuition-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Full Name</th>
                                <th>Grade Level</th>
                                <th>S.Y.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr class="tuition-row"
                                    data-student-id="<?php echo htmlspecialchars($row['student_id'] ?? ''); ?>"
                                    data-full-name="<?php echo htmlspecialchars(format_name($row) ?: '—'); ?>"
                                    data-grade="<?php echo htmlspecialchars($row['grade_level'] ?? ''); ?>"
                                    data-school-year="<?php echo htmlspecialchars($row['school_year'] ?? ''); ?>">
                                    <td><a class="tuition-link" href="tuition_details.php?student_id=<?php echo urlencode($row['student_id'] ?? ''); ?>"><?php echo htmlspecialchars($row['student_id'] ?? ''); ?></a></td>
                                    <td><a class="tuition-link" href="tuition_details.php?student_id=<?php echo urlencode($row['student_id'] ?? ''); ?>"><?php echo htmlspecialchars(format_name($row) ?: '—'); ?></a></td>
                                    <td><a class="tuition-link" href="tuition_details.php?student_id=<?php echo urlencode($row['student_id'] ?? ''); ?>"><?php echo htmlspecialchars($row['grade_level'] ?? ''); ?></a></td>
                                    <td><a class="tuition-link" href="tuition_details.php?student_id=<?php echo urlencode($row['student_id'] ?? ''); ?>"><?php echo htmlspecialchars($row['school_year'] ?? ''); ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    

</main>

<script src="js/track_tuition.js"></script>
</body>
</html>
