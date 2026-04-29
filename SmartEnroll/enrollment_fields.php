<?php
declare(strict_types=1);

function smartenroll_enrollment_base_sections(): array
{
    return [
        'Enrollment Info' => [
            'student_id', 'grade_level', 'school_year', 'student_status', 'completion_date', 'created_at',
        ],
        'Learner Information' => [
            'learner_lname', 'learner_fname', 'learner_mname', 'learner_ext', 'nickname', 'sex', 'dob', 'age',
            'mother_tongue', 'religion', 'email',
        ],
        'Address Information' => [
            'province', 'municipality', 'barangay', 'street',
        ],
        'Father Information' => [
            'father_lname', 'father_fname', 'father_mname', 'father_occ', 'father_contact',
        ],
        'Mother Information' => [
            'mother_lname', 'mother_fname', 'mother_mname', 'mother_occ', 'mother_contact', 'mother_maiden',
        ],
        'Guardian Information' => [
            'guardian_type', 'guardian_lname', 'guardian_fname', 'guardian_mname', 'guardian_occ', 'guardian_contact',
        ],
        'Special Education Needs' => [
            'special_needs', 'medication', 'medication_details',
        ],
        'Emergency Contacts' => [
            'emergency1_name', 'emergency1_contact', 'emergency1_relationship',
            'emergency2_name', 'emergency2_contact', 'emergency2_relationship',
            'emergency3_name', 'emergency3_contact', 'emergency3_relationship',
        ],
    ];
}

function smartenroll_supported_enrollment_sections(): array
{
    $sections = array_keys(smartenroll_enrollment_base_sections());
    $sections[] = 'Grade Level';
    return $sections;
}

function smartenroll_field_labelize(string $key, array $customFieldMap = []): string
{
    if (isset($customFieldMap[$key]['field_label'])) {
        return (string)$customFieldMap[$key]['field_label'];
    }

    $map = [
        'student_id' => 'Student ID',
        'school_year' => 'School Year',
        'student_status' => 'Student Status',
        'completion_date' => 'Completion Date',
        'created_at' => 'Created At',
        'learner_lname' => 'Learner Last Name',
        'learner_fname' => 'Learner First Name',
        'learner_mname' => 'Learner Middle Name',
        'learner_ext' => 'Learner Extension Name',
        'father_lname' => 'Father Last Name',
        'father_fname' => 'Father First Name',
        'father_mname' => 'Father Middle Name',
        'mother_lname' => 'Mother Last Name',
        'mother_fname' => 'Mother First Name',
        'mother_mname' => 'Mother Middle Name',
        'guardian_lname' => 'Guardian Last Name',
        'guardian_fname' => 'Guardian First Name',
        'guardian_mname' => 'Guardian Middle Name',
        'mother_maiden' => 'Mother Maiden Full Name',
        'father_occ' => 'Father Occupation',
        'mother_occ' => 'Mother Occupation',
        'guardian_occ' => 'Guardian Occupation',
        'guardian_contact' => 'Guardian Contact Number',
        'father_contact' => 'Father Contact Number',
        'mother_contact' => 'Mother Contact Number',
        'emergency1_name' => 'Emergency 1 Name',
        'emergency1_contact' => 'Emergency 1 Contact',
        'emergency1_relationship' => 'Emergency 1 Relationship',
        'emergency2_name' => 'Emergency 2 Name',
        'emergency2_contact' => 'Emergency 2 Contact',
        'emergency2_relationship' => 'Emergency 2 Relationship',
        'emergency3_name' => 'Emergency 3 Name',
        'emergency3_contact' => 'Emergency 3 Contact',
        'emergency3_relationship' => 'Emergency 3 Relationship',
        'dob' => 'Date of Birth',
    ];

    if (isset($map[$key])) {
        return $map[$key];
    }

    $key = str_replace('_', ' ', $key);
    $key = preg_replace('/\s+/', ' ', $key);
    return ucwords(trim((string)$key));
}

function smartenroll_normalize_date_value(string $value): string
{
    if ($value === '') {
        return '';
    }

    $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp !== false ? date('Y-m-d', $timestamp) : '';
}

function smartenroll_input_type_for(string $column, array $customFieldMap = []): string
{
    $customType = strtolower(trim((string)($customFieldMap[$column]['input_type'] ?? '')));
    if ($customType !== '') {
        return $customType;
    }

    if ($column === 'student_status') {
        return 'select';
    }

    if (in_array($column, ['completion_date', 'dob'], true)) {
        return 'date';
    }

    if ($column === 'email') {
        return 'email';
    }

    if ($column === 'age') {
        return 'number';
    }

    if (str_contains($column, 'contact')) {
        return 'tel';
    }

    return 'text';
}

