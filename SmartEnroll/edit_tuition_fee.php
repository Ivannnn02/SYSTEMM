<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/enrollment_form_config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

smartenroll_require_role('finance');

function smartenroll_parse_fee_value(mixed $value): float
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return 0.0;
    }

    $normalized = str_replace(',', '', $raw);
    if (!is_numeric($normalized)) {
        throw new RuntimeException('Fee must be a valid number.');
    }

    $fee = round((float)$normalized, 2);
    if ($fee < 0) {
        throw new RuntimeException('Fee cannot be negative.');
    }

    return $fee;
}

function smartenroll_get_or_create_grade_breakdown_table(?mysqli $conn = null): void
{
    $ownsConnection = false;
    if ($conn === null) {
        $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
        $conn->set_charset('utf8mb4');
        $ownsConnection = true;
    }

    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS `grade_breakdown_components` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `grade_key` VARCHAR(255) UNIQUE NOT NULL,
                `components` JSON NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } finally {
        if ($ownsConnection && $conn) {
            $conn->close();
        }
    }
}

function smartenroll_get_grade_breakdown_components(?mysqli $conn = null): array
{
    $ownsConnection = false;
    if ($conn === null) {
        $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
        $conn->set_charset('utf8mb4');
        $ownsConnection = true;
    }

    try {
        smartenroll_get_or_create_grade_breakdown_table($conn);
        
        $result = $conn->query("SELECT grade_key, components FROM `grade_breakdown_components`");
        $components = [];
        
        while ($row = $result->fetch_assoc()) {
            $gradeKey = (string)($row['grade_key'] ?? '');
            $componentsJson = (string)($row['components'] ?? '{}');
            $components[$gradeKey] = json_decode($componentsJson, true) ?? [];
        }

        return $components;
    } finally {
        if ($ownsConnection && $conn) {
            $conn->close();
        }
    }
}

