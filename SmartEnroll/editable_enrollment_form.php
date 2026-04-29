<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/enrollment_form_config.php';
require_once __DIR__ . '/enrollment_fields.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

smartenroll_require_role('finance');

function smartenroll_insert_enrollment(array $input): int
{
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');
    smartenroll_ensure_student_status_column($conn);

    try {
        $columns = [];
        $colRes = $conn->query("SHOW COLUMNS FROM `enrollments`");
        while ($row = $colRes->fetch_assoc()) {
            $columns[] = $row['Field'];
        }

        if ($columns === []) {
            throw new RuntimeException('Unable to read enrollments table columns.');
        }

        $skip = ['id', 'created_at', 'student_id'];
        $data = [];

        foreach ($columns as $col) {
            if (in_array($col, $skip, true)) {
                continue;
            }

            if (isset($input[$col])) {
                $data[$col] = trim((string)$input[$col]);
            }
        }

        if (in_array('learner_ext', $columns, true) && !array_key_exists('learner_ext', $data)) {
            $data['learner_ext'] = '';
        }

        if (in_array('medication_details', $columns, true) && !array_key_exists('medication_details', $data)) {
            $data['medication_details'] = '';
        }

        if (($data['medication'] ?? '') !== 'yes') {
            $data['medication_details'] = '';
        }

        if (isset($data['dob'])) {
            $data['age'] = smartenroll_age_from_dob($data['dob']);
        }

        $completionDateRaw = trim((string)($data['completion_date'] ?? ''));
        $ts = $completionDateRaw !== '' ? strtotime($completionDateRaw) : false;
        if ($ts === false) {
            $ts = time();
        }

        $month = (int)date('n', $ts);
        $year = (int)date('Y', $ts);
        $startYear = $month >= 6 ? $year : ($year - 1);
        $data['school_year'] = $startYear . '-' . ($startYear + 1);

        if (empty($data)) {
            throw new RuntimeException('No valid enrollment data was provided.');
        }

        $conn->begin_transaction();

        $fields = array_keys($data);
        $fieldSql = '`' . implode('`,`', $fields) . '`';
        $placeholderSql = implode(',', array_fill(0, count($fields), '?'));
        $sql = "INSERT INTO `enrollments` ($fieldSql) VALUES ($placeholderSql)";

        $stmt = $conn->prepare($sql);
        $types = str_repeat('s', count($fields));
        $values = array_values($data);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();

        $newId = (int)$conn->insert_id;
        if ($newId <= 0) {
            throw new RuntimeException('Insert failed to return ID.');
        }

        if (in_array('student_id', $columns, true)) {
            $prefix = '202600';
            $nextNumber = 1;

            while (true) {
                $candidate = $prefix . $nextNumber;
                $chk = $conn->prepare("SELECT 1 FROM `enrollments` WHERE `student_id` = ? LIMIT 1");
                $chk->bind_param('s', $candidate);
                $chk->execute();
                $exists = $chk->get_result()->num_rows > 0;
                $chk->close();

                if (!$exists) {
                    break;
                }

                $nextNumber++;
            }

            $studentId = $prefix . $nextNumber;
            $up = $conn->prepare("UPDATE `enrollments` SET `student_id` = ? WHERE `id` = ?");
            $up->bind_param('si', $studentId, $newId);
            $up->execute();
            $up->close();
        }

        $conn->commit();
        return $newId;
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $ignore) {
        }
        throw $e;
    } finally {
        $conn->close();
    }
}