function smartenroll_age_from_dob(string $value): string
{
    $normalized = smartenroll_normalize_date_value($value);
    if ($normalized === '') {
        return '';
    }

    $dob = DateTime::createFromFormat('Y-m-d', $normalized);
    if (!($dob instanceof DateTime)) {
        return '';
    }

    $today = new DateTime('today');
    return (string)$today->diff($dob)->y;
}

function smartenroll_custom_field_connection(?mysqli $conn, bool &$ownsConnection): mysqli
{
    if ($conn instanceof mysqli) {
        $ownsConnection = false;
        return $conn;
    }

    $ownsConnection = true;
    $db = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $db->set_charset('utf8mb4');
    return $db;
}

function smartenroll_student_status_options(): array
{
    return ['New', 'Continuing', 'Drop'];
}

function smartenroll_ensure_student_status_column(mysqli $conn): void
{
    $columnCheck = $conn->query("SHOW COLUMNS FROM `enrollments` LIKE 'student_status'");
    if ($columnCheck && $columnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `student_status` VARCHAR(50) DEFAULT NULL AFTER `school_year`");
    }
    if ($columnCheck) {
        $columnCheck->close();
    }
}

function smartenroll_ensure_custom_fields_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS enrollment_custom_fields (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            field_key VARCHAR(150) NOT NULL,
            field_label VARCHAR(150) NOT NULL,
            section_name VARCHAR(150) NOT NULL,
            input_type VARCHAR(50) NOT NULL DEFAULT 'text',
            field_options TEXT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_field_key (field_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function smartenroll_custom_field_key_from_label(string $label): string
{
    $key = strtolower(trim($label));
    $key = preg_replace('/[^a-z0-9]+/', '_', $key);
    $key = trim((string)$key, '_');

    if ($key === '') {
        $key = 'custom_field';
    }

    if (!str_starts_with($key, 'custom_')) {
        $key = 'custom_' . $key;
    }

    return $key;
}

function smartenroll_custom_field_allowed_types(): array
{
    return ['text', 'textarea', 'date', 'number', 'email', 'tel', 'select'];
}

function smartenroll_normalize_duplicate_label(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);
    return strtolower((string)$value);
}

function smartenroll_builtin_field_rows(?mysqli $conn = null, bool $includeInactive = true): array
{
    $overrides = smartenroll_get_field_label_overrides($conn);
    $rows = [];
    foreach (smartenroll_enrollment_base_sections() as $sectionName => $fields) {
        foreach ($fields as $fieldKey) {
            $fieldKey = (string)$fieldKey;
            $override = $overrides[$fieldKey] ?? null;
            $isActive = (int)($override['is_active'] ?? 1) === 1;
            if (!$includeInactive && !$isActive) {
                continue;
            }

            $rows[] = [
                'field_key' => $fieldKey,
                'field_label' => (string)($override['field_label'] ?? smartenroll_field_labelize($fieldKey)),
                'section_name' => (string)$sectionName,
                'is_active' => $isActive ? 1 : 0,
            ];
        }
    }

    return $rows;
}

function smartenroll_builtin_field_row_map(?mysqli $conn = null, bool $includeInactive = true): array
{
    $map = [];

    foreach (smartenroll_builtin_field_rows($conn, $includeInactive) as $row) {
        $map[(string)$row['field_key']] = $row;
    }

    return $map;
}

function smartenroll_reserved_field_labels(?mysqli $conn = null): array
{
    $labels = [];
    $seen = [];

    foreach (smartenroll_builtin_field_rows($conn, false) as $fieldRow) {
        $label = (string)$fieldRow['field_label'];
        $normalized = smartenroll_normalize_duplicate_label($label);
        if ($normalized === '' || isset($seen[$normalized])) {
            continue;
        }

        $seen[$normalized] = true;
        $labels[] = $label;
    }

    return $labels;
}

function smartenroll_ensure_field_label_overrides_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS enrollment_field_label_overrides (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            field_key VARCHAR(150) NOT NULL,
            field_label VARCHAR(150) NOT NULL,
            section_name VARCHAR(150) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_field_key (field_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $colCheck = $conn->query("SHOW COLUMNS FROM enrollment_field_label_overrides LIKE 'is_active'");
    if ($colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE enrollment_field_label_overrides ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER section_name");
    }
}

function smartenroll_get_field_label_overrides(?mysqli $conn = null): array
{
    $ownsConnection = false;
    $db = smartenroll_custom_field_connection($conn, $ownsConnection);

    try {
        smartenroll_ensure_field_label_overrides_table($db);

        $rows = [];
        $result = $db->query(
            "SELECT field_key, field_label, section_name, is_active
             FROM enrollment_field_label_overrides
             ORDER BY section_name ASC, field_key ASC"
        );

        while ($row = $result->fetch_assoc()) {
            $rows[(string)$row['field_key']] = [
                'field_key' => (string)$row['field_key'],
                'field_label' => (string)$row['field_label'],
                'section_name' => (string)$row['section_name'],
                'is_active' => (int)($row['is_active'] ?? 1),
            ];
        }

        return $rows;
    } finally {
        if ($ownsConnection) {
            $db->close();
        }
    }
}

function smartenroll_get_field_label_map(?mysqli $conn = null, bool $activeOnly = true): array
{
    $map = smartenroll_get_field_label_overrides($conn);

    foreach (smartenroll_get_custom_field_map($conn, $activeOnly) as $fieldKey => $field) {
        $map[(string)$fieldKey] = $field;
    }

    return $map;
}

function smartenroll_save_field_label_overrides(array $rows, ?mysqli $conn = null): void
{
    $ownsConnection = false;
    $db = smartenroll_custom_field_connection($conn, $ownsConnection);

    try {
        smartenroll_ensure_field_label_overrides_table($db);

        $builtinRows = smartenroll_builtin_field_rows($db, true);
        $builtinRowMap = [];
        $customFieldLabels = [];
        foreach (smartenroll_get_custom_fields($db, true) as $customField) {
            $customFieldLabels[smartenroll_normalize_duplicate_label((string)$customField['field_label'])] = true;
        }

        foreach ($builtinRows as $builtinRow) {
            $builtinRowMap[(string)$builtinRow['field_key']] = $builtinRow;
        }

        $cleanRows = [];
        $seenLabels = [];

        foreach ($rows as $row) {
            $fieldKey = trim((string)($row['field_key'] ?? ''));
            $fieldLabel = trim((string)($row['field_label'] ?? ''));
            $isDeleted = (int)($row['is_deleted'] ?? 0) === 1;

            if ($fieldKey === '' || !isset($builtinRowMap[$fieldKey])) {
                continue;
            }

            if (!$isDeleted && $fieldLabel === '') {
                throw new RuntimeException('Each existing field needs a label.');
            }

            if (!$isDeleted) {
                $normalizedLabel = smartenroll_normalize_duplicate_label($fieldLabel);
                if (isset($seenLabels[$normalizedLabel])) {
                    throw new RuntimeException('This field label already exists');
                }

                if (isset($customFieldLabels[$normalizedLabel])) {
                    throw new RuntimeException('This field label already exists');
                }

                $seenLabels[$normalizedLabel] = true;
            }

            $cleanRows[$fieldKey] = [
                'field_key' => $fieldKey,
                'field_label' => $isDeleted ? (string)$builtinRowMap[$fieldKey]['field_label'] : $fieldLabel,
                'section_name' => (string)$builtinRowMap[$fieldKey]['section_name'],
                'is_active' => $isDeleted ? 0 : 1,
            ];
        }

        if (count($cleanRows) !== count($builtinRowMap)) {
            throw new RuntimeException('Unable to save all existing field labels.');
        }

        $db->begin_transaction();
        $db->query("DELETE FROM enrollment_field_label_overrides");

        $stmt = $db->prepare(
            "INSERT INTO enrollment_field_label_overrides (field_key, field_label, section_name, is_active)
             VALUES (?, ?, ?, ?)"
        );

        foreach ($builtinRows as $builtinRow) {
            $fieldKey = (string)$builtinRow['field_key'];
            $defaultLabel = smartenroll_field_labelize($fieldKey);
            $sectionName = (string)$builtinRow['section_name'];
            $fieldLabel = (string)($cleanRows[$fieldKey]['field_label'] ?? $defaultLabel);
            $isActive = (int)($cleanRows[$fieldKey]['is_active'] ?? 1);

            if (
                $isActive === 1 &&
                smartenroll_normalize_duplicate_label($fieldLabel) === smartenroll_normalize_duplicate_label($defaultLabel)
            ) {
                continue;
            }

            $stmt->bind_param('sssi', $fieldKey, $fieldLabel, $sectionName, $isActive);
            $stmt->execute();
        }

        $stmt->close();
        $db->commit();
    } catch (Throwable $e) {
        try {
            $db->rollback();
        } catch (Throwable $ignore) {
        }
        throw $e;
    } finally {
        if ($ownsConnection) {
            $db->close();
        }
    }
}

function smartenroll_get_custom_fields(?mysqli $conn = null, bool $activeOnly = true): array
{
    $ownsConnection = false;
    $db = smartenroll_custom_field_connection($conn, $ownsConnection);

    try {
        smartenroll_ensure_custom_fields_table($db);

        $rows = [];
        $sql = "SELECT id, field_key, field_label, section_name, input_type, field_options, sort_order, is_active
                FROM enrollment_custom_fields";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY section_name ASC, sort_order ASC, id ASC";

        $result = $db->query($sql);
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)($row['id'] ?? 0);
            $row['sort_order'] = (int)($row['sort_order'] ?? 0);
            $row['is_active'] = (int)($row['is_active'] ?? 0);
            $rows[] = $row;
        }

        return $rows;
    } finally {
        if ($ownsConnection) {
            $db->close();
        }
    }
}

