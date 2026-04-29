<?php
declare(strict_types=1);

require_once __DIR__ . '/enrollment_form_config.php';
require_once __DIR__ . '/enrollment_fields.php';
require_once __DIR__ . '/auth.php';

smartenroll_require_role('finance');

$gradeLevels = smartenroll_get_grade_levels();
$customFieldsBySection = smartenroll_custom_fields_by_section();
$customFieldMap = smartenroll_get_field_label_map();
$builtinFieldMap = smartenroll_builtin_field_row_map();
$isBuiltinActive = static fn(string $fieldKey): bool => (int)($builtinFieldMap[$fieldKey]['is_active'] ?? 1) === 1;
$builtinLabel = static fn(string $fieldKey): string => smartenroll_field_labelize($fieldKey, $customFieldMap);
$fieldGroupClass = static fn(string $fieldKey): string => $isBuiltinActive($fieldKey) ? '' : ' form-group-hidden';
$fieldDisabledAttr = static fn(string $fieldKey): string => $isBuiltinActive($fieldKey) ? '' : 'disabled';
$fieldReadonlyAttr = static fn(string $fieldKey, bool $readOnlyWhenActive = false): string => $isBuiltinActive($fieldKey)
    ? ($readOnlyWhenActive ? 'readonly' : '')
    : 'disabled';
$fieldLabelsForJs = [];

foreach ($builtinFieldMap as $fieldKey => $fieldRow) {
    $fieldLabelsForJs[$fieldKey] = (string)$builtinLabel((string)$fieldKey);
}

$showCompletionDateField = $isBuiltinActive('completion_date');
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="UTF-8">
        <title>SmartEnroll | Enrollment</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="Online enrollment form for Adreo Montessori Inc. Submit learner, parent, guardian, and emergency contact details through SMARTENROLL.">
        <meta name="keywords" content="Adreo Montessori enrollment form, SMARTENROLL form, school application, student registration">
        <meta name="robots" content="index, follow">
        <meta property="og:type" content="website">
        <meta property="og:title" content="SMARTENROLL | Enrollment Form">
        <meta property="og:description" content="Complete the Adreo Montessori Inc. enrollment form online through SMARTENROLL.">
        <meta property="og:image" content="assets/logo.png">

        <!-- FONT -->
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- ICONS -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">



        <link rel="stylesheet" href="css/enroll.css">

    </head>
    <body class="enroll-body">

    <!-- FORM -->
   <main class="enroll-form">
    <div class="enroll-page-header">
        <div class="enroll-header-left">
            <a href="dashboard.php" class="icon-back" title="Go Back" aria-label="Back to dashboard">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="enroll-header-title">
                <h1>Enrollment Form</h1>
                <p>Enter the learner, parent, guardian, and emergency contact details to complete a new enrollment.</p>
            </div>
        </div>
    </div>