$errorMessage = '';
$successMessage = '';
$enrollmentMessage = '';
$enrollmentError = '';
$fieldActionMessage = '';
$fieldActionError = '';
$customFieldMessage = '';
$customFieldError = '';
$columns = [];
$sectionMap = [];
$formValues = [];
$builtinFieldRows = [];
$builtinFieldKeys = [];
$customFieldMap = [];
$customFieldsBySection = [];
$customFieldRows = [];
$submittedCustomFieldRows = [];
$customFieldSectionChoices = array_values(array_filter(
    smartenroll_supported_enrollment_sections(),
    static fn(string $sectionName): bool => $sectionName !== 'Grade Level'
));
$readOnly = ['school_year'];
$skip = ['id', 'student_id', 'created_at'];
$hiddenOtherSavedFields = [
    'grade_level',
    'school_year',
    'enrollment_status',
    'requirements_status',
    'payment_status',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save_grade_levels') {
    try {
        $currentGradeLevels = smartenroll_get_grade_levels();
        $rows = [];
        $gradeKeys = $_POST['grade_key'] ?? [];
        $gradeLabels = $_POST['grade_label'] ?? [];
        $max = max(count($gradeKeys), count($gradeLabels));

        for ($i = 0; $i < $max; $i++) {
            $rows[] = [
                'grade_key' => $gradeKeys[$i] ?? '',
                'grade_label' => $gradeLabels[$i] ?? '',
                'tuition_fee' => $currentGradeLevels[$i]['tuition_fee'] ?? 0,
            ];
        }

        smartenroll_save_grade_levels($rows);
        header('Location: editable_enrollment_form.php?status=saved');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save_field_label_inline') {
    try {
        $fieldKey = trim((string)($_POST['field_key'] ?? ''));
        $fieldLabel = trim((string)($_POST['field_label'] ?? ''));
        $rows = smartenroll_builtin_field_rows(null, true);
        $saveRows = [];
        $found = false;

        foreach ($rows as $row) {
            $rowKey = (string)$row['field_key'];
            $isDeleted = (int)($row['is_active'] ?? 1) === 1 ? '0' : '1';
            $label = (string)$row['field_label'];

            if ($rowKey === $fieldKey) {
                $found = true;
                $label = $fieldLabel;
                $isDeleted = '0';
            }

            $saveRows[] = [
                'field_key' => $rowKey,
                'field_label' => $label,
                'is_deleted' => $isDeleted,
            ];
        }

        if (!$found) {
            throw new RuntimeException('Field not found.');
        }

        smartenroll_save_field_label_overrides($saveRows);
        header('Location: editable_enrollment_form.php?field_action=updated');
        exit;
    } catch (Throwable $e) {
        $fieldActionError = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'delete_field_label_inline') {
    try {
        $fieldKey = trim((string)($_POST['field_key'] ?? ''));
        $rows = smartenroll_builtin_field_rows(null, true);
        $saveRows = [];
        $found = false;

        foreach ($rows as $row) {
            $rowKey = (string)$row['field_key'];
            $isDeleted = (int)($row['is_active'] ?? 1) === 1 ? '0' : '1';

            if ($rowKey === $fieldKey) {
                $found = true;
                $isDeleted = '1';
            }

            $saveRows[] = [
                'field_key' => $rowKey,
                'field_label' => (string)$row['field_label'],
                'is_deleted' => $isDeleted,
            ];
        }

        if (!$found) {
            throw new RuntimeException('Field not found.');
        }

        smartenroll_save_field_label_overrides($saveRows);
        header('Location: editable_enrollment_form.php?field_action=deleted');
        exit;
    } catch (Throwable $e) {
        $fieldActionError = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save_custom_fields') {
    try {
        $rows = [];
        $fieldKeys = $_POST['custom_field_key'] ?? [];
        $fieldLabels = $_POST['custom_field_label'] ?? [];
        $sectionNames = $_POST['custom_field_section'] ?? [];
        $inputTypes = $_POST['custom_field_input_type'] ?? [];
        $fieldOptions = $_POST['custom_field_options'] ?? [];
        $max = max(
            count($fieldKeys),
            count($fieldLabels),
            count($sectionNames),
            count($inputTypes),
            count($fieldOptions)
        );

        for ($i = 0; $i < $max; $i++) {
            $rows[] = [
                'field_key' => $fieldKeys[$i] ?? '',
                'field_label' => $fieldLabels[$i] ?? '',
                'section_name' => $sectionNames[$i] ?? '',
                'input_type' => $inputTypes[$i] ?? 'text',
                'field_options' => $fieldOptions[$i] ?? '',
            ];
        }

        $submittedCustomFieldRows = $rows;
        smartenroll_save_custom_fields($rows);
        header('Location: editable_enrollment_form.php?custom_field_status=saved');
        exit;
    } catch (Throwable $e) {
        $customFieldError = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save_enrollment_form') {
    $formValues = $_POST;

    try {
        smartenroll_insert_enrollment($_POST);
        header('Location: editable_enrollment_form.php?enrollment_status=saved');
        exit;
    } catch (Throwable $e) {
        $enrollmentError = $e->getMessage();
    }
}

if (($_GET['status'] ?? '') === 'saved') {
    $successMessage = 'Enrollment form settings were saved successfully.';
}

if (($_GET['enrollment_status'] ?? '') === 'saved') {
    $enrollmentMessage = 'Enrollment form was submitted successfully.';
}

if (($_GET['field_action'] ?? '') === 'updated') {
    $fieldActionMessage = 'Field label was updated successfully.';
}

if (($_GET['field_action'] ?? '') === 'deleted') {
    $fieldActionMessage = 'Field was deleted successfully.';
}

if (($_GET['custom_field_status'] ?? '') === 'saved') {
    $customFieldMessage = 'Custom fields were saved successfully.';
}

try {
    $gradeLevels = smartenroll_get_grade_levels();
} catch (Throwable $e) {
    $gradeLevels = [];
    if ($errorMessage === '') {
        $errorMessage = $e->getMessage();
    }
}

try {
    $builtinFieldRows = smartenroll_builtin_field_rows(null, true);
    foreach ($builtinFieldRows as $row) {
        $builtinFieldKeys[(string)$row['field_key']] = true;
    }
    $customFieldMap = smartenroll_get_field_label_map();
    $customFieldsBySection = smartenroll_custom_fields_by_section();
    $customFieldRows = smartenroll_get_custom_fields(null, true);
} catch (Throwable $e) {
    if ($fieldActionError === '') {
        $fieldActionError = $e->getMessage();
    }
    if ($customFieldError === '') {
        $customFieldError = $e->getMessage();
    }
}

if ($customFieldError !== '' && $submittedCustomFieldRows !== []) {
    $customFieldRows = $submittedCustomFieldRows;
}

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');
    smartenroll_ensure_student_status_column($conn);

    $colRes = $conn->query("SHOW COLUMNS FROM `enrollments`");
    while ($row = $colRes->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    $conn->close();

    $sectionMap = smartenroll_build_sections($columns, null, ['grade_level', 'school_year']);
    unset($sectionMap['Grade Level']);

    if (isset($sectionMap['Other Saved Fields'])) {
        $sectionMap['Other Saved Fields'] = array_values(array_filter(
            $sectionMap['Other Saved Fields'],
            static fn(string $field): bool => !in_array($field, $hiddenOtherSavedFields, true)
        ));

        if ($sectionMap['Other Saved Fields'] === []) {
            unset($sectionMap['Other Saved Fields']);
        }
    }

    foreach ($columns as $column) {
        $formValues[$column] = isset($formValues[$column]) ? trim((string)$formValues[$column]) : '';
    }
} catch (Throwable $e) {
    if ($enrollmentError === '') {
        $enrollmentError = $e->getMessage();
    }
}

$initialDuplicatePopup = null;
if ($errorMessage === 'This grade level already exists' || str_starts_with($errorMessage, 'This grade level already exists:')) {
    $initialDuplicatePopup = [
        'title' => 'Duplicate Grade Level',
        'message' => 'This grade level already exists',
    ];
} elseif ($fieldActionError === 'This field label already exists' || str_starts_with($fieldActionError, 'This field label already exists')) {
    $initialDuplicatePopup = [
        'title' => 'Duplicate Field Label',
        'message' => 'This field label already exists',
    ];
} elseif ($customFieldError === 'This custom field already exists' || str_starts_with($customFieldError, 'This custom field already exists')) {
    $initialDuplicatePopup = [
        'title' => 'Duplicate Custom Field',
        'message' => 'This custom field already exists',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMARTENROLL | Edit Enrollment Form</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/editable_enrollment_form.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page dashboard-white-page">

<main class="dashboard-main">
    <div class="dashboard-header student-header">
        <div class="student-header-left">
            <a href="dashboard.php" class="dashboard-link back-left">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="student-header-title">
                <h1>Edit Enrollment Form</h1>
                <p>Manage the grade levels and fill out the full enrollment form in one page.</p>
            </div>
        </div>
    </div>

    <div class="settings-card">
        <?php if ($successMessage !== ''): ?>
            <div class="settings-alert success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="settings-alert error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <?php if ($fieldActionMessage !== ''): ?>
            <div class="settings-alert success"><?php echo htmlspecialchars($fieldActionMessage); ?></div>
        <?php endif; ?>

        <?php if ($fieldActionError !== '' && !($fieldActionError === 'This field label already exists' || str_starts_with($fieldActionError, 'This field label already exists'))): ?>
            <div class="settings-alert error"><?php echo htmlspecialchars($fieldActionError); ?></div>
        <?php endif; ?>

        <?php if ($customFieldMessage !== ''): ?>
            <div class="settings-alert success"><?php echo htmlspecialchars($customFieldMessage); ?></div>
        <?php endif; ?>

        <?php if ($customFieldError !== '' && !($customFieldError === 'This custom field already exists' || str_starts_with($customFieldError, 'This custom field already exists'))): ?>
            <div class="settings-alert error"><?php echo htmlspecialchars($customFieldError); ?></div>
        <?php endif; ?>

        <div class="settings-intro settings-subsection">
            <h2>Enrollment Form</h2>
            <p>Set up custom fields first, then manage grade levels and fill out the enrollment form below.</p>
        </div>

        <form method="post" id="customFieldsForm">
            <input type="hidden" name="form_action" value="save_custom_fields">
            <div>
                <h3 class="detail-section-title">Custom Fields</h3>
                <div class="settings-table-wrap">
                    <table class="settings-table">
                        <thead>
                            <tr>
                                <th>Field Name</th>
                                <th>Section</th>
                                <th>Input Type</th>
                                <th>Select Options</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="customFieldsBody">
                            <?php foreach ($customFieldRows as $fieldRow): ?>
                                <?php $inputType = strtolower(trim((string)($fieldRow['input_type'] ?? 'text'))); ?>
                                <tr>
                                    <td>
                                        <input type="hidden" name="custom_field_key[]" value="<?php echo htmlspecialchars((string)$fieldRow['field_key']); ?>">
                                        <input
                                            type="text"
                                            name="custom_field_label[]"
                                            value="<?php echo htmlspecialchars((string)$fieldRow['field_label']); ?>"
                                            placeholder="Enter field name"
                                        >
                                    </td>
                                    <td>
                                        <select name="custom_field_section[]" class="settings-table-select-section">
                                            <?php foreach ($customFieldSectionChoices as $sectionName): ?>
                                                <option value="<?php echo htmlspecialchars($sectionName); ?>" <?php echo (string)$fieldRow['section_name'] === $sectionName ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($sectionName); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="custom_field_input_type[]" class="settings-table-select-type" data-custom-field-type>
                                            <?php foreach (smartenroll_custom_field_allowed_types() as $type): ?>
                                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $inputType === $type ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars(ucfirst($type)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            name="custom_field_options[]"
                                            value="<?php echo htmlspecialchars((string)($fieldRow['field_options'] ?? '')); ?>"
                                            placeholder="Option 1, Option 2"
                                            data-custom-field-options
                                            <?php echo $inputType === 'select' ? '' : 'disabled'; ?>
                                        >
                                    </td>
                                    <td><button type="button" class="settings-row-remove" data-remove-custom-row>Remove</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="settings-actions">
                    <button type="button" class="settings-add-btn" id="addCustomFieldRow">Add Custom Field</button>
                    <button type="submit" class="settings-save-btn">Save Custom Fields</button>
                </div>
                <p class="settings-help">Use `Select Options` only when the input type is `Select`. Separate each option with a comma.</p>
            </div>
        </form>

        <form method="post" id="gradeSettingsForm">
            <input type="hidden" name="form_action" value="save_grade_levels">
            <div class="settings-subsection">
                <h3 class="detail-section-title">Grade Level</h3>
                <div class="settings-table-wrap">
                    <table class="settings-table">
                        <thead>
                            <tr>
                                <th>Grade Level Name</th>
                                <th>Shown On Enrollment Form</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="gradeSettingsBody">
                            <?php foreach ($gradeLevels as $row): ?>
                                <tr>
                                    <td><input type="text" name="grade_key[]" value="<?php echo htmlspecialchars((string)$row['grade_key']); ?>" placeholder="Grade 4"></td>
                                    <td><input type="text" name="grade_label[]" value="<?php echo htmlspecialchars((string)$row['grade_label']); ?>" placeholder="Grade 4"></td>
                                    <td><button type="button" class="settings-row-remove" data-remove-grade-row>Remove</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="settings-actions">
                    <button type="button" class="settings-add-btn" id="addGradeRow">Add Grade Level</button>
                    <button type="submit" class="settings-save-btn">Save Grade Levels</button>
                </div>
            </div>
        </form>

        <?php if ($enrollmentMessage !== ''): ?>
            <div class="settings-alert success"><?php echo htmlspecialchars($enrollmentMessage); ?></div>
        <?php endif; ?>

        <?php if ($enrollmentError !== ''): ?>
            <div class="settings-alert error"><?php echo htmlspecialchars($enrollmentError); ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="form_action" value="save_enrollment_form">

            <?php if (!empty($customFieldsBySection['Grade Level'])): ?>
                <div class="settings-table-wrap grade-custom-fields-wrap">
                    <table class="settings-table">
                        <tbody>
                            <?php foreach ($customFieldsBySection['Grade Level'] as $field): ?>
                                <?php $fieldKey = (string)$field['field_key']; ?>
                                <?php $val = (string)($formValues[$fieldKey] ?? ''); ?>
                                <tr>
                                    <td class="grade-custom-field-label"><?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?></td>
                                    <td>
                                        <?php if (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'select'): ?>
                                            <select name="<?php echo htmlspecialchars($fieldKey); ?>">
                                                <option value="">Select</option>
                                                <?php foreach (smartenroll_custom_field_options($field) as $option): ?>
                                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $val === $option ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($option); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php elseif (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'textarea'): ?>
                                            <textarea name="<?php echo htmlspecialchars($fieldKey); ?>" rows="4"><?php echo htmlspecialchars($val); ?></textarea>
                                        <?php else: ?>
                                            <input
                                                type="<?php echo htmlspecialchars(smartenroll_input_type_for($fieldKey, $customFieldMap)); ?>"
                                                name="<?php echo htmlspecialchars($fieldKey); ?>"
                                                value="<?php echo htmlspecialchars($val); ?>"
                                            >
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php foreach ($sectionMap as $sectionTitle => $fields): ?>
                <div class="detail-section">
                    <h3 class="detail-section-title"><?php echo htmlspecialchars($sectionTitle); ?></h3>
                    <div class="student-edit-grid">
                        <?php foreach ($fields as $col): ?>
                            <?php if (in_array($col, $skip, true)) { continue; } ?>
                            <?php $val = (string)($formValues[$col] ?? ''); ?>
                            <?php $customField = $customFieldMap[$col] ?? null; ?>
                            <div class="edit-item">
                                <span class="detail-label <?php echo isset($builtinFieldKeys[$col]) ? 'field-label-with-actions' : ''; ?>">
                                    <span><?php echo htmlspecialchars(smartenroll_field_labelize($col, $customFieldMap)); ?></span>
                                    <?php if (isset($builtinFieldKeys[$col])): ?>
                                        <span class="field-label-actions">
                                            <button
                                                type="button"
                                                class="field-label-action field-label-action-edit"
                                                aria-label="Edit field label"
                                                title="Edit"
                                                data-edit-field-label
                                                data-field-key="<?php echo htmlspecialchars($col); ?>"
                                                data-field-label="<?php echo htmlspecialchars(smartenroll_field_labelize($col, $customFieldMap)); ?>"
                                            >
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="field-label-action field-label-action-delete"
                                                aria-label="Delete field"
                                                title="Delete"
                                                data-delete-field-label
                                                data-field-key="<?php echo htmlspecialchars($col); ?>"
                                                data-field-label="<?php echo htmlspecialchars(smartenroll_field_labelize($col, $customFieldMap)); ?>"
                                            >
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </span>
                                    <?php endif; ?>
                                </span>

                                <?php if ($customField !== null && smartenroll_input_type_for($col, $customFieldMap) === 'select'): ?>
                                    <select name="<?php echo htmlspecialchars($col); ?>">
                                        <option value="">Select</option>
                                        <?php foreach (smartenroll_custom_field_options($customField) as $option): ?>
                                            <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $val === $option ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($option); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($customField !== null && smartenroll_input_type_for($col, $customFieldMap) === 'textarea'): ?>
                                    <textarea name="<?php echo htmlspecialchars($col); ?>" rows="4"><?php echo htmlspecialchars($val); ?></textarea>
                                <?php elseif ($col === 'learner_ext'): ?>
                                    <select name="learner_ext">
                                        <?php
                                            $extOptions = ['' => 'None', 'Jr' => 'Jr.', 'Sr' => 'Sr.', 'II' => 'II', 'III' => 'III'];
                                            foreach ($extOptions as $optVal => $optLabel):
                                                $selected = $val === $optVal ? 'selected' : '';
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
                                                $selected = $val === $optVal ? 'selected' : '';
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
                                    <div class="edit-radio-inline-group">
                                        <?php
                                            $guardianOptions = ['other' => 'Other', 'mother' => 'Mother', 'father' => 'Father'];
                                            foreach ($guardianOptions as $optVal => $optLabel):
                                                $checked = ($val === '' && $optVal === 'other') || $val === $optVal ? 'checked' : '';
                                        ?>
                                            <label class="edit-radio-inline">
                                                <input type="radio" name="guardian_type" value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $checked; ?>>
                                                <span><?php echo htmlspecialchars($optLabel); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($col === 'grade_level'): ?>
                                    <div class="edit-choice-grid" aria-label="Grade Level">
                                        <?php foreach ($gradeLevels as $gradeLevel): ?>
                                            <?php
                                                $optVal = (string)$gradeLevel['grade_key'];
                                                $optLabel = (string)$gradeLevel['grade_label'];
                                                $checked = $val === $optVal ? 'checked' : '';
                                            ?>
                                            <label class="edit-choice-option">
                                                <input type="radio" name="grade_level" value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $checked; ?>>
                                                <span class="edit-choice-button"><?php echo htmlspecialchars($optLabel); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif (in_array($col, ['guardian_lname', 'guardian_fname', 'guardian_mname', 'guardian_occ', 'guardian_contact'], true)): ?>
                                    <input
                                        type="<?php echo htmlspecialchars(smartenroll_input_type_for($col, $customFieldMap)); ?>"
                                        name="<?php echo htmlspecialchars($col); ?>"
                                        value="<?php echo htmlspecialchars($val); ?>"
                                        data-guardian-field="<?php echo htmlspecialchars($col); ?>"
                                    >
                                <?php elseif ($col === 'medication'): ?>
                                    <div class="edit-radio-inline-group">
                                        <?php
                                            $medicationOptions = ['yes' => 'Yes', 'no' => 'No'];
                                            foreach ($medicationOptions as $optVal => $optLabel):
                                                $checked = ($val === '' && $optVal === 'no') || $val === $optVal ? 'checked' : '';
                                        ?>
                                            <label class="edit-radio-inline">
                                                <input type="radio" name="medication" value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $checked; ?>>
                                                <span><?php echo htmlspecialchars($optLabel); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif (in_array($col, ['dob', 'completion_date'], true)): ?>
                                    <input
                                        type="date"
                                        name="<?php echo htmlspecialchars($col); ?>"
                                        value="<?php echo htmlspecialchars(smartenroll_normalize_date_value($val)); ?>"
                                    >
                                <?php elseif ($col === 'age'): ?>
                                    <input type="number" name="age" value="<?php echo htmlspecialchars($val); ?>" readonly>
                                <?php elseif (in_array($col, ['special_needs', 'medication_details'], true)): ?>
                                    <textarea name="<?php echo htmlspecialchars($col); ?>" rows="4"><?php echo htmlspecialchars($val); ?></textarea>
                                <?php else: ?>
                                    <input
                                        type="<?php echo htmlspecialchars(smartenroll_input_type_for($col, $customFieldMap)); ?>"
                                        name="<?php echo htmlspecialchars($col); ?>"
                                        value="<?php echo htmlspecialchars($val); ?>"
                                        <?php echo in_array($col, $readOnly, true) ? 'readonly' : ''; ?>
                                    >
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="edit-actions">
                <button type="submit" class="settings-save-btn">Submit Enrollment Form</button>
            </div>
        </form>
    </div>
</main>

<div id="duplicatePopup" class="popup-overlay" aria-hidden="true">
    <div class="popup-box" role="dialog" aria-modal="true" aria-labelledby="duplicatePopupTitle">
        <button type="button" class="popup-close" id="duplicatePopupClose" aria-label="Close popup">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="popup-icon popup-icon-error">
            <img src="assets/logo.png" alt="SMARTENROLL logo">
            <i class="fa-solid fa-circle-exclamation"></i>
        </div>
        <h2 id="duplicatePopupTitle">Duplicate Entry</h2>
        <p id="duplicatePopupMessage">This value already exists.</p>
        <button type="button" class="popup-btn" id="duplicatePopupOk">OK</button>
    </div>
</div>

<div id="editFieldPopup" class="popup-overlay" aria-hidden="true">
    <div class="popup-box" role="dialog" aria-modal="true" aria-labelledby="editFieldPopupTitle">
        <button type="button" class="popup-close" id="editFieldPopupClose" aria-label="Close popup">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="popup-icon popup-icon-primary" id="editFieldPopupIcon">
            <img src="assets/logo.png" alt="SMARTENROLL logo">
            <i class="fa-solid fa-pen"></i>
        </div>
        <h2 id="editFieldPopupTitle" class="popup-title-primary">Edit Field Name</h2>
        <p>Edit the field name below.</p>
        <input type="text" id="editFieldPopupInput" class="popup-text-input" placeholder="Enter field name">
        <div class="popup-actions-row">
            <button type="button" class="popup-btn popup-btn-secondary" id="editFieldPopupCancel">Cancel</button>
            <button type="button" class="popup-btn" id="editFieldPopupSave">Save</button>
        </div>
    </div>
</div>

<div id="deleteFieldPopup" class="popup-overlay" aria-hidden="true">
    <div class="popup-box" role="dialog" aria-modal="true" aria-labelledby="deleteFieldPopupTitle">
        <button type="button" class="popup-close" id="deleteFieldPopupClose" aria-label="Close popup">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="popup-icon popup-icon-error" id="deleteFieldPopupIcon">
            <img src="assets/logo.png" alt="SMARTENROLL logo">
            <i class="fa-solid fa-trash"></i>
        </div>
        <h2 id="deleteFieldPopupTitle">Delete Field</h2>
        <p id="deleteFieldPopupMessage">Are you sure you want to delete this field?</p>
        <div class="popup-actions-row">
            <button type="button" class="popup-btn popup-btn-secondary" id="deleteFieldPopupNo">No</button>
            <button type="button" class="popup-btn popup-btn-danger" id="deleteFieldPopupYes">Yes</button>
        </div>
    </div>
</div>

<form method="post" id="inlineEditFieldForm" class="hidden-inline-form">
    <input type="hidden" name="form_action" value="save_field_label_inline">
    <input type="hidden" name="field_key" id="inlineEditFieldKey">
    <input type="hidden" name="field_label" id="inlineEditFieldLabel">
</form>

<form method="post" id="inlineDeleteFieldForm" class="hidden-inline-form">
    <input type="hidden" name="form_action" value="delete_field_label_inline">
    <input type="hidden" name="field_key" id="inlineDeleteFieldKey">
</form>

<script>
const gradeSettingsForm = document.getElementById('gradeSettingsForm');
const gradeSettingsBody = document.getElementById('gradeSettingsBody');
const addGradeRowBtn = document.getElementById('addGradeRow');
const customFieldsForm = document.getElementById('customFieldsForm');
const customFieldsBody = document.getElementById('customFieldsBody');
const addCustomFieldRowBtn = document.getElementById('addCustomFieldRow');
const duplicatePopup = document.getElementById('duplicatePopup');
const duplicatePopupTitle = document.getElementById('duplicatePopupTitle');
const duplicatePopupMessage = document.getElementById('duplicatePopupMessage');
const duplicatePopupClose = document.getElementById('duplicatePopupClose');
const duplicatePopupOk = document.getElementById('duplicatePopupOk');
const duplicatePopupIcon = duplicatePopup ? duplicatePopup.querySelector('.popup-icon') : null;
const editFieldPopup = document.getElementById('editFieldPopup');
const editFieldPopupClose = document.getElementById('editFieldPopupClose');
const editFieldPopupCancel = document.getElementById('editFieldPopupCancel');
const editFieldPopupSave = document.getElementById('editFieldPopupSave');
const editFieldPopupInput = document.getElementById('editFieldPopupInput');
const editFieldPopupIcon = document.getElementById('editFieldPopupIcon');
const deleteFieldPopup = document.getElementById('deleteFieldPopup');
const deleteFieldPopupClose = document.getElementById('deleteFieldPopupClose');
const deleteFieldPopupNo = document.getElementById('deleteFieldPopupNo');
const deleteFieldPopupYes = document.getElementById('deleteFieldPopupYes');
const deleteFieldPopupMessage = document.getElementById('deleteFieldPopupMessage');
const deleteFieldPopupIcon = document.getElementById('deleteFieldPopupIcon');
const inlineEditFieldForm = document.getElementById('inlineEditFieldForm');
const inlineEditFieldKey = document.getElementById('inlineEditFieldKey');
const inlineEditFieldLabel = document.getElementById('inlineEditFieldLabel');
const inlineDeleteFieldForm = document.getElementById('inlineDeleteFieldForm');
const inlineDeleteFieldKey = document.getElementById('inlineDeleteFieldKey');
const initialDuplicatePopup = <?php echo json_encode($initialDuplicatePopup, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let activeFieldActionKey = '';
let activeFieldActionLabel = '';

function buildGradeRow() {
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" name="grade_key[]" placeholder="Grade 4"></td>
        <td><input type="text" name="grade_label[]" placeholder="Grade 4"></td>
        <td><button type="button" class="settings-row-remove" data-remove-grade-row>Remove</button></td>
    `;
    return row;
}

function buildCustomFieldRow() {
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>
            <input type="hidden" name="custom_field_key[]" value="">
            <input type="text" name="custom_field_label[]" placeholder="Enter field name">
        </td>
        <td>
            <select name="custom_field_section[]" class="settings-table-select-section">
                <?php foreach ($customFieldSectionChoices as $sectionName): ?>
                    <option value="<?php echo htmlspecialchars($sectionName); ?>"><?php echo htmlspecialchars($sectionName); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select name="custom_field_input_type[]" class="settings-table-select-type" data-custom-field-type>
                <?php foreach (smartenroll_custom_field_allowed_types() as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars(ucfirst($type)); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="text" name="custom_field_options[]" placeholder="Option 1, Option 2" data-custom-field-options disabled>
        </td>
        <td><button type="button" class="settings-row-remove" data-remove-custom-row>Remove</button></td>
    `;
    return row;
}

function normalizeDuplicateValue(value) {
    return value.trim().toLowerCase().replace(/\s+/g, ' ');
}

function findDuplicateValue(values) {
    const seen = new Set();
    for (const value of values) {
        const normalized = normalizeDuplicateValue(value);
        if (!normalized) {
            continue;
        }

        if (seen.has(normalized)) {
            return value.trim();
        }

        seen.add(normalized);
    }

    return '';
}

function showDuplicatePopup(title, message) {
    if (!duplicatePopup || !duplicatePopupTitle || !duplicatePopupMessage) {
        window.alert(message);
        return;
    }

    animatePopupIcon(duplicatePopupIcon);

    duplicatePopupTitle.textContent = title;
    duplicatePopupMessage.textContent = message;
    duplicatePopup.classList.add('active');
    duplicatePopup.setAttribute('aria-hidden', 'false');
}

function hideDuplicatePopup() {
    if (!duplicatePopup) {
        return;
    }

    duplicatePopup.classList.remove('active');
    duplicatePopup.setAttribute('aria-hidden', 'true');

    if (duplicatePopupIcon) {
        duplicatePopupIcon.classList.remove('is-animating');
    }
}

function animatePopupIcon(iconElement) {
    if (!iconElement) {
        return;
    }

    iconElement.classList.remove('is-animating');
    void iconElement.offsetWidth;
    iconElement.classList.add('is-animating');
}

function syncCustomFieldOptions(row) {
    if (!(row instanceof HTMLElement)) {
        return;
    }

    const typeSelect = row.querySelector('[data-custom-field-type]');
    const optionsInput = row.querySelector('[data-custom-field-options]');
    if (!(typeSelect instanceof HTMLSelectElement) || !(optionsInput instanceof HTMLInputElement)) {
        return;
    }

    const isSelect = typeSelect.value === 'select';
    optionsInput.disabled = !isSelect;
    if (!isSelect) {
        optionsInput.value = '';
    }
}

function showEditFieldPopup(fieldKey, fieldLabel) {
    if (!editFieldPopup || !editFieldPopupInput) {
        return;
    }

    activeFieldActionKey = fieldKey;
    activeFieldActionLabel = fieldLabel;
    editFieldPopupInput.value = fieldLabel;
    editFieldPopup.classList.add('active');
    editFieldPopup.setAttribute('aria-hidden', 'false');
    animatePopupIcon(editFieldPopupIcon);
    setTimeout(() => {
        editFieldPopupInput.focus();
        editFieldPopupInput.select();
    }, 80);
}

function hideEditFieldPopup() {
    if (!editFieldPopup) {
        return;
    }

    editFieldPopup.classList.remove('active');
    editFieldPopup.setAttribute('aria-hidden', 'true');
    if (editFieldPopupIcon) {
        editFieldPopupIcon.classList.remove('is-animating');
    }
}

function showDeleteFieldPopup(fieldKey, fieldLabel) {
    if (!deleteFieldPopup) {
        return;
    }

    activeFieldActionKey = fieldKey;
    activeFieldActionLabel = fieldLabel;
    if (deleteFieldPopupMessage) {
        deleteFieldPopupMessage.textContent = `Are you sure you want to delete "${fieldLabel}"?`;
    }
    deleteFieldPopup.classList.add('active');
    deleteFieldPopup.setAttribute('aria-hidden', 'false');
    animatePopupIcon(deleteFieldPopupIcon);
}

function hideDeleteFieldPopup() {
    if (!deleteFieldPopup) {
        return;
    }

    deleteFieldPopup.classList.remove('active');
    deleteFieldPopup.setAttribute('aria-hidden', 'true');
    if (deleteFieldPopupIcon) {
        deleteFieldPopupIcon.classList.remove('is-animating');
    }
}

if (duplicatePopupClose) {
    duplicatePopupClose.addEventListener('click', hideDuplicatePopup);
}

if (duplicatePopupOk) {
    duplicatePopupOk.addEventListener('click', hideDuplicatePopup);
}

if (duplicatePopup) {
    duplicatePopup.addEventListener('click', (event) => {
        if (event.target === duplicatePopup) {
            hideDuplicatePopup();
        }
    });
}

if (editFieldPopupClose) {
    editFieldPopupClose.addEventListener('click', hideEditFieldPopup);
}

if (editFieldPopupCancel) {
    editFieldPopupCancel.addEventListener('click', hideEditFieldPopup);
}

if (editFieldPopupSave) {
    editFieldPopupSave.addEventListener('click', () => {
        const nextLabel = editFieldPopupInput ? editFieldPopupInput.value.trim() : '';
        if (!activeFieldActionKey || !inlineEditFieldForm || !inlineEditFieldKey || !inlineEditFieldLabel) {
            return;
        }

        inlineEditFieldKey.value = activeFieldActionKey;
        inlineEditFieldLabel.value = nextLabel;
        inlineEditFieldForm.submit();
    });
}

if (editFieldPopup) {
    editFieldPopup.addEventListener('click', (event) => {
        if (event.target === editFieldPopup) {
            hideEditFieldPopup();
        }
    });
}

if (deleteFieldPopupClose) {
    deleteFieldPopupClose.addEventListener('click', hideDeleteFieldPopup);
}

if (deleteFieldPopupNo) {
    deleteFieldPopupNo.addEventListener('click', hideDeleteFieldPopup);
}

if (deleteFieldPopupYes) {
    deleteFieldPopupYes.addEventListener('click', () => {
        if (!activeFieldActionKey || !inlineDeleteFieldForm || !inlineDeleteFieldKey) {
            return;
        }

        inlineDeleteFieldKey.value = activeFieldActionKey;
        inlineDeleteFieldForm.submit();
    });
}

if (deleteFieldPopup) {
    deleteFieldPopup.addEventListener('click', (event) => {
        if (event.target === deleteFieldPopup) {
            hideDeleteFieldPopup();
        }
    });
}

document.querySelectorAll('[data-edit-field-label]').forEach((button) => {
    button.addEventListener('click', () => {
        if (!(button instanceof HTMLElement)) {
            return;
        }

        showEditFieldPopup(button.dataset.fieldKey || '', button.dataset.fieldLabel || '');
    });
});

document.querySelectorAll('[data-delete-field-label]').forEach((button) => {
    button.addEventListener('click', () => {
        if (!(button instanceof HTMLElement)) {
            return;
        }

        showDeleteFieldPopup(button.dataset.fieldKey || '', button.dataset.fieldLabel || '');
    });
});

if (initialDuplicatePopup && initialDuplicatePopup.title && initialDuplicatePopup.message) {
    showDuplicatePopup(initialDuplicatePopup.title, initialDuplicatePopup.message);
}

if (addGradeRowBtn && gradeSettingsBody) {
    addGradeRowBtn.addEventListener('click', () => {
        gradeSettingsBody.appendChild(buildGradeRow());
    });

    gradeSettingsBody.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || !target.hasAttribute('data-remove-grade-row')) {
            return;
        }

        const row = target.closest('tr');
        if (row && gradeSettingsBody.children.length > 1) {
            row.remove();
        }
    });
}

if (addCustomFieldRowBtn && customFieldsBody) {
    addCustomFieldRowBtn.addEventListener('click', () => {
        const row = buildCustomFieldRow();
        customFieldsBody.appendChild(row);
        syncCustomFieldOptions(row);
    });

    customFieldsBody.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || !target.hasAttribute('data-remove-custom-row')) {
            return;
        }

        const row = target.closest('tr');
        if (row) {
            row.remove();
        }
    });

    customFieldsBody.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || !target.matches('[data-custom-field-type]')) {
            return;
        }

        const row = target.closest('tr');
        if (row instanceof HTMLElement) {
            syncCustomFieldOptions(row);
        }
    });

    customFieldsBody.querySelectorAll('tr').forEach((row) => {
        if (row instanceof HTMLElement) {
            syncCustomFieldOptions(row);
        }
    });
}

