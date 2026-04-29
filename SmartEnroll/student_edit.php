<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/enrollment_form_config.php';
require_once __DIR__ . '/enrollment_fields.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

smartenroll_require_role('finance');

function labelize(string $key): string
{
    return smartenroll_field_labelize($key, $GLOBALS['smartenroll_custom_field_map'] ?? []);
}

function normalizeDateValue(string $value): string
{
    return smartenroll_normalize_date_value($value);
}

function inputTypeFor(string $column): string
{
    return smartenroll_input_type_for($column, $GLOBALS['smartenroll_custom_field_map'] ?? []);
}

function ageFromDob(string $value): string
{
    return smartenroll_age_from_dob($value);
}

function studentEditSections(array $columns): array
{
    return smartenroll_build_sections($columns);
}

$gradeLevels = smartenroll_get_grade_levels();
$GLOBALS['smartenroll_custom_field_map'] = smartenroll_get_field_label_map();

$student = null;
$columns = [];
$error = '';
$showPopup = isset($_GET['saved']) && $_GET['saved'] === '1';

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

    $skip = ['id', 'created_at'];
    $readOnly = ['student_id', 'school_year', 'created_at'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = [];
        foreach ($columns as $col) {
            if (in_array($col, $skip, true)) {
                continue;
            }
            if (isset($_POST[$col])) {
                $data[$col] = trim((string)$_POST[$col]);
            }
        }

        if (array_key_exists('completion_date', $data)) {
            $completionDateRaw = $data['completion_date'];
            $ts = $completionDateRaw !== '' ? strtotime($completionDateRaw) : false;
            if ($ts !== false) {
                $month = (int)date('n', $ts);
                $year = (int)date('Y', $ts);
                $startYear = ($month >= 6) ? $year : ($year - 1);
                $data['school_year'] = $startYear . '-' . ($startYear + 1);
            } else {
                $data['school_year'] = '';
            }
        }

        if (array_key_exists('dob', $data)) {
            $data['age'] = ageFromDob($data['dob']);
        }

        if (($data['medication'] ?? '') !== 'yes') {
            $data['medication_details'] = '';
        }

        if (!empty($data)) {
            $set = [];
            $types = '';
            $values = [];
            foreach ($data as $col => $val) {
                $set[] = "`$col` = ?";
                $types .= 's';
                $values[] = $val;
            }
            $types .= 'i';
            $values[] = $id;

            $sql = "UPDATE `enrollments` SET " . implode(', ', $set) . " WHERE `id` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $stmt->close();

            header('Location: student_edit.php?id=' . $id . '&saved=1');
            exit;
        }
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

    $sectionMap = studentEditSections($columns);
} catch (Throwable $e) {
    $error = $e->getMessage();
    $skip = ['id', 'created_at'];
    $readOnly = ['student_id', 'school_year', 'created_at'];
    $sectionMap = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMARTENROLL | Edit Student</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/student_edit.css">
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
                <h1>Edit Student</h1>
                <p>Update all saved enrollment form details for this student.</p>
            </div>
        </div>
    </div>

    <div class="student-edit-card">
        <?php if ($error): ?>
            <div class="student-error">
                <strong>Unable to load student.</strong>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php else: ?>
            <?php if ($showPopup): ?>
                <div id="successPopup" class="popup-overlay">
                    <div class="popup-box">
                        <div class="popup-icon success-icon" id="successIcon">
                            <img src="assets/logo.png" id="successLogo" alt="Logo">
                            <i class="fas fa-check" id="successCheck"></i>
                        </div>

                        <h2>Changes Saved!</h2>
                        <p>The student record was updated successfully.</p>
                        <button class="popup-btn" id="closeSuccess">OK</button>
                    </div>
                </div>
            <?php endif; ?>
            <form method="post">
                <?php foreach ($sectionMap as $sectionTitle => $fields): ?>
                    <div class="detail-section">
                        <h3 class="detail-section-title"><?php echo htmlspecialchars($sectionTitle); ?></h3>
                        <div class="student-edit-grid">
                                <?php foreach ($fields as $col): ?>
                                <?php if (in_array($col, $skip, true)) { continue; } ?>
                                <label class="edit-item">
                                    <span class="detail-label"><?php echo htmlspecialchars(labelize($col)); ?></span>
                                    <?php $val = (string)($student[$col] ?? ''); ?>
                                    <?php $customField = $GLOBALS['smartenroll_custom_field_map'][$col] ?? null; ?>
                                    <?php if ($customField !== null && inputTypeFor($col) === 'select'): ?>
                                        <select name="<?php echo htmlspecialchars($col); ?>">
                                            <option value="">Select</option>
                                            <?php foreach (smartenroll_custom_field_options($customField) as $option): ?>
                                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $val === $option ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($option); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($customField !== null && inputTypeFor($col) === 'textarea'): ?>
                                        <textarea
                                            name="<?php echo htmlspecialchars($col); ?>"
                                            rows="4"
                                        ><?php echo htmlspecialchars($val); ?></textarea>
                                    <?php elseif ($col === 'learner_ext'): ?>
                                        <select name="learner_ext">
                                            <?php
                                                $extOptions = ['' => 'None', 'Jr' => 'Jr.', 'Sr' => 'Sr.', 'II' => 'II', 'III' => 'III'];
                                                foreach ($extOptions as $optVal => $optLabel):
                                                    $selected = ($val === $optVal) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($optLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($col === 'sex'): ?>
                                        <select name="sex">
                                            <?php
                                                $sexOptions = ['' => 'Select', 'Male' => 'Male', 'Female' => 'Female'];
                                                foreach ($sexOptions as $optVal => $optLabel):
                                                    $selected = ($val === $optVal) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($optLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($col === 'student_status'): ?>
                                        <select name="student_status">
                                            <option value="">Select Status</option>
                                            <?php foreach (smartenroll_student_status_options() as $optVal): ?>
                                                <option value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $val === $optVal ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($optVal); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($col === 'guardian_type'): ?>
                                        <select name="guardian_type">
                                            <?php
                                                $gOptions = ['' => 'Select', 'other' => 'Other', 'mother' => 'Mother', 'father' => 'Father'];
                                                foreach ($gOptions as $optVal => $optLabel):
                                                    $selected = ($val === $optVal) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($optLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($col === 'grade_level'): ?>
                                        <select name="grade_level">
                                            <?php foreach ($gradeLevels as $gradeLevel): ?>
                                                <?php
                                                    $optVal = (string)$gradeLevel['grade_key'];
                                                    $optLabel = (string)$gradeLevel['grade_label'];
                                                    $selected = ($val === $optVal) ? 'selected' : '';
                                                ?>
                                                <option value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($optLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif (in_array($col, ['guardian_lname', 'guardian_fname', 'guardian_mname', 'guardian_occ', 'guardian_contact'], true)): ?>
                                        <input
                                            type="<?php echo htmlspecialchars(inputTypeFor($col)); ?>"
                                            name="<?php echo htmlspecialchars($col); ?>"
                                            value="<?php echo htmlspecialchars($val); ?>"
                                            data-guardian-field="<?php echo htmlspecialchars($col); ?>"
                                        >
                                    <?php elseif ($col === 'medication'): ?>
                                        <select name="medication">
                                            <?php
                                                $mOptions = ['' => 'Select', 'yes' => 'Yes', 'no' => 'No'];
                                                foreach ($mOptions as $optVal => $optLabel):
                                                    $selected = ($val === $optVal) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($optLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif (in_array($col, ['dob', 'completion_date'], true)): ?>
                                        <input
                                            type="date"
                                            name="<?php echo htmlspecialchars($col); ?>"
                                            value="<?php echo htmlspecialchars(normalizeDateValue($val)); ?>"
                                        >
                                    <?php elseif ($col === 'age'): ?>
                                        <input
                                            type="number"
                                            name="age"
                                            value="<?php echo htmlspecialchars($val); ?>"
                                            readonly
                                        >
                                    <?php elseif (in_array($col, ['special_needs', 'medication_details'], true)): ?>
                                        <textarea
                                            name="<?php echo htmlspecialchars($col); ?>"
                                            rows="4"
                                        ><?php echo htmlspecialchars($val); ?></textarea>
                                    <?php else: ?>
                                        <input
                                            type="<?php echo htmlspecialchars(inputTypeFor($col)); ?>"
                                            name="<?php echo htmlspecialchars($col); ?>"
                                            value="<?php echo htmlspecialchars($val); ?>"
                                            <?php echo in_array($col, $readOnly, true) ? 'readonly' : ''; ?>
                                        >
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="edit-actions">
                    <button type="submit" class="edit-save">Save Changes</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<script src="js/student_edit.js"></script>
</body>
</html>