function smartenroll_get_custom_field_map(?mysqli $conn = null, bool $activeOnly = true): array
{
    $map = [];
    foreach (smartenroll_get_custom_fields($conn, $activeOnly) as $field) {
        $map[(string)$field['field_key']] = $field;
    }
    return $map;
}

function smartenroll_get_custom_field_keys(?mysqli $conn = null, bool $activeOnly = false): array
{
    $keys = [];
    foreach (smartenroll_get_custom_fields($conn, $activeOnly) as $field) {
        $keys[] = (string)$field['field_key'];
    }
    return $keys;
}

function smartenroll_custom_fields_by_section(?mysqli $conn = null): array
{
    $grouped = [];
    foreach (smartenroll_get_custom_fields($conn, true) as $field) {
        $section = (string)$field['section_name'];
        if (!isset($grouped[$section])) {
            $grouped[$section] = [];
        }
        $grouped[$section][] = $field;
    }
    return $grouped;
}

function smartenroll_ensure_custom_field_columns(mysqli $conn, array $rows): void
{
    $columns = [];
    $colRes = $conn->query("SHOW COLUMNS FROM `enrollments`");
    while ($row = $colRes->fetch_assoc()) {
        $columns[] = (string)$row['Field'];
    }

    foreach ($rows as $row) {
        $fieldKey = (string)$row['field_key'];
        if ($fieldKey === '' || in_array($fieldKey, $columns, true)) {
            continue;
        }

        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `$fieldKey` TEXT NULL");
        $columns[] = $fieldKey;
    }
}