if (gradeSettingsForm) {
    gradeSettingsForm.addEventListener('submit', (event) => {
        const gradeKeyInputs = Array.from(gradeSettingsForm.querySelectorAll('input[name="grade_key[]"]'));
        const gradeLabelInputs = Array.from(gradeSettingsForm.querySelectorAll('input[name="grade_label[]"]'));
        const duplicateGradeKey = findDuplicateValue(gradeKeyInputs.map((input) => input.value));
        const duplicateGradeLabel = findDuplicateValue(gradeLabelInputs.map((input) => input.value));

        if (duplicateGradeKey !== '' || duplicateGradeLabel !== '') {
            event.preventDefault();
            showDuplicatePopup('Duplicate Grade Level', 'This grade level already exists');
        }
    });
}

if (customFieldsForm) {
    customFieldsForm.addEventListener('submit', (event) => {
        const labelInputs = Array.from(customFieldsForm.querySelectorAll('input[name="custom_field_label[]"]'));
        const duplicateLabel = findDuplicateValue(labelInputs.map((input) => input.value));

        if (duplicateLabel !== '') {
            event.preventDefault();
            showDuplicatePopup('Duplicate Custom Field', 'This custom field already exists');
        }
    });
}

const dobInput = document.querySelector('input[name="dob"]');
const ageInput = document.querySelector('input[name="age"]');
const completionDateInput = document.querySelector('input[name="completion_date"]');
const schoolYearInput = document.querySelector('input[name="school_year"]');

