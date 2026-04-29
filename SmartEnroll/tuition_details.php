<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/enrollment_form_config.php';
smartenroll_auth_start_session();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/mail/PHPMailer/mail_helper.php';

$currentUser = smartenroll_require_role('finance');

$student = null;
$error = '';
$successMessage = $_SESSION['tuition_details_success'] ?? '';
$warningMessage = $_SESSION['tuition_details_warning'] ?? '';
$lastEmailError = '';
unset($_SESSION['tuition_details_success'], $_SESSION['tuition_details_warning']);

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

function format_money(?float $amount): string
{
    return 'PHP ' . number_format((float)$amount, 2);
}

function send_tuition_details_email(array $student, float $totalTuition, float $amountPaid, float $remainingBalance): bool
{
    global $lastEmailError;

    $to = trim((string)($student['email'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $lastEmailError = 'This student does not have a valid enrollment email address.';
        return false;
    }

    $studentName = format_name($student);
    $subject = 'SMARTENROLL Tuition Details - ' . ($student['student_id'] ?? '');
    $html = '
    <html>
    <body style="margin:0;padding:24px;background:#f5f7fb;font-family:Arial,sans-serif;color:#1f2937;">
        <div style="max-width:720px;margin:0 auto;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #e5e7eb;">
            <div style="padding:24px 28px;background:linear-gradient(135deg,#19325a,#1e88e5);color:#ffffff;">
                <h2 style="margin:0 0 8px;">Tuition Details</h2>
                <p style="margin:0;font-size:14px;opacity:.92;">This tuition summary was sent from SMARTENROLL.</p>
            </div>
            <div style="padding:28px;">
                <p style="margin-top:0;">Good day,</p>
                <p>Here are the current tuition details for <strong>' . htmlspecialchars($studentName) . '</strong>.</p>
                <table style="width:100%;border-collapse:collapse;margin:18px 0;">
                    <tr><td style="padding:10px;border:1px solid #e5e7eb;background:#f8fafc;">Student ID</td><td style="padding:10px;border:1px solid #e5e7eb;">' . htmlspecialchars((string)($student['student_id'] ?? '')) . '</td></tr>
                    <tr><td style="padding:10px;border:1px solid #e5e7eb;background:#f8fafc;">Student Name</td><td style="padding:10px;border:1px solid #e5e7eb;">' . htmlspecialchars($studentName) . '</td></tr>
                    <tr><td style="padding:10px;border:1px solid #e5e7eb;background:#f8fafc;">Grade Level</td><td style="padding:10px;border:1px solid #e5e7eb;">' . htmlspecialchars((string)($student['grade_level'] ?? '')) . '</td></tr>
                    <tr><td style="padding:10px;border:1px solid #e5e7eb;background:#f8fafc;">School Year</td><td style="padding:10px;border:1px solid #e5e7eb;">' . htmlspecialchars((string)($student['school_year'] ?? '')) . '</td></tr>
                    <tr><td style="padding:10px;border:1px solid #e5e7eb;background:#f8fafc;">Total Tuition</td><td style="padding:10px;border:1px solid #e5e7eb;">' . htmlspecialchars(format_money($totalTuition)) . '</td></tr>
                    <tr><td style="padding:10px;border:1px solid #e5e7eb;background:#f8fafc;">Amount Paid</td><td style="padding:10px;border:1px solid #e5e7eb;">' . htmlspecialchars(format_money($amountPaid)) . '</td></tr>
                    <tr><td style="padding:10px;border:1px solid #e5e7eb;background:#f8fafc;">Remaining Balance</td><td style="padding:10px;border:1px solid #e5e7eb;">' . htmlspecialchars(format_money($remainingBalance)) . '</td></tr>
                </table>
                <p style="margin-bottom:0;">Thank you.<br>SMARTENROLL / Adreo Montessori Inc.</p>
            </div>
        </div>
    </body>
    </html>';

    $text = implode("\r\n", [
        'Tuition Details',
        '',
        'Student ID: ' . ($student['student_id'] ?? ''),
        'Student Name: ' . $studentName,
        'Grade Level: ' . ($student['grade_level'] ?? ''),
        'School Year: ' . ($student['school_year'] ?? ''),
        'Total Tuition: ' . format_money($totalTuition),
        'Amount Paid: ' . format_money($amountPaid),
        'Remaining Balance: ' . format_money($remainingBalance),
        '',
        'SMARTENROLL / Adreo Montessori Inc.',
    ]);

    return smtp_send_mail($to, $subject, $html, $text, $lastEmailError);
}

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');

    $studentId = trim((string)($_GET['student_id'] ?? $_POST['student_id'] ?? ''));
    if ($studentId === '') {
        throw new RuntimeException('Missing student ID.');
    }

    $stmt = $conn->prepare("SELECT id, student_id, learner_lname, learner_fname, learner_mname, grade_level, school_year, completion_date, email FROM enrollments WHERE student_id = ? LIMIT 1");
    $stmt->bind_param('s', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if (!$student) {
        throw new RuntimeException('Student not found.');
    }

} catch (Throwable $e) {
    $error = $e->getMessage();
}

$studentName = $student ? format_name($student) : '';
$schoolYear = $student['school_year'] ?? '';
$completionDate = $student['completion_date'] ?? '';
$gradeLevel = $student['grade_level'] ?? '';

$totalTuition = isset($conn) && $conn instanceof mysqli
    ? (smartenroll_resolve_grade_tuition_fee($gradeLevel, $conn) ?? 0.0)
    : (smartenroll_resolve_grade_tuition_fee($gradeLevel) ?? 0.0);
$amountPaid = 0.0;

if ($student && isset($conn) && $conn instanceof mysqli) {
    $selectedEnrollmentId = (int)($student['id'] ?? 0);
    $selectedSchoolYear = trim((string)($student['school_year'] ?? ''));

    $tableCheck = $conn->query("SHOW TABLES LIKE 'tuition_payments'");
    $hasPaymentsTable = $tableCheck && $tableCheck->num_rows > 0;
    if ($tableCheck) {
        $tableCheck->close();
    }

    if ($hasPaymentsTable && $selectedEnrollmentId > 0) {
        $paymentStmt = $conn->prepare(
            "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid
             FROM tuition_payments
             WHERE enrollment_id = ?
               AND COALESCE(school_year, '') = ?"
        );
        $paymentStmt->bind_param('is', $selectedEnrollmentId, $selectedSchoolYear);
        $paymentStmt->execute();
        $paymentRow = $paymentStmt->get_result()->fetch_assoc();
        $paymentStmt->close();
        $amountPaid = (float)($paymentRow['total_paid'] ?? 0);
    }
}

$remainingBalance = max(0, round($totalTuition - $amountPaid, 2));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $csrfToken = trim((string)($_POST['csrf_token'] ?? ''));
    if (!smartenroll_verify_csrf($csrfToken, 'tuition_details_form')) {
        $warningMessage = 'Session verification failed. Please refresh and try again.';
    }

    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'send_tuition_details' && $warningMessage === '') {
        $sent = send_tuition_details_email($student, $totalTuition, $amountPaid, $remainingBalance);
        if ($sent) {
            $_SESSION['tuition_details_success'] = 'Tuition details sent to ' . trim((string)($student['email'] ?? '')) . '.';
        } else {
            $_SESSION['tuition_details_warning'] = $lastEmailError ?: 'The tuition details email could not be sent.';
        }

        header('Location: tuition_details.php?student_id=' . urlencode((string)$studentId));
        exit;
    }
}