function smartenroll_save_custom_fields(array $rows, ?mysqli $conn = null): void
{
    $ownsConnection = false;
    $db = smartenroll_custom_field_connection($conn, $ownsConnection);

    try {
        smartenroll_ensure_custom_fields_table($db);

        $supportedSections = smartenroll_supported_enrollment_sections();
        $allowedTypes = smartenroll_custom_field_allowed_types();
        $reservedLabels = [];
        foreach (smartenroll_reserved_field_labels() as $reservedLabel) {
            $reservedLabels[smartenroll_normalize_duplicate_label($reservedLabel)] = true;
        }
        $cleanRows = [];
        $seenKeys = [];
        $seenLabels = [];
        $sortOrder = 10;

        foreach ($rows as $row) {
            $existingKey = trim((string)($row['field_key'] ?? ''));
            $label = trim((string)($row['field_label'] ?? ''));
            $section = trim((string)($row['section_name'] ?? ''));
            $inputType = strtolower(trim((string)($row['input_type'] ?? 'text')));
            $fieldOptions = trim((string)($row['field_options'] ?? ''));

            if ($existingKey === '' && $label === '' && $section === '') {
                continue;
            }

            if ($label === '') {
                throw new RuntimeException('Each custom field needs a label.');
            }

            if (!in_array($section, $supportedSections, true)) {
                throw new RuntimeException('Choose a valid section for each custom field.');
            }

            if (!in_array($inputType, $allowedTypes, true)) {
                $inputType = 'text';
            }

            if ($inputType === 'select' && $fieldOptions === '') {
                throw new RuntimeException('Select fields need options separated by commas.');
            }

            $fieldKey = $existingKey !== '' ? smartenroll_custom_field_key_from_label($existingKey) : smartenroll_custom_field_key_from_label($label);

            if (isset($seenKeys[$fieldKey])) {
                throw new RuntimeException('Duplicate custom field keys are not allowed: ' . $fieldKey);
            }
            $seenKeys[$fieldKey] = true;

            $labelKey = smartenroll_normalize_duplicate_label($label);
            if (isset($reservedLabels[$labelKey])) {
                throw new RuntimeException('This custom field already exists');
            }

            if (isset($seenLabels[$labelKey])) {
                throw new RuntimeException('This custom field already exists');
            }
            $seenLabels[$labelKey] = true;

            $cleanRows[] = [
                'field_key' => $fieldKey,
                'field_label' => $label,
                'section_name' => $section,
                'input_type' => $inputType,
                'field_options' => $fieldOptions,
                'sort_order' => $sortOrder,
            ];
            $sortOrder += 10;
        }

        $db->begin_transaction();
        $db->query("UPDATE enrollment_custom_fields SET is_active = 0");

        smartenroll_ensure_custom_field_columns($db, $cleanRows);

        $stmt = $db->prepare(
            "INSERT INTO enrollment_custom_fields (field_key, field_label, section_name, input_type, field_options, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE
                field_label = VALUES(field_label),
                section_name = VALUES(section_name),
                input_type = VALUES(input_type),
                field_options = VALUES(field_options),
                sort_order = VALUES(sort_order),
                is_active = 1"
        );

        foreach ($cleanRows as $row) {
            $fieldKey = $row['field_key'];
            $fieldLabel = $row['field_label'];
            $sectionName = $row['section_name'];
            $inputType = $row['input_type'];
            $fieldOptions = $row['field_options'];
            $sortOrderValue = $row['sort_order'];
            $stmt->bind_param('sssssi', $fieldKey, $fieldLabel, $sectionName, $inputType, $fieldOptions, $sortOrderValue);
            $stmt->execute();
        }

        $stmt->close();
        $db->commit();
    } catch (Throwable $e) {
        try {
            $db->rollback();
        } catch (Throwable $ignore) {
        }
        throw $e;
    } finally {
        if ($ownsConnection) {
            $db->close();
        }
    }
}