function calculateAge(dobValue) {
    if (!dobValue) return '';
    const dob = new Date(dobValue);
    if (Number.isNaN(dob.getTime())) return '';

    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const monthDiff = today.getMonth() - dob.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age--;
    }

    return age >= 0 ? age : '';
}

function calculateSchoolYear(dateValue) {
    if (!dateValue) return '';
    const completionDate = new Date(dateValue);
    if (Number.isNaN(completionDate.getTime())) return '';

    const month = completionDate.getMonth() + 1;
    const year = completionDate.getFullYear();
    const startYear = month >= 6 ? year : year - 1;
    return `${startYear}-${startYear + 1}`;
}

if (dobInput && ageInput) {
    dobInput.addEventListener('change', () => {
        const age = calculateAge(dobInput.value);
        ageInput.value = age !== '' ? age : '';
    });
}

if (completionDateInput && schoolYearInput) {
    completionDateInput.addEventListener('change', () => {
        const schoolYear = calculateSchoolYear(completionDateInput.value);
        schoolYearInput.value = schoolYear || '';
    });
}

const guardianTypeInputs = Array.from(document.querySelectorAll('input[name="guardian_type"]'));
const medicationInputs = Array.from(document.querySelectorAll('input[name="medication"]'));
const medicationDetailsInput = document.querySelector('[name="medication_details"]');
const guardianMap = {
    mother: {
        guardian_lname: document.querySelector('input[name="mother_lname"]'),
        guardian_fname: document.querySelector('input[name="mother_fname"]'),
        guardian_mname: document.querySelector('input[name="mother_mname"]'),
        guardian_occ: document.querySelector('input[name="mother_occ"]'),
        guardian_contact: document.querySelector('input[name="mother_contact"]')
    },
    father: {
        guardian_lname: document.querySelector('input[name="father_lname"]'),
        guardian_fname: document.querySelector('input[name="father_fname"]'),
        guardian_mname: document.querySelector('input[name="father_mname"]'),
        guardian_occ: document.querySelector('input[name="father_occ"]'),
        guardian_contact: document.querySelector('input[name="father_contact"]')
    }
};

