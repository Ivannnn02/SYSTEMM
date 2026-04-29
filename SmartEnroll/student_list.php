<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/enrollment_fields.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$currentUser = smartenroll_require_role('finance');
$isFinance = (($currentUser['role'] ?? '') === 'finance');

$rows = [];
$error = '';
$page = 1;
$perPage = 20;
$totalRows = 0;
$filteredRows = 0;
$totalPages = 1;
$status = trim((string)($_GET['status'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));
$studentStatusFilterOptions = [
    'all' => 'All Statuses',
    'New' => 'New',
    'Continuing' => 'Continuing',
    'Drop' => 'Drop',
    'blank' => 'Not Set',
];
$studentStatusFilter = trim((string)($_GET['student_status'] ?? 'all'));
$isPrintMode = (string)($_GET['print'] ?? '') === '1';

if (!isset($studentStatusFilterOptions[$studentStatusFilter])) {
    $studentStatusFilter = 'all';
}

function build_student_list_url(array $overrides = []): string
{
    global $search, $studentStatusFilter, $page, $isPrintMode;

    $params = [
        'q' => $search,
        'student_status' => $studentStatusFilter,
        'page' => $page,
        'print' => $isPrintMode ? '1' : '',
    ];

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
            continue;
        }

        if ($key === 'student_status' && $value === 'all') {
            unset($params[$key]);
            continue;
        }

        if ($key === 'page' && (int)$value <= 1) {
            unset($params[$key]);
            continue;
        }

        if ($key === 'print' && $value !== '1') {
            unset($params[$key]);
        }
    }

    return 'student_list.php' . ($params !== [] ? '?' . http_build_query($params) : '');
}

