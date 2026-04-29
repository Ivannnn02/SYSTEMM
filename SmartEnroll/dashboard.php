<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$currentUser = smartenroll_require_role('finance');
$profileFirstName = trim((string)($currentUser['full_name'] ?? 'User'));
if ($profileFirstName !== '') {
    $profileFirstNameParts = preg_split('/\s+/', $profileFirstName);
    $profileFirstName = (string)($profileFirstNameParts[0] ?? $profileFirstName);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMARTENROLL | Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="SMARTENROLL internal dashboard for managing enrollment, tuition, batch sectioning, and student records.">

    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:opsz,wght@6..96,600;6..96,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page">

<header class="landing-header">
    <a href="index.php#main-screen" class="logo" aria-label="Go to main screen">
        <img src="assets/logo.png" alt="Adreo Montessori Inc. Logo">
        <span>SMARTENROLL</span>
    </a>
    <div class="header-actions dashboard-profile-menu">
        <button type="button" class="dashboard-profile-link" id="dashboardProfileToggle" aria-label="Open profile menu" aria-expanded="false">
            <span class="dashboard-profile-avatar"><i class="fa-solid fa-user"></i></span>
            <span class="dashboard-profile-trigger-text"><?php echo htmlspecialchars($profileFirstName, ENT_QUOTES, 'UTF-8'); ?></span>
            <i class="fa-solid fa-chevron-down dashboard-profile-chevron"></i>
        </button>
        <div class="dashboard-profile-dropdown" id="dashboardProfileDropdown">
            <div class="dashboard-profile-summary">
                <span class="dashboard-profile-name"><?php echo htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="dashboard-profile-role">Finance</span>
                <span class="dashboard-profile-email"><?php echo htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <a href="editable_enrollment_form.php" class="dashboard-profile-item">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>Edit Enrollment Form</span>
            </a>
            <a href="edit_tuition_fee.php" class="dashboard-profile-item">
                <i class="fa-solid fa-coins"></i>
                <span>Edit Tuition Fee</span>
            </a>
            <a href="dashboard.php" class="dashboard-profile-item">
                <i class="fa-solid fa-id-badge"></i>
                <span>Account Details</span>
            </a>
            <a href="login.php?action=logout" class="dashboard-profile-item">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</header>

<main class="dashboard-main">
    <div class="dashboard-header">
        <div>
            <h1>Welcome to SMARTENROLL</h1>
            <p>Finance manages enrollment, requirements, billing, and receipts from this dashboard.</p>
        </div>
    </div>

    <div class="dashboard-grid">
        <a href="enroll.php" class="dash-card">
            <div class="dash-icon">
                <i class="fa-solid fa-clipboard-list"></i>
            </div>
            <div class="dash-content">
                <span class="dash-tag">Admissions</span>
                <h2>Enrollment</h2>
                <p>Manage applications and requirements.</p>
            </div>
            <span class="dash-arrow"><i class="fa-solid fa-arrow-right"></i></span>
        </a>

        <a href="batch_sectioning.php" class="dash-card">
            <div class="dash-icon">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div class="dash-content">
                <span class="dash-tag">Class Setup</span>
                <h2>Batch &amp; Sectioning</h2>
                <p>Organize sections and class groupings.</p>
            </div>
            <span class="dash-arrow"><i class="fa-solid fa-arrow-right"></i></span>
        </a>

        <a href="track_tuition.php" class="dash-card">
            <div class="dash-icon">
                <i class="fa-solid fa-receipt"></i>
            </div>
            <div class="dash-content">
                <span class="dash-tag">Finance</span>
                <h2>Track Tuition</h2>
                <p>Monitor balances and payment history.</p>
            </div>
            <span class="dash-arrow"><i class="fa-solid fa-arrow-right"></i></span>
        </a>

        <a href="tuition_receipt.php" class="dash-card">
            <div class="dash-icon">
                <i class="fa-solid fa-money-check-dollar"></i>
            </div>
            <div class="dash-content">
                <span class="dash-tag">Receipts</span>
                <h2>Tuition Receipt</h2>
                <p>Generate tuition breakdown receipts and email them to students.</p>
            </div>
            <span class="dash-arrow"><i class="fa-solid fa-arrow-right"></i></span>
        </a>

        <a href="student_list.php" class="dash-card">
            <div class="dash-icon">
                <i class="fa-solid fa-user-graduate"></i>
            </div>
            <div class="dash-content">
                <span class="dash-tag">Records</span>
                <h2>Student List</h2>
                <p>View enrolled students and details.</p>
            </div>
            <span class="dash-arrow"><i class="fa-solid fa-arrow-right"></i></span>
        </a>

        <a href="requirements_upload.php" class="dash-card">
            <div class="dash-icon">
                <i class="fa-solid fa-folder-open"></i>
            </div>
            <div class="dash-content">
                <span class="dash-tag">Requirements</span>
                <h2>Upload Requirements</h2>
                <p>Upload and check student requirement files.</p>
            </div>
            <span class="dash-arrow"><i class="fa-solid fa-arrow-right"></i></span>
        </a>
    </div>
</main>

<script src="js/dashboard.js"></script>
</body>
</html>