function setGuardianFrom(sourceKey) {
    const source = guardianMap[sourceKey];
    if (!source) return;

    Object.keys(source).forEach((targetKey) => {
        const target = document.querySelector(`input[name="${targetKey}"]`);
        const sourceInput = source[targetKey];
        if (target && sourceInput) {
            target.value = sourceInput.value || '';
        }
    });
}

function setGuardianReadOnly(readOnly) {
    document.querySelectorAll('[data-guardian-field]').forEach((field) => {
        field.readOnly = readOnly;
    });
}

function getSelectedRadioValue(inputs) {
    const selected = inputs.find((input) => input.checked);
    return selected ? selected.value : '';
}

if (guardianTypeInputs.length > 0) {
    const syncGuardianType = () => {
        const selectedGuardianType = getSelectedRadioValue(guardianTypeInputs);

        if (selectedGuardianType === 'mother' || selectedGuardianType === 'father') {
            setGuardianFrom(selectedGuardianType);
            setGuardianReadOnly(true);
        } else {
            setGuardianReadOnly(false);
        }
    };

    syncGuardianType();

    guardianTypeInputs.forEach((input) => {
        input.addEventListener('change', syncGuardianType);
    });
}

function syncMedicationField() {
    if (medicationInputs.length === 0 || !medicationDetailsInput) return;

    const enabled = getSelectedRadioValue(medicationInputs) === 'yes';
    medicationDetailsInput.disabled = !enabled;
    if (!enabled) {
        medicationDetailsInput.value = '';
    }
}

if (medicationInputs.length > 0 && medicationDetailsInput) {
    syncMedicationField();
    medicationInputs.forEach((input) => {
        input.addEventListener('change', syncMedicationField);
    });
}
</script>
</body>
</html>