function smartenroll_save_grade_breakdown_components(array $breakdownData, ?mysqli $conn = null): void
{
    $ownsConnection = false;
    if ($conn === null) {
        $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
        $conn->set_charset('utf8mb4');
        $ownsConnection = true;
    }

    try {
        smartenroll_get_or_create_grade_breakdown_table($conn);

        foreach ($breakdownData as $gradeKey => $components) {
            $componentsJson = json_encode($components, JSON_UNESCAPED_SLASHES);
            
            $stmt = $conn->prepare("
                INSERT INTO `grade_breakdown_components` (grade_key, components)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                    components = VALUES(components),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->bind_param('ss', $gradeKey, $componentsJson);
            $stmt->execute();
            $stmt->close();

            // Also update the total tuition fee in the grade level table
            $totalFee = array_sum($components);
            $updateStmt = $conn->prepare("
                UPDATE `enrollment_grade_levels` 
                SET tuition_fee = ? 
                WHERE grade_key = ?
            ");
            $updateStmt->bind_param('ds', $totalFee, $gradeKey);
            $updateStmt->execute();
            $updateStmt->close();
        }
    } finally {
        if ($ownsConnection && $conn) {
            $conn->close();
        }
    }
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save_tuition_breakdown') {
    try {
        $breakdownData = [];
        $formComponents = $_POST['components'] ?? [];

        foreach ($formComponents as $gradeKey => $gradeComponentData) {
            $gradeKey = trim((string)$gradeKey);
            if ($gradeKey === '') {
                continue;
            }

            $gradeComponents = [];

            if (is_array($gradeComponentData) && isset($gradeComponentData['name'], $gradeComponentData['amount'])) {
                $componentNames = $gradeComponentData['name'];
                $componentAmounts = $gradeComponentData['amount'];

                foreach ($componentNames as $componentIndex => $rawComponentName) {
                    $componentName = trim((string)$rawComponentName);
                    if ($componentName === '') {
                        continue;
                    }

                    $amount = smartenroll_parse_fee_value($componentAmounts[$componentIndex] ?? '');
                    $gradeComponents[$componentName] = $amount;
                }
            } elseif (is_array($gradeComponentData)) {
                foreach ($gradeComponentData as $componentName => $componentValues) {
                    if (!is_array($componentValues)) {
                        continue;
                    }

                    $componentName = trim((string)$componentName);
                    if ($componentName === '') {
                        continue;
                    }

                    foreach ($componentValues as $value) {
                        $amount = smartenroll_parse_fee_value($value);
                        $gradeComponents[$componentName] = $amount;
                    }
                }
            }

            if (in_array($gradeKey, ['Toddler', 'Casa', 'Kindergarten'], true)) {
                unset($gradeComponents['Books']);
            }

            if (!empty($gradeComponents)) {
                $breakdownData[$gradeKey] = $gradeComponents;
            }
        }

        smartenroll_save_grade_breakdown_components($breakdownData);
        header('Location: edit_tuition_fee.php?status=saved');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

if (($_GET['status'] ?? '') === 'saved') {
    $successMessage = 'Tuition fee breakdown was updated successfully.';
}

$gradeLevels = [];
$gradeBreakdowns = [];

try {
    $gradeLevels = smartenroll_get_grade_levels();
    $savedComponents = smartenroll_get_grade_breakdown_components();
    $templates = smartenroll_grade_breakdown_templates();

    // Merge saved components with templates
    foreach ($gradeLevels as $grade) {
        $gradeKey = (string)$grade['grade_key'];
        $gradeLabel = (string)$grade['grade_label'];
        
        if (isset($savedComponents[$gradeKey]) && !empty($savedComponents[$gradeKey])) {
            $gradeBreakdowns[$gradeLabel] = $savedComponents[$gradeKey];
        } elseif (isset($templates[$gradeLabel])) {
            $gradeBreakdowns[$gradeLabel] = $templates[$gradeLabel];
        } else {
            $gradeBreakdowns[$gradeLabel] = ['Tuition Fee' => (float)$grade['tuition_fee']];
        }
    }
} catch (Throwable $e) {
    $gradeLevels = [];
    $gradeBreakdowns = [];
    if ($errorMessage === '') {
        $errorMessage = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMARTENROLL | Edit Tuition Fee</title>
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
                <h1>Edit Tuition Fee</h1>
                <p>Update the tuition fee amount for each grade level.</p>
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

        <div class="settings-intro">
            <h2>Tuition Fees by Grade Level</h2>
            <p>Edit the breakdown of fees for each grade level. Each component can be customized.</p>
        </div>

        <form method="post">
            <input type="hidden" name="form_action" value="save_tuition_breakdown">

            <?php foreach ($gradeLevels as $gradeRow): ?>
                <?php $gradeKey = (string)$gradeRow['grade_key']; ?>
                <?php $gradeLabel = (string)$gradeRow['grade_label']; ?>
                <?php $breakdown = $gradeBreakdowns[$gradeLabel] ?? []; ?>

                <div class="settings-subsection">
                    <h3 class="detail-section-title"><?php echo htmlspecialchars($gradeLabel); ?></h3>
                    
                    <input type="hidden" name="grade_key[]" value="<?php echo htmlspecialchars($gradeKey); ?>">

                    <div class="grade-breakdown-table-wrap">
                        <table class="grade-breakdown-table" data-grade-key="<?php echo htmlspecialchars($gradeKey, ENT_QUOTES); ?>">
                            <thead>
                                <tr>
                                    <th>Fee Component</th>
                                    <th>Amount</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($breakdown as $componentName => $componentValue): ?>
                                    <tr class="component-row">
                                        <td>
                                            <input
                                                type="text"
                                                name="components[<?php echo htmlspecialchars($gradeKey, ENT_QUOTES); ?>][name][]"
                                                value="<?php echo htmlspecialchars($componentName); ?>"
                                                class="fee-component-name-input"
                                                placeholder="Component name"
                                            >
                                        </td>
                                        <td>
                                            <input
                                                type="number"
                                                name="components[<?php echo htmlspecialchars($gradeKey, ENT_QUOTES); ?>][amount][]"
                                                value="<?php echo htmlspecialchars(number_format($componentValue, 2, '.', '')); ?>"
                                                min="0"
                                                step="0.01"
                                                inputmode="decimal"
                                                class="fee-component-input"
                                                placeholder="0.00"
                                            >
                                        </td>
                                        <td class="component-action-cell">
                                            <button type="button" class="component-remove-btn" aria-label="Remove component">&times;</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="grade-table-actions">
                            <button type="button" class="grade-add-btn" data-grade-key="<?php echo htmlspecialchars($gradeKey, ENT_QUOTES); ?>">+ Add component</button>
                        </div>
                    </div>

                    <?php if (!empty($breakdown)): ?>
                        <div class="grade-total-row">
                            <strong>Total:</strong>
                            <span class="total-amount">
                                ₱<?php echo number_format(array_sum($breakdown), 2); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="settings-actions">
                <span class="settings-help">Use numbers only. Example: `72740.00`</span>
                <button type="submit" class="settings-save-btn">Save Tuition Fees</button>
            </div>
        </form>
    </div>
</main>
<script>
    function updateGradeTotals(section) {
        const amountInputs = section.querySelectorAll('input[name*="[amount][]"]');
        let total = 0;
        amountInputs.forEach((input) => {
            const value = parseFloat(input.value);
            if (!Number.isNaN(value)) {
                total += value;
            }
        });
        const totalAmount = section.querySelector('.total-amount');
        if (totalAmount) {
            totalAmount.textContent = '₱' + total.toFixed(2);
        }
    }

    function createComponentRow(gradeKey) {
        const row = document.createElement('tr');
        row.className = 'component-row';

        const nameCell = document.createElement('td');
        const nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.name = `components[${gradeKey}][name][]`;
        nameInput.className = 'fee-component-name-input';
        nameInput.placeholder = 'Component name';
        nameCell.appendChild(nameInput);

        const amountCell = document.createElement('td');
        const amountInput = document.createElement('input');
        amountInput.type = 'number';
        amountInput.name = `components[${gradeKey}][amount][]`;
        amountInput.className = 'fee-component-input';
        amountInput.min = '0';
        amountInput.step = '0.01';
        amountInput.inputMode = 'decimal';
        amountInput.placeholder = '0.00';
        amountCell.appendChild(amountInput);

        const actionCell = document.createElement('td');
        actionCell.className = 'component-action-cell';
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'component-remove-btn';
        removeBtn.setAttribute('aria-label', 'Remove component');
        removeBtn.textContent = '×';
        actionCell.appendChild(removeBtn);

        row.appendChild(nameCell);
        row.appendChild(amountCell);
        row.appendChild(actionCell);

        return row;
    }

    document.addEventListener('click', function (event) {
        if (event.target.matches('.grade-add-btn')) {
            const gradeKey = event.target.dataset.gradeKey;
            const subsection = event.target.closest('.settings-subsection');
            const tbody = subsection.querySelector('tbody');
            const newRow = createComponentRow(gradeKey);
            tbody.appendChild(newRow);
            updateGradeTotals(subsection);
        }

        if (event.target.matches('.component-remove-btn')) {
            const row = event.target.closest('tr');
            if (row) {
                const section = row.closest('.settings-subsection');
                row.remove();
                if (section) {
                    updateGradeTotals(section);
                }
            }
        }
    });

    document.addEventListener('input', function (event) {
        if (event.target.matches('input[name*="[amount][]"]')) {
            const section = event.target.closest('.settings-subsection');
            if (section) {
                updateGradeTotals(section);
            }
        }
    });

    document.querySelectorAll('.settings-subsection').forEach((section) => {
        updateGradeTotals(section);
    });
</script>
</body>
</html>