function smartenroll_build_sections(array $columns, ?mysqli $conn = null, array $omitFields = []): array
{
    $sections = smartenroll_enrollment_base_sections();
    $customFieldsBySection = smartenroll_custom_fields_by_section($conn);
    $knownCustomKeys = smartenroll_get_custom_field_keys($conn, false);
    $builtinFieldMap = [];

    foreach (smartenroll_builtin_field_rows($conn, true) as $fieldRow) {
        $builtinFieldMap[(string)$fieldRow['field_key']] = $fieldRow;
    }

    foreach ($sections as $sectionName => $fields) {
        $sections[$sectionName] = array_values(array_filter(
            $fields,
            static function (string $field) use ($builtinFieldMap): bool {
                return (int)($builtinFieldMap[$field]['is_active'] ?? 1) === 1;
            }
        ));
    }

    foreach ($customFieldsBySection as $sectionName => $fields) {
        if (!isset($sections[$sectionName])) {
            $sections[$sectionName] = [];
        }

        foreach ($fields as $field) {
            $sections[$sectionName][] = (string)$field['field_key'];
        }
    }

    if ($omitFields !== []) {
        foreach ($sections as $sectionName => $fields) {
            $sections[$sectionName] = array_values(array_filter(
                $fields,
                static fn(string $field): bool => !in_array($field, $omitFields, true)
            ));
        }
    }

    $mapped = ['id'];
    foreach ($sections as $fields) {
        foreach ($fields as $field) {
            $mapped[] = $field;
        }
    }

    $extras = [];
    foreach ($columns as $column) {
        if (in_array($column, $mapped, true) || in_array($column, $knownCustomKeys, true)) {
            continue;
        }
        $extras[] = $column;
    }

    if ($extras !== []) {
        $sections['Other Saved Fields'] = $extras;
    }

    return $sections;
}

function smartenroll_custom_field_options(array $field): array
{
    $optionsRaw = trim((string)($field['field_options'] ?? ''));
    if ($optionsRaw === '') {
        return [];
    }

    $parts = array_map('trim', explode(',', $optionsRaw));
    return array_values(array_filter($parts, static fn(string $value): bool => $value !== ''));
}