$tuitionDetailsCsrfToken = smartenroll_csrf_token('tuition_details_form');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMARTENROLL | Tuition Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/tuition_details.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page dashboard-white-page">

<main class="dashboard-main">
    <div class="dashboard-header tuition-header tuition-header-bar">
        <div class="student-header-left">
            <a href="track_tuition.php" class="dashboard-link back-left"><i class="fa-solid fa-arrow-left"></i></a>
            <div class="student-header-title">
                <h1>Tuition Details</h1>
                <p>Payment profile for the selected student.</p>
            </div>
        </div>
    </div>

    <section class="tuition-section">
        <div class="tuition-card tuition-form-card">
            <?php if ($successMessage): ?>
                <div class="tuition-alert success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>
            <?php if ($warningMessage): ?>
                <div class="tuition-alert warning"><?php echo htmlspecialchars($warningMessage); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="student-error"><strong>Unable to load student.</strong> <?php echo htmlspecialchars($error); ?></div>
            <?php else: ?>
                <div class="tuition-overview">
                    <div class="tuition-overview-copy">
                        <span class="tuition-overview-label">Student Billing Profile</span>
                        <h2><?php echo htmlspecialchars($studentName); ?></h2>
                        <p>Review the billing details, payment setup, and current tuition standing for this student.</p>
                    </div>
                    <div class="tuition-overview-meta">
                        <div class="tuition-meta-chip">
                            <span>Grade Level</span>
                            <strong><?php echo htmlspecialchars($student['grade_level'] ?? '—'); ?></strong>
                        </div>
                        <div class="tuition-meta-chip">
                            <span>School Year</span>
                            <strong><?php echo htmlspecialchars($schoolYear ?: '—'); ?></strong>
                        </div>
                        <div class="tuition-meta-chip">
                            <span>Posting Date</span>
                            <strong><?php echo htmlspecialchars($completionDate ?: date('Y-m-d')); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="tuition-summary-grid">
                    <div class="tuition-summary-card">
                        <span>Total Tuition</span>
                        <strong><?php echo htmlspecialchars(format_money($totalTuition)); ?></strong>
                        <small>Based on the student's enrolled grade level.</small>
                    </div>
                    <div class="tuition-summary-card">
                        <span>Amount Paid</span>
                        <strong><?php echo htmlspecialchars(format_money($amountPaid)); ?></strong>
                        <small>Total payments recorded for this student.</small>
                    </div>
                    <div class="tuition-summary-card accent">
                        <span>Remaining Balance</span>
                        <strong><?php echo htmlspecialchars(format_money($remainingBalance)); ?></strong>
                        <small>Computed as total tuition minus amount paid.</small>
                    </div>
                </div>

                <div class="tuition-form-section">
                    <div class="tuition-section-head">
                        <h3>Payment Information</h3>
                        <p>Use this section to review the current payment setup for the student.</p>
                    </div>

                    <div class="tuition-form-grid">
                        <div class="tuition-field">
                            <label>Customer/ID No.</label>
                            <input type="text" value="<?php echo htmlspecialchars($student['student_id'] ?? '—'); ?>">
                        </div>
                        <div class="tuition-field">
                            <label>Posting Date</label>
                            <input type="text" value="<?php echo htmlspecialchars($completionDate ?: date('Y-m-d')); ?>">
                        </div>
                        <div class="tuition-field">
                            <label>Mode of Payment</label>
                            <select>
                                <option value="">Select payment mode</option>
                                <option value="Cash">Cash</option>
                                <option value="Online Payment">Online Payment</option>
                            </select>
                        </div>
                        <div class="tuition-field">
                            <label>Official Receipt No.</label>
                            <input type="text" value="" placeholder="Enter official receipt number">
                        </div>
                        <div class="tuition-field">
                            <label>Student Name</label>
                            <input type="text" value="<?php echo htmlspecialchars($studentName); ?>">
                        </div>
                        <div class="tuition-field">
                            <label>School Year</label>
                            <input type="text" value="<?php echo htmlspecialchars($schoolYear ?: '—'); ?>">
                        </div>
                        <div class="tuition-field">
                            <label>Campus/Branch</label>
                            <input type="text" value="Adreo Montessori Incorporated">
                        </div>
                        <div class="tuition-field">
                            <label>Semester</label>
                            <select>
                                <option value="">Select semester</option>
                                <option value="1st Semester">1st Semester</option>
                                <option value="2nd Semester">2nd Semester</option>
                            </select>
                        </div>
                        <div class="tuition-field tuition-field-wide">
                            <label>Teller/Cashier Name</label>
                            <input type="text" value="adreomontessori@gmail.com">
                        </div>
                    </div>
                </div>

                <hr class="tuition-divider">

                <div class="tuition-form-section">
                    <div class="tuition-section-head">
                        <h3>Balance Summary</h3>
                        <p>Current billing totals pulled from the student's tuition records.</p>
                    </div>

                    <div class="tuition-form-grid tuition-balance-grid">
                        <div class="tuition-field">
                            <label>Total Tuition</label>
                            <input type="text" value="<?php echo htmlspecialchars(format_money($totalTuition)); ?>" readonly>
                        </div>
                        <div class="tuition-field">
                            <label>Amount Paid</label>
                            <input type="text" value="<?php echo htmlspecialchars(format_money($amountPaid)); ?>" readonly>
                        </div>
                        <div class="tuition-field">
                            <label>Remaining Balance</label>
                            <input type="text" value="<?php echo htmlspecialchars(format_money($remainingBalance)); ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="tuition-actions">
                    <form method="post" action="tuition_details.php">
                        <input type="hidden" name="action" value="send_tuition_details">
                        <input type="hidden" name="student_id" value="<?php echo htmlspecialchars((string)$student['student_id']); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($tuitionDetailsCsrfToken); ?>">
                        <button type="submit" class="tuition-email-btn" <?php echo filter_var((string)($student['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? '' : 'disabled'; ?>>
                            <i class="fa-solid fa-paper-plane"></i>
                            Send Tuition Details to Email
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<script src="js/tuition_details.js"></script></body>
</html>





