function active_student_status_filter_label(string $filter, array $options): string
{
    return $options[$filter] ?? $options['all'];
}

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');
    smartenroll_ensure_student_status_column($conn);

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) {
        $page = 1;
    }

    $whereClauses = [];
    $queryTypes = '';
    $queryValues = [];

    if ($search !== '') {
        $whereClauses[] = "(student_id LIKE ?
            OR learner_lname LIKE ?
            OR learner_fname LIKE ?
            OR learner_mname LIKE ?
            OR grade_level LIKE ?
            OR COALESCE(student_status, '') LIKE ?
            OR street LIKE ?
            OR barangay LIKE ?
            OR municipality LIKE ?
            OR province LIKE ?
            OR CONCAT_WS(' ', learner_fname, learner_mname, learner_lname) LIKE ?
            OR CONCAT_WS(', ', learner_lname, CONCAT_WS(' ', learner_fname, learner_mname)) LIKE ?)";
        $likeSearch = '%' . $search . '%';
        $queryTypes .= 'ssssssssssss';
        array_push(
            $queryValues,
            $likeSearch,
            $likeSearch,
            $likeSearch,
            $likeSearch,
            $likeSearch,
            $likeSearch,
            $likeSearch,
            $likeSearch,
            $likeSearch,
            $likeSearch,
            $likeSearch,
            $likeSearch
        );
    }

    if ($studentStatusFilter === 'blank') {
        $whereClauses[] = "(student_status IS NULL OR TRIM(student_status) = '')";
    } elseif ($studentStatusFilter !== 'all') {
        $whereClauses[] = "student_status = ?";
        $queryTypes .= 's';
        $queryValues[] = $studentStatusFilter;
    }

    $whereSql = $whereClauses !== [] ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    $countRes = $conn->query("SELECT COUNT(*) AS total FROM enrollments");
    $totalRows = (int)($countRes->fetch_assoc()['total'] ?? 0);

    $filteredCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM enrollments $whereSql");
    if ($queryValues !== []) {
        $filteredCountStmt->bind_param($queryTypes, ...$queryValues);
    }
    $filteredCountStmt->execute();
    $filteredRows = (int)($filteredCountStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $filteredCountStmt->close();

    if ($isPrintMode) {
        $page = 1;
        $totalPages = 1;
        $offset = 0;
    } else {
        $totalPages = max(1, (int)ceil($filteredRows / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
    }

    $sql = "SELECT id, student_id, learner_lname, learner_fname, learner_mname, grade_level, street, barangay, municipality, province, student_status
            FROM enrollments
            $whereSql
            ORDER BY id DESC";

    if (!$isPrintMode) {
        $sql .= " LIMIT ? OFFSET ?";
        $queryTypes .= 'ii';
        $queryValues[] = $perPage;
        $queryValues[] = $offset;
    }

    $stmt = $conn->prepare($sql);
    if ($queryValues !== []) {
        $stmt->bind_param($queryTypes, ...$queryValues);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
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
    <title>SMARTENROLL | Student List</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/student_list.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page dashboard-white-page<?php echo $isPrintMode ? ' student-list-print-page' : ''; ?>" data-print-mode="<?php echo $isPrintMode ? '1' : '0'; ?>">

<main class="dashboard-main">
    <div class="dashboard-header student-header">
        <div class="student-header-left">
            <a href="dashboard.php" class="dashboard-link back-left">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="student-header-title">
                <h1>Student List</h1>
                <p>All enrolled students recorded in SMARTENROLL.</p>
            </div>
        </div>
    </div>

    <section class="student-list-toolbar">
        <form class="student-filter-form" method="get" action="student_list.php">
            <a href="<?php echo htmlspecialchars(build_student_list_url(['print' => '1', 'page' => null])); ?>" data-print-url="<?php echo htmlspecialchars(build_student_list_url(['print' => '1', 'page' => null])); ?>" class="export-btn print student-print-btn" aria-label="Print List" title="Print List">
                <i class="fa-solid fa-print"></i>
            </a>

            <label class="student-filter-field student-filter-inline">
                <div class="student-filter-select-wrap">
                    <i class="fa-solid fa-filter"></i>
                    <select name="student_status" id="studentStatusFilter" aria-label="Student Status">
                        <?php foreach ($studentStatusFilterOptions as $filterValue => $filterLabel): ?>
                            <option value="<?php echo htmlspecialchars($filterValue); ?>" <?php echo $studentStatusFilter === $filterValue ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($filterLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </label>

            <div class="student-search student-search-inline">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="studentSearch" type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search student, grade, address, or status">
            </div>
        </form>
    </section>

    <div class="student-list-card">
        <?php if ($isPrintMode): ?>
            <section class="student-print-summary">
                <strong>Filtered Student List</strong>
                <span>Status: <?php echo htmlspecialchars(active_student_status_filter_label($studentStatusFilter, $studentStatusFilterOptions)); ?></span>
                <span>Search: <?php echo htmlspecialchars($search !== '' ? $search : 'All Students'); ?></span>
                <span>Shown: <?php echo count($rows); ?></span>
                <span>Matching Records: <?php echo $filteredRows; ?></span>
            </section>
        <?php endif; ?>
        <?php if ($status === 'updated'): ?>
            <div class="student-success">
                <strong>Enrollment updated.</strong>
                <p>The student details were saved successfully.</p>
            </div>
        <?php elseif ($status === 'deleted'): ?>
            <div class="student-success">
                <strong>Enrollment deleted.</strong>
                <p>The student record was removed successfully.</p>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="student-error">
                <strong>Unable to load students.</strong>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php elseif (empty($rows)): ?>
            <div class="student-empty">
                <p>No student records found.</p>
            </div>
        <?php else: ?>
            <div class="student-table-wrap">
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Grade Level</th>
                            <th>Address</th>
                            <th>Student Status</th>
                            <?php if (!$isPrintMode): ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $m = trim((string)($row['learner_mname'] ?? ''));
                                $mi = $m !== '' ? strtoupper(mb_substr($m, 0, 1)) . '.' : '';
                                $fullName = trim(
                                    ($row['learner_lname'] ?? '') . ', ' .
                                    ($row['learner_fname'] ?? '') . ' ' . $mi
                                );
                                $fullName = trim(preg_replace('/\s+/', ' ', $fullName), " ,");
                                $addressParts = array_filter([
                                    trim((string)($row['street'] ?? '')),
                                    trim((string)($row['barangay'] ?? '')),
                                    trim((string)($row['municipality'] ?? '')),
                                    trim((string)($row['province'] ?? ''))
                                ], fn($v) => $v !== '');
                                $address = implode(', ', $addressParts);
                                $studentStatus = trim((string)($row['student_status'] ?? ''));
                                $studentStatusClass = strtolower(trim((string)preg_replace('/[^a-z0-9]+/', '-', $studentStatus), '-'));
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['student_id'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($fullName !== '' ? $fullName : '—'); ?></td>
                                <td><?php echo htmlspecialchars($row['grade_level'] ?? ''); ?></td>
                                <td class="address-cell" title="<?php echo htmlspecialchars($address); ?>">
                                    <?php echo htmlspecialchars($address !== '' ? $address : '—'); ?>
                                </td>
                                <td class="student-status-cell">
                                    <?php if ($studentStatus !== ''): ?>
                                        <span class="student-status-pill <?php echo htmlspecialchars($studentStatusClass); ?>">
                                            <?php echo htmlspecialchars($studentStatus); ?>
                                        </span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <?php if (!$isPrintMode): ?>
                                    <td>
                                        <div class="student-actions">
                                            <?php if ($isFinance): ?>
                                                <a href="student_edit.php?id=<?php echo urlencode((string)($row['id'] ?? '')); ?>" class="action-btn edit" title="Edit Enrollment Form Details">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <a href="student_delete.php?id=<?php echo urlencode((string)($row['id'] ?? '')); ?>" class="action-btn delete" title="Delete">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!$isPrintMode && $totalPages > 1): ?>
                <div class="pagination">
                    <a class="page-btn nav" href="<?php echo htmlspecialchars(build_student_list_url(['page' => max(1, $page - 1), 'print' => null])); ?>">Prev</a>
                    <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        if ($start > 1) {
                            echo '<a class="page-btn" href="' . htmlspecialchars(build_student_list_url(['page' => 1, 'print' => null])) . '">1</a>';
                            if ($start > 2) {
                                echo '<span class="page-ellipsis">...</span>';
                            }
                        }
                        for ($p = $start; $p <= $end; $p++) {
                            $active = $p === $page ? ' active' : '';
                            echo '<a class="page-btn' . $active . '" href="' . htmlspecialchars(build_student_list_url(['page' => $p, 'print' => null])) . '">' . $p . '</a>';
                        }
                        if ($end < $totalPages) {
                            if ($end < $totalPages - 1) {
                                echo '<span class="page-ellipsis">...</span>';
                            }
                            echo '<a class="page-btn" href="' . htmlspecialchars(build_student_list_url(['page' => $totalPages, 'print' => null])) . '">' . $totalPages . '</a>';
                        }
                    ?>
                    <a class="page-btn nav" href="<?php echo htmlspecialchars(build_student_list_url(['page' => min($totalPages, $page + 1), 'print' => null])); ?>">Next</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php if ($isFinance): ?>
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon" id="deleteIconBox">
            <img src="assets/logo.png" alt="Logo" class="modal-logo" id="deleteLogo">
            <i class="fa-solid fa-triangle-exclamation" id="deleteIcon"></i>
        </div>
        <h3>Delete student?</h3>
        <p>This action cannot be undone.</p>
        <div class="modal-actions">
            <button type="button" class="modal-btn cancel" id="cancelDelete">Cancel</button>
            <a class="modal-btn confirm" id="confirmDelete" href="#">Delete</a>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="js/student_list.js"></script>
</body>
</html>
