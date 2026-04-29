<?php
require_once __DIR__ . '/auth.php';

smartenroll_auth_start_session();
smartenroll_require_role('finance');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$selectedId = trim((string)($_GET['student_id'] ?? $_POST['student_id'] ?? ''));
$selectedStudent = null;
$error = '';
$successMessage = $_SESSION['requirements_success'] ?? '';
unset($_SESSION['requirements_success']);

$requirements = [
    'picture_2x2' => '2x2 Picture',
    'birth_certificate' => 'Photocopy of Birth Certificate',
    'medical_certificate' => 'Medical Certificate',
];
$uploadedFiles = [];

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

function get_student(mysqli $conn, string $studentId): ?array
{
    $stmt = $conn->prepare(
        "SELECT id, student_id, learner_lname, learner_fname, learner_mname, grade_level, school_year
         FROM enrollments
         WHERE student_id = ?
         LIMIT 1"
    );
    $stmt->bind_param('s', $studentId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $student;
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

function load_uploaded_files(mysqli $conn, int $enrollmentId): array
{
    $files = [];
    $stmt = $conn->prepare(
        "SELECT requirement_key, original_name, stored_name, file_path, uploaded_at
         FROM student_requirements
         WHERE enrollment_id = ?"
    );
    $stmt->bind_param('i', $enrollmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $files[$row['requirement_key']] = $row;
    }
    $stmt->close();

    return $files;
}

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');
    ensure_requirements_table($conn);

    if ($selectedId === '') {
        throw new RuntimeException('Please choose a student from the Upload Requirements page first.');
    }

    $selectedStudent = get_student($conn, $selectedId);
    if (!$selectedStudent) {
        throw new RuntimeException('The selected student could not be found.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $requirementKey = trim((string)($_POST['requirement_key'] ?? ''));
        if (!array_key_exists($requirementKey, $requirements)) {
            throw new RuntimeException('Please choose a valid requirement.');
        }

        if (!isset($_FILES['requirement_file']) || !is_array($_FILES['requirement_file'])) {
            throw new RuntimeException('Please choose a file to upload.');
        }

        $file = $_FILES['requirement_file'];
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The selected file could not be uploaded.');
        }

        $originalName = trim((string)($file['name'] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Only JPG, JPEG, PNG, and PDF files are allowed.');
        }

        $uploadDir = __DIR__ . '/uploads/requirements';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('The requirements upload folder could not be created.');
        }

        $safeStudentId = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$selectedStudent['student_id']);
        $storedName = $safeStudentId . '_' . $requirementKey . '_' . time() . '.' . $extension;
        $targetPath = $uploadDir . '/' . $storedName;

        if (!move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
            throw new RuntimeException('The uploaded file could not be saved.');
        }

        $relativePath = 'uploads/requirements/' . $storedName;
        $existingFiles = load_uploaded_files($conn, (int)$selectedStudent['id']);
        if (isset($existingFiles[$requirementKey])) {
            $oldPath = __DIR__ . '/' . $existingFiles[$requirementKey]['file_path'];
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $stmt = $conn->prepare(
            "INSERT INTO student_requirements (enrollment_id, student_id, requirement_key, original_name, stored_name, file_path)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                student_id = VALUES(student_id),
                original_name = VALUES(original_name),
                stored_name = VALUES(stored_name),
                file_path = VALUES(file_path),
                uploaded_at = CURRENT_TIMESTAMP"
        );
        $stmt->bind_param(
            'isssss',
            $selectedStudent['id'],
            $selectedStudent['student_id'],
            $requirementKey,
            $originalName,
            $storedName,
            $relativePath
        );
        $stmt->execute();
        $stmt->close();

        $_SESSION['requirements_success'] = $requirements[$requirementKey] . ' uploaded successfully.';
        header('Location: requirements_upload_details.php?student_id=' . urlencode($selectedId));
        exit;
    }

    $uploadedFiles = load_uploaded_files($conn, (int)$selectedStudent['id']);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$studentName = $selectedStudent ? format_name($selectedStudent) : '';
$completedCount = 0;
foreach (array_keys($requirements) as $key) {
    if (isset($uploadedFiles[$key])) {
        $completedCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMARTENROLL | Requirement Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/requirements_upload.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page dashboard-white-page">
<main class="dashboard-main requirements-main">
    <section class="requirements-hero detail-hero">
        <div class="requirements-hero-top">
            <a href="requirements_upload.php" class="dashboard-link back-left"><i class="fa-solid fa-arrow-left"></i></a>
            <div class="requirements-hero-copy">
                <span class="eyebrow eyebrow-gold">File Desk</span>
                <h1>Requirement Details</h1>
                <p>Upload files, verify completion, and keep every requirement in one student record.</p>
            </div>
        </div>
    </section>

    <?php if ($error): ?>
        <div class="requirements-alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($successMessage): ?>
        <div class="requirements-alert success"><?php echo htmlspecialchars($successMessage); ?></div>
    <?php endif; ?>

    <?php if ($selectedStudent): ?>
        <section class="requirements-workspace">
            <aside class="requirements-sidebar">
                <div class="student-dossier">
                    <span class="eyebrow eyebrow-blue">Student Dossier</span>
                    <h2><?php echo htmlspecialchars($studentName); ?></h2>
                    <div class="dossier-list">
                        <div class="dossier-item">
                            <span>School ID</span>
                            <strong><?php echo htmlspecialchars((string)$selectedStudent['student_id']); ?></strong>
                        </div>
                        <div class="dossier-item">
                            <span>Grade Level</span>
                            <strong><?php echo htmlspecialchars((string)$selectedStudent['grade_level']); ?></strong>
                        </div>
                        <div class="dossier-item">
                            <span>School Year</span>
                            <strong><?php echo htmlspecialchars((string)$selectedStudent['school_year']); ?></strong>
                        </div>
                        <div class="dossier-item accent">
                            <span>Checklist</span>
                            <strong><?php echo $completedCount; ?>/<?php echo count($requirements); ?> Complete</strong>
                        </div>
                    </div>
                </div>

                <div class="requirements-panel checklist-panel">
                    <div class="requirements-panel-head">
                    <h3>Automatic Checklist</h3>
                    <p>The checklist marks each requirement complete once a file is uploaded.</p>
                </div>
                <div class="checklist-list">
                    <?php foreach ($requirements as $key => $label): ?>
                        <?php $isDone = isset($uploadedFiles[$key]); ?>
                        <div class="checklist-item <?php echo $isDone ? 'done' : ''; ?>">
                            <div class="checklist-icon">
                                <i class="fa-solid <?php echo $isDone ? 'fa-circle-check' : 'fa-circle'; ?>"></i>
                            </div>
                            <div class="checklist-copy">
                                <strong><?php echo htmlspecialchars($label); ?></strong>
                                <span><?php echo $isDone ? 'Uploaded' : 'Missing'; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                </div>
            </aside>

            <section class="requirements-panel upload-workbench">
                <div class="requirements-panel-head upload-workbench-head">
                    <h3>Upload Files</h3>
                    <p>Each row below is a document slot. Upload a file and the checklist updates automatically.</p>
                </div>
                <div class="upload-card-list">
                    <?php foreach ($requirements as $key => $label): ?>
                        <?php $fileInfo = $uploadedFiles[$key] ?? null; ?>
                        <form class="upload-item-card" method="post" action="requirements_upload_details.php?student_id=<?php echo urlencode($selectedId); ?>" enctype="multipart/form-data">
                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($selectedId); ?>">
                            <input type="hidden" name="requirement_key" value="<?php echo htmlspecialchars($key); ?>">
                            <div class="upload-item-head">
                                <div>
                                    <strong><?php echo htmlspecialchars($label); ?></strong>
                                    <span><?php echo $fileInfo ? 'Uploaded and checked' : 'Not uploaded yet'; ?></span>
                                </div>
                                <span class="upload-status <?php echo $fileInfo ? 'done' : 'pending'; ?>">
                                    <?php echo $fileInfo ? 'Complete' : 'Pending'; ?>
                                </span>
                            </div>
                            <label class="file-input-wrap">
                                <span>Select File</span>
                                <input type="file" name="requirement_file" accept=".jpg,.jpeg,.png,.pdf" required>
                            </label>
                            <?php if ($fileInfo): ?>
                                <div class="uploaded-file-note">
                                    <span>Current File</span>
                                    <a href="<?php echo htmlspecialchars($fileInfo['file_path']); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo htmlspecialchars((string)$fileInfo['original_name']); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <button type="submit" class="upload-submit-btn">
                                <i class="fa-solid fa-upload"></i>
                                <?php echo $fileInfo ? 'Replace File' : 'Upload File'; ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </section>
        </section>
    <?php endif; ?>
</main>

<script src="js/requirements_upload.js"></script>
</body>
</html>