<form id="enrollmentForm" action="save_enrollment.php" method="POST">

    <?php if ($showCompletionDateField): ?>
        <div class="form-top">
            <div class="completion-date">
                <label for="completionDate"><?php echo htmlspecialchars($builtinLabel('completion_date')); ?>:</label>
                <input type="date" id="completionDate" name="completion_date" <?php echo $fieldDisabledAttr('completion_date'); ?>>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($customFieldsBySection['Enrollment Info'])): ?>
        <section class="form-section">
            <h2>Enrollment Info</h2>
            <div class="form-grid two">
                <?php foreach ($customFieldsBySection['Enrollment Info'] as $field): ?>
                    <?php $fieldKey = (string)$field['field_key']; ?>
                    <div class="form-group">
                        <label><?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?></label>
                        <?php if (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'select'): ?>
                            <select name="<?php echo htmlspecialchars($fieldKey); ?>">
                                <option value="">Select</option>
                                <?php foreach (smartenroll_custom_field_options($field) as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'textarea'): ?>
                            <textarea name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>"></textarea>
                        <?php else: ?>
                            <input type="<?php echo htmlspecialchars(smartenroll_input_type_for($fieldKey, $customFieldMap)); ?>" name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>



        <!-- GRADE LEVEL -->
        <?php if ($isBuiltinActive('grade_level') || !empty($customFieldsBySection['Grade Level'])): ?>
        <section class="form-section">
        
            <h2>A. <?php echo htmlspecialchars($builtinLabel('grade_level')); ?></h2>

            <?php if ($isBuiltinActive('grade_level')): ?>
                <div class="ch-grid">
                    <?php foreach ($gradeLevels as $gradeLevel): ?>
                        <label class="grade-level-option">
                            <input type="radio" name="grade_level" value="<?php echo htmlspecialchars((string)$gradeLevel['grade_key']); ?>">
                            <span class="grade-level-button"><?php echo htmlspecialchars((string)$gradeLevel['grade_label']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($customFieldsBySection['Grade Level'])): ?>
                <?php $gradeLevelGridClass = count($customFieldsBySection['Grade Level']) === 1 ? 'one' : 'two'; ?>
                <div class="form-grid <?php echo $gradeLevelGridClass; ?>">
                    <?php foreach ($customFieldsBySection['Grade Level'] as $field): ?>
                        <?php $fieldKey = (string)$field['field_key']; ?>
                        <div class="form-group">
                            <label><?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?></label>
                            <?php if (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'select'): ?>
                                <select name="<?php echo htmlspecialchars($fieldKey); ?>">
                                    <option value="">Select</option>
                                    <?php foreach (smartenroll_custom_field_options($field) as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'textarea'): ?>
                                <textarea name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>"></textarea>
                            <?php else: ?>
                                <input type="<?php echo htmlspecialchars(smartenroll_input_type_for($fieldKey, $customFieldMap)); ?>" name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- LEARNER INFO -->
        <section class="form-section">
            <h2>B. Learner’s Information</h2>

            <!-- ROW 1 -->
            <div class="form-grid four">
                <div class="form-group<?php echo $fieldGroupClass('learner_lname'); ?>">
                    <label><?php echo htmlspecialchars($builtinLabel('learner_lname')); ?></label>
                    <input type="text" placeholder="<?php echo htmlspecialchars($builtinLabel('learner_lname')); ?>" name="learner_lname" <?php echo $fieldDisabledAttr('learner_lname'); ?> >

                </div>

                <div class="form-group<?php echo $fieldGroupClass('learner_fname'); ?>">
                    <label><?php echo htmlspecialchars($builtinLabel('learner_fname')); ?></label>
                    <input type="text" placeholder="<?php echo htmlspecialchars($builtinLabel('learner_fname')); ?>" name="learner_fname" <?php echo $fieldDisabledAttr('learner_fname'); ?>>

                </div>

                <div class="form-group<?php echo $fieldGroupClass('learner_mname'); ?>">
                    <label><?php echo htmlspecialchars($builtinLabel('learner_mname')); ?></label>
                    <input type="text" placeholder="<?php echo htmlspecialchars($builtinLabel('learner_mname')); ?>" name="learner_mname" <?php echo $fieldDisabledAttr('learner_mname'); ?>>


                </div>

                <div class="form-group<?php echo $fieldGroupClass('learner_ext'); ?>">
                    <label><?php echo htmlspecialchars($builtinLabel('learner_ext')); ?></label>
                <select name="learner_ext" <?php echo $fieldDisabledAttr('learner_ext'); ?>>
                        <option value="">None</option>
                        <option value="Jr">Jr.</option>
                        <option value="Sr">Sr.</option>
                        <option value="II">II</option>
                        <option value="III">III</option>
                    </select>
                </div>
            </div>

            <!-- ROW 2 -->
            <div class="form-grid one">
                <div class="form-group<?php echo $fieldGroupClass('nickname'); ?>">
                    <label><?php echo htmlspecialchars($builtinLabel('nickname')); ?></label>
                    <input type="text" placeholder="<?php echo htmlspecialchars($builtinLabel('nickname')); ?>" name="nickname" <?php echo $fieldDisabledAttr('nickname'); ?>>

                </div>
    <div class="form-group<?php echo $fieldGroupClass('sex'); ?>">
            <label><?php echo htmlspecialchars($builtinLabel('sex')); ?></label>
            <select name="sex" <?php echo $fieldDisabledAttr('sex'); ?>>
                <option value="">Select</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>
        </div>
            </div>

            <!-- ROW 3 -->
            <div class="form-grid two">
                <div class="form-group<?php echo $fieldGroupClass('dob'); ?>">
        <label><?php echo htmlspecialchars($builtinLabel('dob')); ?></label>

        <div class="date-wrapper">
        <input
            type="text"
            id="dob"
            name="dob"
            class="date-input"
            placeholder="MM / DD / YYYY"
            autocomplete="off"
            <?php echo $fieldDisabledAttr('dob'); ?>
        >
        <span class="calendar-icon" id="calendarBtn">
            <i class="fas fa-calendar-alt"></i>
        </span>
    <div class="custom-dob-picker" id="dobPicker">
        <div class="picker-header">
        <span id="monthLabel"></span>
        <span id="yearLabel"></span>
    </div>

        <div class="picker-columns">
            <div class="picker-column month-col"></div>
            <div class="picker-column year-col"></div>
        </div>

        <div class="day-grid"></div>
    </div>

    </div>

    </div>

                <div class="form-group<?php echo $fieldGroupClass('age'); ?>">
                    <label><?php echo htmlspecialchars($builtinLabel('age')); ?></label>
                    <input type="number" id="age" name="age" <?php echo $fieldReadonlyAttr('age', true); ?>>
                </div>
            </div>

            <!-- ROW 4 -->
            <div class="form-grid three">
                <div class="form-group<?php echo $fieldGroupClass('mother_tongue'); ?>">
                    <label><?php echo htmlspecialchars($builtinLabel('mother_tongue')); ?></label>
                    <input type="text" placeholder="<?php echo htmlspecialchars($builtinLabel('mother_tongue')); ?>" name="mother_tongue" <?php echo $fieldDisabledAttr('mother_tongue'); ?>>
                </div>

                <div class="form-group<?php echo $fieldGroupClass('religion'); ?>">
                    <label><?php echo htmlspecialchars($builtinLabel('religion')); ?></label>
                    <input type="text" placeholder="<?php echo htmlspecialchars($builtinLabel('religion')); ?>" name="religion" <?php echo $fieldDisabledAttr('religion'); ?>>
                </div>

                <div class="form-group<?php echo $fieldGroupClass('email'); ?>">
                    <label><?php echo htmlspecialchars($builtinLabel('email')); ?></label>
                    <input type="email" placeholder="<?php echo htmlspecialchars($builtinLabel('email')); ?>" name="email" <?php echo $fieldDisabledAttr('email'); ?>>
                </div>
            </div>

            <?php if (!empty($customFieldsBySection['Learner Information'])): ?>
                <div class="form-grid two">
                    <?php foreach ($customFieldsBySection['Learner Information'] as $field): ?>
                        <?php $fieldKey = (string)$field['field_key']; ?>
                        <div class="form-group">
                            <label><?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?></label>
                            <?php if (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'select'): ?>
                                <select name="<?php echo htmlspecialchars($fieldKey); ?>">
                                    <option value="">Select</option>
                                    <?php foreach (smartenroll_custom_field_options($field) as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'textarea'): ?>
                                <textarea name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>"></textarea>
                            <?php else: ?>
                                <input type="<?php echo htmlspecialchars(smartenroll_input_type_for($fieldKey, $customFieldMap)); ?>" name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <!-- ADDRESS INFO -->
    <section class="form-section">
        <h2>Address Information</h2>

        <!-- ROW 1 -->
        <div class="form-grid three">
            <div class="form-group<?php echo $fieldGroupClass('province'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('province')); ?></label>
               <select id="province" name="province_codes" <?php echo $fieldDisabledAttr('province'); ?>>
                    <option value="">Select Province</option>
                </select>
                <input type="hidden" name="province" id="province_name" <?php echo $fieldDisabledAttr('province'); ?>>
            </div>
        <div class="form-group<?php echo $fieldGroupClass('municipality'); ?>">
            <label><?php echo htmlspecialchars($builtinLabel('municipality')); ?></label>

                <select id="municipality" name="municipality_code" disabled>
                <option value="">Select Municipality</option>
            </select>

        <input type="hidden" name="municipality" id="municipality_name" <?php echo $fieldDisabledAttr('municipality'); ?>>
        </div>


            <div class="form-group<?php echo $fieldGroupClass('barangay'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('barangay')); ?></label>
                <select id="barangay" name="barangay" disabled>
                    <option value="">Select Barangay</option>
                </select>
            </div>
        </div>

        <!-- ROW 2 -->
        <div class="form-grid one">
            <div class="form-group<?php echo $fieldGroupClass('street'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('street')); ?></label>
                <input type="text" name="street" placeholder="<?php echo htmlspecialchars($builtinLabel('street')); ?>" <?php echo $fieldDisabledAttr('street'); ?>>
            </div>
        </div>

        <?php if (!empty($customFieldsBySection['Address Information'])): ?>
            <div class="form-grid two">
                <?php foreach ($customFieldsBySection['Address Information'] as $field): ?>
                    <?php $fieldKey = (string)$field['field_key']; ?>
                    <div class="form-group">
                        <label><?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?></label>
                        <?php if (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'select'): ?>
                            <select name="<?php echo htmlspecialchars($fieldKey); ?>">
                                <option value="">Select</option>
                                <?php foreach (smartenroll_custom_field_options($field) as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'textarea'): ?>
                            <textarea name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>"></textarea>
                        <?php else: ?>
                            <input type="<?php echo htmlspecialchars(smartenroll_input_type_for($fieldKey, $customFieldMap)); ?>" name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <!-- PARENT / GUARDIAN INFO -->
    <section class="form-section">
        <h2>C. PARENT / GUARDIAN INFORMATION</h2><br>

        <!-- FATHER -->
        <h2>Father's Information</h2>

        <div class="form-grid three">
            <div class="form-group<?php echo $fieldGroupClass('father_lname'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('father_lname')); ?></label>
                <input type="text" name="father_lname" placeholder="<?php echo htmlspecialchars($builtinLabel('father_lname')); ?>" <?php echo $fieldDisabledAttr('father_lname'); ?>>
            </div>
            <div class="form-group<?php echo $fieldGroupClass('father_fname'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('father_fname')); ?></label>
                <input type="text" name="father_fname" placeholder="<?php echo htmlspecialchars($builtinLabel('father_fname')); ?>" <?php echo $fieldDisabledAttr('father_fname'); ?>>
            </div>
            <div class="form-group<?php echo $fieldGroupClass('father_mname'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('father_mname')); ?></label>
                <input type="text" name="father_mname" placeholder="<?php echo htmlspecialchars($builtinLabel('father_mname')); ?>" <?php echo $fieldDisabledAttr('father_mname'); ?>>
            </div>
        </div>

        <div class="form-grid one">
            <div class="form-group<?php echo $fieldGroupClass('father_occ'); ?>">    
                <label><?php echo htmlspecialchars($builtinLabel('father_occ')); ?></label>
                <input type="text" name="father_occ" placeholder="<?php echo htmlspecialchars($builtinLabel('father_occ')); ?>" <?php echo $fieldDisabledAttr('father_occ'); ?>>
            </div>
        </div>

        <div class="form-grid one">
            <div class="form-group<?php echo $fieldGroupClass('father_contact'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('father_contact')); ?></label>
                <input type="text" name="father_contact" placeholder="<?php echo htmlspecialchars($builtinLabel('father_contact')); ?>" <?php echo $fieldDisabledAttr('father_contact'); ?>>
            </div>
        </div>

        <?php if (!empty($customFieldsBySection['Father Information'])): ?>
            <div class="form-grid two">
                <?php foreach ($customFieldsBySection['Father Information'] as $field): ?>
                    <?php $fieldKey = (string)$field['field_key']; ?>
                    <div class="form-group">
                        <label><?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?></label>
                        <?php if (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'select'): ?>
                            <select name="<?php echo htmlspecialchars($fieldKey); ?>">
                                <option value="">Select</option>
                                <?php foreach (smartenroll_custom_field_options($field) as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'textarea'): ?>
                            <textarea name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>"></textarea>
                        <?php else: ?>
                            <input type="<?php echo htmlspecialchars(smartenroll_input_type_for($fieldKey, $customFieldMap)); ?>" name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- MOTHER -->
        <h2>Mother's Information</h2>

        <div class="form-grid three">
            <div class="form-group<?php echo $fieldGroupClass('mother_lname'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('mother_lname')); ?></label>
                <input type="text" name="mother_lname" placeholder="<?php echo htmlspecialchars($builtinLabel('mother_lname')); ?>" <?php echo $fieldDisabledAttr('mother_lname'); ?>>
            </div>
            <div class="form-group<?php echo $fieldGroupClass('mother_fname'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('mother_fname')); ?></label>
                <input type="text" name="mother_fname" placeholder="<?php echo htmlspecialchars($builtinLabel('mother_fname')); ?>" <?php echo $fieldDisabledAttr('mother_fname'); ?>>
            </div>
            <div class="form-group<?php echo $fieldGroupClass('mother_mname'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('mother_mname')); ?></label>
                <input type="text" name="mother_mname" placeholder="<?php echo htmlspecialchars($builtinLabel('mother_mname')); ?>" <?php echo $fieldDisabledAttr('mother_mname'); ?>>
            </div>
        </div>

        <div class="form-grid one">
            <div class="form-group<?php echo $fieldGroupClass('mother_occ'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('mother_occ')); ?></label>
                <input type="text" name="mother_occ" placeholder="<?php echo htmlspecialchars($builtinLabel('mother_occ')); ?>" <?php echo $fieldDisabledAttr('mother_occ'); ?>>
            </div>
        </div>

        <div class="form-grid one">
            <div class="form-group<?php echo $fieldGroupClass('mother_contact'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('mother_contact')); ?></label>
                <input type="text" name="mother_contact" placeholder="<?php echo htmlspecialchars($builtinLabel('mother_contact')); ?>" <?php echo $fieldDisabledAttr('mother_contact'); ?>>
            </div>
        </div>

        <div class="form-grid one">
            <div class="form-group<?php echo $fieldGroupClass('mother_maiden'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('mother_maiden')); ?></label>
                <input type="text" name="mother_maiden" placeholder="<?php echo htmlspecialchars($builtinLabel('mother_maiden')); ?>" <?php echo $fieldDisabledAttr('mother_maiden'); ?>>
            </div>
        </div>

        <?php if (!empty($customFieldsBySection['Mother Information'])): ?>
            <div class="form-grid two">
                <?php foreach ($customFieldsBySection['Mother Information'] as $field): ?>
                    <?php $fieldKey = (string)$field['field_key']; ?>
                    <div class="form-group">
                        <label><?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?></label>
                        <?php if (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'select'): ?>
                            <select name="<?php echo htmlspecialchars($fieldKey); ?>">
                                <option value="">Select</option>
                                <?php foreach (smartenroll_custom_field_options($field) as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'textarea'): ?>
                            <textarea name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>"></textarea>
                        <?php else: ?>
                            <input type="<?php echo htmlspecialchars(smartenroll_input_type_for($fieldKey, $customFieldMap)); ?>" name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- GUARDIAN -->
        <h2>Guardian's Information</h2>

        <div class="form-grid one<?php echo $isBuiltinActive('guardian_type') ? '' : ' form-group-hidden'; ?>">
            <div class="form-group">
                <label><?php echo htmlspecialchars($builtinLabel('guardian_type')); ?></label>
                <div style="display:flex; gap:30px;">
                    <label><input type="radio" name="guardian_type" value="other" <?php echo $fieldDisabledAttr('guardian_type'); ?>> Other</label>

                    <label><input type="radio" name="guardian_type" value="mother" <?php echo $fieldDisabledAttr('guardian_type'); ?>> Mother</label>
                    <label><input type="radio" name="guardian_type" value="father" <?php echo $fieldDisabledAttr('guardian_type'); ?>> Father</label>
                    
                </div>
            </div>
        </div>

        <div class="form-grid three">
            <div class="form-group<?php echo $fieldGroupClass('guardian_lname'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('guardian_lname')); ?></label>
                <input type="text" name="guardian_lname" <?php echo $fieldReadonlyAttr('guardian_lname', $isBuiltinActive('guardian_type')); ?>>
            </div>
            <div class="form-group<?php echo $fieldGroupClass('guardian_fname'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('guardian_fname')); ?></label>
                <input type="text" name="guardian_fname" <?php echo $fieldReadonlyAttr('guardian_fname', $isBuiltinActive('guardian_type')); ?>>
            </div>
            <div class="form-group<?php echo $fieldGroupClass('guardian_mname'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('guardian_mname')); ?></label>
                <input type="text" name="guardian_mname" <?php echo $fieldReadonlyAttr('guardian_mname', $isBuiltinActive('guardian_type')); ?>>
            </div>
        </div>

        <div class="form-grid one">
            <div class="form-group<?php echo $fieldGroupClass('guardian_occ'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('guardian_occ')); ?></label>
                <input type="text" name="guardian_occ" <?php echo $fieldReadonlyAttr('guardian_occ', $isBuiltinActive('guardian_type')); ?>>
            </div>
        </div>

        <div class="form-grid one">
            <div class="form-group<?php echo $fieldGroupClass('guardian_contact'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('guardian_contact')); ?></label>
                <input type="text" name="guardian_contact" <?php echo $fieldReadonlyAttr('guardian_contact', $isBuiltinActive('guardian_type')); ?>>
            </div>
        </div>

        <?php if (!empty($customFieldsBySection['Guardian Information'])): ?>
            <div class="form-grid two">
                <?php foreach ($customFieldsBySection['Guardian Information'] as $field): ?>
                    <?php $fieldKey = (string)$field['field_key']; ?>
                    <div class="form-group">
                        <label><?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?></label>
                        <?php if (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'select'): ?>
                            <select name="<?php echo htmlspecialchars($fieldKey); ?>" data-guardian-field="<?php echo htmlspecialchars($fieldKey); ?>">
                                <option value="">Select</option>
                                <?php foreach (smartenroll_custom_field_options($field) as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'textarea'): ?>
                            <textarea name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>" data-guardian-field="<?php echo htmlspecialchars($fieldKey); ?>"></textarea>
                        <?php else: ?>
                            <input type="<?php echo htmlspecialchars(smartenroll_input_type_for($fieldKey, $customFieldMap)); ?>" name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>" data-guardian-field="<?php echo htmlspecialchars($fieldKey); ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <!-- LEARNERS WITH SPECIAL EDUCATION NEEDS -->
    <section class="form-section">
        <h2>D. Learners with Special Education Needs</h2>

        <!-- D1 -->
        <div class="form-grid one">
            <div class="form-group<?php echo $fieldGroupClass('special_needs'); ?>">
                <label>
                    <?php echo htmlspecialchars($builtinLabel('special_needs')); ?>
                    <br>
                    <small>(e.g. physical, mental, social disability, giftedness, among others)</small>
                </label>
                <input type="text" name="special_needs" placeholder="Specify if any" <?php echo $fieldDisabledAttr('special_needs'); ?>>
            </div>
        </div>

        <!-- D2 -->
            <div class="form-grid one<?php echo $isBuiltinActive('medication') ? '' : ' form-group-hidden'; ?>">
                <div class="form-group">
                    <label><?php echo htmlspecialchars($builtinLabel('medication')); ?></label>
                    <div style="display:flex; gap:30px; margin-top:10px;">
                        <label><input type="radio" name="medication" value="yes" <?php echo $fieldDisabledAttr('medication'); ?>> Yes</label>
                        <label><input type="radio" name="medication" value="no" checked <?php echo $fieldDisabledAttr('medication'); ?>> No</label>
                    </div>
                </div>
            </div>

        <!-- D3 -->
        <div class="form-grid one">
            <div class="form-group<?php echo $fieldGroupClass('medication_details'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('medication_details')); ?></label>
                <input
                    type="text"
                    id="medication_details"
                    name="medication_details"
                    placeholder="Specify medication"
                    <?php echo !$isBuiltinActive('medication_details') ? 'disabled' : ($isBuiltinActive('medication') ? 'disabled' : ''); ?>
                >
            </div>
        </div>

        <?php if (!empty($customFieldsBySection['Special Education Needs'])): ?>
            <div class="form-grid two">
                <?php foreach ($customFieldsBySection['Special Education Needs'] as $field): ?>
                    <?php $fieldKey = (string)$field['field_key']; ?>
                    <div class="form-group">
                        <label><?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?></label>
                        <?php if (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'select'): ?>
                            <select name="<?php echo htmlspecialchars($fieldKey); ?>">
                                <option value="">Select</option>
                                <?php foreach (smartenroll_custom_field_options($field) as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'textarea'): ?>
                            <textarea name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>"></textarea>
                        <?php else: ?>
                            <input type="<?php echo htmlspecialchars(smartenroll_input_type_for($fieldKey, $customFieldMap)); ?>" name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <!-- IN CASE OF EMERGENCY -->
    <section class="form-section">
        <h2>E. In Case of Emergency (Call Order of Priority)</h2>

        <!-- 1st Priority -->
        <div class="form-grid emergency-contact-block" data-emergency-block="1">
            <div class="form-group<?php echo $fieldGroupClass('emergency1_name'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('emergency1_name')); ?></label>
                <input type="text" name="emergency1_name" placeholder="Full Name" <?php echo $fieldDisabledAttr('emergency1_name'); ?>>
            </div>

            <div class="form-group<?php echo $fieldGroupClass('emergency1_contact'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('emergency1_contact')); ?></label>
                <input type="tel" name="emergency1_contact" placeholder="09XXXXXXXXX" <?php echo $fieldDisabledAttr('emergency1_contact'); ?>>
            </div>

            <div class="form-group<?php echo $fieldGroupClass('emergency1_relationship'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('emergency1_relationship')); ?></label>
                <input type="text" name="emergency1_relationship" placeholder="e.g. Mother, Father, Guardian" <?php echo $fieldDisabledAttr('emergency1_relationship'); ?>>
            </div>
        </div>

        <!-- 2nd Priority -->
        <div class="form-grid emergency-contact-block emergency-contact-hidden" data-emergency-block="2">
            <div class="form-group-with-remove<?php echo $fieldGroupClass('emergency2_name'); ?>">
                <button type="button" class="emergency-remove-btn" data-remove-block="2" title="Remove contact">&times;</button>
                <div class="form-group">
                    <label><?php echo htmlspecialchars($builtinLabel('emergency2_name')); ?></label>
                    <input type="text" name="emergency2_name" placeholder="Full Name" disabled>
                </div>
            </div>

            <div class="form-group<?php echo $fieldGroupClass('emergency2_contact'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('emergency2_contact')); ?></label>
                <input type="tel" name="emergency2_contact" placeholder="09XXXXXXXXX" disabled>
            </div>

            <div class="form-group<?php echo $fieldGroupClass('emergency2_relationship'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('emergency2_relationship')); ?></label>
                <input type="text" name="emergency2_relationship" placeholder="e.g. Aunt, Uncle, Guardian" disabled>
            </div>
        </div>

        <!-- 3rd Priority -->
        <div class="form-grid emergency-contact-block emergency-contact-hidden" data-emergency-block="3">
            <div class="form-group-with-remove<?php echo $fieldGroupClass('emergency3_name'); ?>">
                <button type="button" class="emergency-remove-btn" data-remove-block="3" title="Remove contact">&times;</button>
                <div class="form-group">
                    <label><?php echo htmlspecialchars($builtinLabel('emergency3_name')); ?></label>
                    <input type="text" name="emergency3_name" placeholder="Full Name" disabled>
                </div>
            </div>

            <div class="form-group<?php echo $fieldGroupClass('emergency3_contact'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('emergency3_contact')); ?></label>
                <input type="tel" name="emergency3_contact" placeholder="09XXXXXXXXX" disabled>
            </div>

            <div class="form-group<?php echo $fieldGroupClass('emergency3_relationship'); ?>">
                <label><?php echo htmlspecialchars($builtinLabel('emergency3_relationship')); ?></label>
                <input type="text" name="emergency3_relationship" placeholder="e.g. Relative, Caregiver" disabled>
            </div>
        </div>

        <div class="emergency-contact-actions">
            <button type="button" class="emergency-add-btn" id="addEmergencyContactBtn">+ Add Emergency Contact</button>
        </div>

        <?php if (!empty($customFieldsBySection['Emergency Contacts'])): ?>
            <div class="form-grid two">
                <?php foreach ($customFieldsBySection['Emergency Contacts'] as $field): ?>
                    <?php $fieldKey = (string)$field['field_key']; ?>
                    <div class="form-group">
                        <label><?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?></label>
                        <?php if (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'select'): ?>
                            <select name="<?php echo htmlspecialchars($fieldKey); ?>">
                                <option value="">Select</option>
                                <?php foreach (smartenroll_custom_field_options($field) as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif (smartenroll_input_type_for($fieldKey, $customFieldMap) === 'textarea'): ?>
                            <textarea name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>"></textarea>
                        <?php else: ?>
                            <input type="<?php echo htmlspecialchars(smartenroll_input_type_for($fieldKey, $customFieldMap)); ?>" name="<?php echo htmlspecialchars($fieldKey); ?>" placeholder="<?php echo htmlspecialchars(smartenroll_field_labelize($fieldKey, $customFieldMap)); ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <!-- SUBMIT BUTTON -->
    <div class="form-submit-area">
        <button type="button" id="submitBtn" class="submit-btn">
            Submit Enrollment
        </button>
    </div>
</form>
    </main>
    <!-- SUMMARY MODAL -->
    <div class="modal-overlay" id="summaryModal">
        <div class="modal-box">
            <h2>Confirm Enrollment Details</h2>

            <div id="summaryContent"></div>

            <div class="modal-actions">
            <button type="button" id="confirmSubmit" class="confirm-btn">Confirm</button>

    
                <button id="cancelSubmit" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>



<!-- SUCCESS POPUP -->
<div id="successPopup" class="popup-overlay">

  <div class="popup-box">

    <!-- LOGO → CHECK MORPH ICON -->
    <div class="popup-icon success-icon" id="successIcon">
        <img src="assets/logo.png" id="successLogo" alt="Logo">
        <i class="fas fa-check" id="successCheck"></i>
    </div>

    <h2>Enrollment Successful</h2>

    <p>The student has been successfully enrolled.</p>

    <button class="popup-btn" id="closeSuccess">OK</button>

  </div>

</div>

<!-- VALIDATION POPUP -->
<div id="validationPopup" class="popup-overlay">

  <div class="popup-box">

    <!-- LOGO → X MORPH ICON -->
    <div class="popup-icon" id="popupIcon">
        <img src="assets/logo.png" id="popupLogo" alt="Logo">
        <i class="fas fa-times" id="popupX"></i>
    </div>

    <h2>Incomplete Form</h2>

    <p>Please complete all required fields before submitting.</p>

    <button class="popup-btn" id="okValidation">OK</button>

  </div>

</div>

    <script>
    window.smartenrollFieldLabels = <?php echo json_encode($fieldLabelsForJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script src="js/enroll.js"></script>

    </body>
    </html>
