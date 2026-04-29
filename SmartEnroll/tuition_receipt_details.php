<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/enrollment_form_config.php';
smartenroll_auth_start_session();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/mail/PHPMailer/mail_helper.php';

$currentUser = smartenroll_require_role('finance');

$paymentHistory = [];
$gmailSendHistory = [];
$selectedStudent = null;
$selectedPayment = null;
$error = '';
$lastEmailError = '';
$selectedId = trim((string)($_GET['student_id'] ?? $_POST['student_id'] ?? ''));
$successMessage = $_SESSION['pay_tuition_success'] ?? '';
$warningMessage = $_SESSION['pay_tuition_warning'] ?? '';
unset($_SESSION['pay_tuition_success'], $_SESSION['pay_tuition_warning']);

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

function format_money(float $amount): string
{
    return 'PHP ' . number_format($amount, 2);
}

function format_invoice_money(float $amount): string
{
    return number_format($amount, 2);
}

function format_invoice_date(?string $dateValue): string
{
    $raw = trim((string)$dateValue);
    if ($raw === '') {
        return 'N/A';
    }

    $date = date_create($raw);
    return $date ? $date->format('d M Y') : $raw;
}

function format_history_timestamp(?string $dateValue): string
{
    $raw = trim((string)$dateValue);
    if ($raw === '') {
        return 'N/A';
    }

    $date = date_create($raw);
    return $date ? $date->format('d M Y g:i A') : $raw;
}

function build_invoice_item_description(array $student, array $item): string
{
    $gradeLevel = strtoupper(trim((string)($student['grade_level'] ?? '')));
    $label = strtoupper(trim((string)($item['label'] ?? $item['option'] ?? 'PAYMENT ITEM')));

    if ($label === 'REGISTRATION FEE & MISCELLANEOUS') {
        return 'REGISTRATION FEE';
    }

    if ($gradeLevel !== '' && ($label === 'TUITION FEE' || $label === 'MONTHLY PAYMENT')) {
        return trim($gradeLevel . ' ' . $label);
    }

    return $label !== '' ? $label : 'PAYMENT ITEM';
}

function get_invoice_reference(array $student, array $payment = []): string
{
    $gradeLevel = trim((string)($student['grade_level'] ?? $payment['grade_level'] ?? ''));
    $schoolYear = trim((string)($student['school_year'] ?? $payment['school_year'] ?? ''));

    if ($gradeLevel === '' && $schoolYear === '') {
        return 'N/A';
    }

    if ($gradeLevel === '') {
        return 'SCHOOL YEAR ' . ($schoolYear !== '' ? $schoolYear : 'N/A');
    }

    return trim($gradeLevel . ' SCHOOL YEAR ' . ($schoolYear !== '' ? $schoolYear : 'N/A'));
}

function get_school_address_lines(): array
{
    return [
        'Adreo Montessori Inc.',
        'Bldg. 42-43 Great Mall of',
        'Central Luzon, Brgy. Tabun',
        'Xevera',
        'MABALACAT CITY',
        'PAMPANGA 2010',
        'PHILIPPINES',
    ];
}

function get_payment_detail_blocks(): array
{
    return [
        [
            'branch' => 'ADREO XEVERA',
            'account_name' => 'ADREO MONTESSORI INCORPORATED',
            'bank' => 'Bank of the Philippine Islands (BPI)',
            'account_number' => '0121-0022-01',
        ],
        [
            'branch' => 'ADREO ANGELES',
            'account_name' => 'ADREO LEARNING HUB',
            'bank' => 'Security Bank',
            'account_number' => '0000073919401',
        ],
        [
            'branch' => 'ADREO CAMACHILES',
            'account_name' => 'ADREO MONTESSORI INCORPORATED',
            'bank' => 'Philippine National Bank (PNB)',
            'account_number' => '203570004892',
        ],
    ];
}

function get_registered_office_line(): string
{
    return 'Registered Office: Bldg. 42-43 Great Mall of Central Luzon, Brgy. Tabun Xevera, Mabalacat City, Pampanga, 2010, Philippines.';
}

function is_loopback_host(string $host): bool
{
    $normalized = strtolower(trim($host));
    if ($normalized === '') {
        return true;
    }

    $normalized = preg_replace('/:\d+$/', '', $normalized);
    return in_array($normalized, ['localhost', '127.0.0.1', '::1'], true);
}

function build_app_url(string $path, array $query = []): string
{
    $config = get_email_config();
    $configuredBase = trim((string)($config['app_url'] ?? ''));
    if ($configuredBase !== '' && !is_loopback_host((string)parse_url($configuredBase, PHP_URL_HOST))) {
        $baseUrl = rtrim($configuredBase, '/');
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        $serverAddr = trim((string)($_SERVER['SERVER_ADDR'] ?? ''));

        if ($host === '') {
            $host = $serverAddr !== '' ? $serverAddr : 'localhost';
        }

        if (is_loopback_host($host) && $serverAddr !== '' && !is_loopback_host($serverAddr)) {
            $port = '';
            if (preg_match('/:(\d+)$/', $host, $portMatches)) {
                $port = ':' . $portMatches[1];
            }
            $host = $serverAddr . $port;
        }

        if (is_loopback_host($host) && $configuredBase !== '') {
            $configuredPath = trim((string)parse_url($configuredBase, PHP_URL_PATH));
            $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
            $scriptDir = $configuredPath !== '' ? rtrim($configuredPath, '/') : ($scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/'));
            $baseUrl = $scheme . '://' . $host . $scriptDir;
        } else {
            $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
            $scriptDir = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
            $baseUrl = $scheme . '://' . $host . $scriptDir;
        }
    }

    if ($baseUrl === '') {
        $baseUrl = $configuredBase;
    }

    $url = $baseUrl . '/' . ltrim($path, '/');
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    return $url;
}

function generate_payment_token(): string
{
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        return bin2hex(hash('sha256', uniqid('payment_', true), true));
    }
}

function generate_invoice_number_candidate(): string
{
    try {
        $number = random_int(0, 9999);
    } catch (Throwable $e) {
        $number = (int)(microtime(true) * 10000) % 10000;
    }

    return 'INV-' . str_pad((string)$number, 4, '0', STR_PAD_LEFT);
}

function invoice_number_exists(mysqli $conn, string $invoiceNumber): bool
{
    $stmt = $conn->prepare(
        "SELECT id
         FROM tuition_payments
         WHERE receipt_no = ?
         LIMIT 1"
    );
    $stmt->bind_param('s', $invoiceNumber);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();

    return $exists;
}

function generate_unique_invoice_number(mysqli $conn): string
{
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $candidate = generate_invoice_number_candidate();
        if (!invoice_number_exists($conn, $candidate)) {
            return $candidate;
        }
    }

    return 'INV-' . str_pad((string)(((int)date('is')) % 10000), 4, '0', STR_PAD_LEFT);
}

function get_student(mysqli $conn, string $studentId): ?array
{
    $stmt = $conn->prepare(
        "SELECT id, student_id, learner_lname, learner_fname, learner_mname, grade_level, school_year, email
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

function get_school_year_paid_total(mysqli $conn, int $enrollmentId, string $schoolYear): float
{
    if ($enrollmentId <= 0) {
        return 0.0;
    }

    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid
         FROM tuition_payments
         WHERE enrollment_id = ?
           AND COALESCE(school_year, '') = ?"
    );
    $stmt->bind_param('is', $enrollmentId, $schoolYear);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return round((float)($row['total_paid'] ?? 0), 2);
}

function backfill_tuition_balances(mysqli $conn): int
{
    return smartenroll_sync_tuition_payment_totals($conn);
}

function get_paid_amounts_by_option(mysqli $conn, int $enrollmentId, string $schoolYear): array
{
    $totals = [];
    if ($enrollmentId <= 0) {
        return $totals;
    }

    $stmt = $conn->prepare(
        "SELECT payment_items, amount_paid
         FROM tuition_payments
         WHERE enrollment_id = ?
           AND COALESCE(school_year, '') = ?"
    );
    $stmt->bind_param('is', $enrollmentId, $schoolYear);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $items = decode_saved_payment_items((string)($row['payment_items'] ?? ''), (float)($row['amount_paid'] ?? 0));
        foreach ($items as $item) {
            $option = trim((string)($item['option'] ?? $item['label'] ?? ''));
            if ($option === '') {
                continue;
            }

            $totals[$option] = round((float)($totals[$option] ?? 0) + (float)($item['amount'] ?? 0), 2);
        }
    }

    $stmt->close();
    return $totals;
}

function get_paid_amounts_from_history(array $historyRows): array
{
    $totals = [];

    foreach ($historyRows as $row) {
        $items = is_array($row['items'] ?? null)
            ? $row['items']
            : decode_saved_payment_items((string)($row['payment_items'] ?? ''), (float)($row['amount_paid'] ?? 0));

        foreach ($items as $item) {
            $option = trim((string)($item['option'] ?? $item['label'] ?? ''));
            if ($option === '') {
                continue;
            }

            $totals[$option] = round((float)($totals[$option] ?? 0) + (float)($item['amount'] ?? 0), 2);
        }
    }

    return $totals;
}

function ensure_audit_log_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 0,
            user_role VARCHAR(50) NOT NULL DEFAULT '',
            action VARCHAR(120) NOT NULL,
            entity_type VARCHAR(80) NOT NULL,
            entity_id VARCHAR(120) NOT NULL DEFAULT '',
            details_json LONGTEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_action_created (action, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function write_audit_log(mysqli $conn, array $actor, string $action, string $entityType, string $entityId, array $details = []): void
{
    $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $userId = (int)($actor['id'] ?? 0);
    $userRole = trim((string)($actor['role'] ?? ''));

    $stmt = $conn->prepare(
        "INSERT INTO audit_logs (user_id, user_role, action, entity_type, entity_id, details_json)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('isssss', $userId, $userRole, $action, $entityType, $entityId, $detailsJson);
    $stmt->execute();
    $stmt->close();
}

function parse_payment_items(string $rawJson, array $allowedOptions, array $feeDefaults, float $defaultTuitionAmount): array
{
    $decoded = json_decode($rawJson, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Please add at least one payment row.');
    }

    $items = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }

        $option = trim((string)($row['option'] ?? ''));
        if ($option === '') {
            continue;
        }

        if ($option === '' || !in_array($option, $allowedOptions, true)) {
            throw new RuntimeException('Please choose a valid payment item for every row.');
        }

        $amount = $option === 'Tuition Fee'
            ? round((float)($row['amount'] ?? 0), 2)
            : round((float)($feeDefaults[$option] ?? 0), 2);

        if ($amount <= 0) {
            if ($option === 'Tuition Fee') {
                throw new RuntimeException('Please enter a valid tuition fee amount.');
            }
            throw new RuntimeException('Please set a valid fixed amount for every selected payment item.');
        }

        $items[] = [
            'option' => $option,
            'label' => $option,
            'amount' => $amount,
        ];
    }

    if (empty($items)) {
        throw new RuntimeException('Please add at least one payment row.');
    }

    return $items;
}

function get_payment_total(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += (float)($item['amount'] ?? 0);
    }

    return round($total, 2);
}

function sum_payment_history(array $historyRows): float
{
    $total = 0.0;
    foreach ($historyRows as $row) {
        $total += (float)($row['amount_paid'] ?? 0);
    }

    return round($total, 2);
}

function attach_running_balances(array $historyRows, float $programTotal): array
{
    usort($historyRows, static function (array $a, array $b): int {
        $dateCompare = strcmp((string)($a['payment_date'] ?? ''), (string)($b['payment_date'] ?? ''));
        if ($dateCompare !== 0) {
            return $dateCompare;
        }

        return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
    });

    $paid = 0.0;
    foreach ($historyRows as &$row) {
        $paid += (float)($row['amount_paid'] ?? 0);
        $row['balance_after'] = max(0, round($programTotal - $paid, 2));
    }
    unset($row);

    usort($historyRows, static function (array $a, array $b): int {
        $dateCompare = strcmp((string)($b['payment_date'] ?? ''), (string)($a['payment_date'] ?? ''));
        if ($dateCompare !== 0) {
            return $dateCompare;
        }

        return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
    });

    return $historyRows;
}

function find_payment_by_id(array $historyRows, int $paymentId): ?array
{
    foreach ($historyRows as $row) {
        if ((int)($row['id'] ?? 0) === $paymentId) {
            return $row;
        }
    }

    return null;
}

function resolve_payment_balance_after(array $payment): float
{
    $storedBalance = round((float)($payment['balance_after'] ?? 0), 2);
    $tuitionFee = round((float)($payment['tuition_fee'] ?? 0), 2);
    $amountPaid = round((float)($payment['amount_paid'] ?? 0), 2);

    if ($storedBalance > 0 || $amountPaid >= $tuitionFee) {
        return max(0, $storedBalance);
    }

    return max(0, round($tuitionFee - $amountPaid, 2));
}

function get_payment_cumulative_paid(array $payment): float
{
    $tuitionFee = round((float)($payment['tuition_fee'] ?? 0), 2);
    $balanceAfter = resolve_payment_balance_after($payment);

    return max(0, round($tuitionFee - $balanceAfter, 2));
}

function decode_saved_payment_items(?string $rawJson, float $amountPaid): array
{
    $decoded = json_decode((string)$rawJson, true);
    if (!is_array($decoded) || empty($decoded)) {
        return [[
            'option' => 'Tuition Fee',
            'label' => 'Tuition Fee',
            'amount' => round($amountPaid, 2),
        ]];
    }

    $items = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }

        $label = trim((string)($row['label'] ?? $row['option'] ?? ''));
        $option = trim((string)($row['option'] ?? ($label !== '' ? $label : 'Other')));
        $amount = round((float)($row['amount'] ?? 0), 2);

        if ($label === '' || $amount <= 0) {
            continue;
        }

        $items[] = [
            'option' => $option,
            'label' => $label,
            'amount' => $amount,
        ];
    }

    if (empty($items)) {
        $items[] = [
            'option' => 'Tuition Fee',
            'label' => 'Tuition Fee',
            'amount' => round($amountPaid, 2),
        ];
    }

    return $items;
}

function build_invoice_item_list(array $student, array $items, string $emptyLabel = 'N/A'): string
{
    $labels = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $label = trim(build_invoice_item_description($student, $item));
        if ($label !== '') {
            $labels[] = $label;
        }
    }

    $labels = array_values(array_unique($labels));
    return !empty($labels) ? implode(', ', $labels) : $emptyLabel;
}

function build_history_fill_items(array $student, array $items): array
{
    $normalizedItems = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $option = trim((string)($item['option'] ?? $item['label'] ?? ''));
        $amount = round((float)($item['amount'] ?? 0), 2);
        if ($option === '' || $amount <= 0) {
            continue;
        }

        $normalizedItems[] = [
            'option' => $option,
            'display_label' => build_invoice_item_description($student, $item),
            'amount' => $amount,
        ];
    }

    return $normalizedItems;
}

function encode_history_fill_payload(array $student, string $paymentDate, string $receiptNo, array $items, ?float $amountPaid = null): string
{
    $normalizedItems = build_history_fill_items($student, $items);
    $payload = [
        'payment_date' => trim($paymentDate),
        'receipt_no' => trim($receiptNo),
        'amount_paid' => round($amountPaid ?? get_payment_total($normalizedItems), 2),
        'items' => $normalizedItems,
    ];

    if ($payload['receipt_no'] === '' && $payload['payment_date'] === '' && empty($payload['items']) && $payload['amount_paid'] <= 0) {
        return '';
    }

    return htmlspecialchars((string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
}

function render_receipt_items_html(array $items): string
{
    $rows = '';
    foreach ($items as $item) {
        $rows .= '<tr>'
            . '<td style="padding:10px;border:1px solid #e5e7eb;background:#f8fafc;">' . htmlspecialchars((string)$item['label']) . '</td>'
            . '<td style="padding:10px;border:1px solid #e5e7eb;text-align:right;">' . htmlspecialchars(format_money((float)$item['amount'])) . '</td>'
            . '</tr>';
    }

    return $rows;
}

function render_receipt_items_text(array $items): string
{
    $lines = [];
    foreach ($items as $item) {
        $lines[] = '- ' . ($item['label'] ?? 'Payment Item') . ': ' . format_money((float)($item['amount'] ?? 0));
    }

    return implode("\r\n", $lines);
}

function send_receipt_email(array $student, array $payment, string $layout = 'summary'): bool
{
    global $lastEmailError;

    $to = trim((string)($student['email'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $lastEmailError = 'This student does not have a valid enrollment email address.';
        return false;
    }

    $emailLayout = in_array($layout, ['summary', 'invoice'], true) ? $layout : 'summary';

    $studentName = format_name($student);
    if ($studentName === '') {
        $studentName = 'N/A';
    }

    $studentId = trim((string)($student['student_id'] ?? ''));
    $gradeLevel = trim((string)($student['grade_level'] ?? $payment['grade_level'] ?? ''));
    $receiptNo = trim((string)($payment['receipt_no'] ?? '')) !== '' ? (string)$payment['receipt_no'] : 'N/A';
    $items = decode_saved_payment_items((string)($payment['payment_items'] ?? ''), (float)($payment['amount_paid'] ?? 0));
    $remainingBalance = resolve_payment_balance_after($payment);
    $cumulativePaid = get_payment_cumulative_paid($payment);
    $invoiceAmount = round((float)($payment['amount_paid'] ?? 0), 2);
    $invoiceAmountDisplay = number_format($invoiceAmount, 2);
    $cumulativePaidDisplay = number_format($cumulativePaid, 2);
    $remainingBalanceDisplay = number_format($remainingBalance, 2);
    $invoiceDateDisplay = format_invoice_date((string)($payment['payment_date'] ?? ''));
    $dueDateDisplay = $invoiceDateDisplay;
    $referenceDisplay = get_invoice_reference($student, $payment);
    $schoolAddressLines = get_school_address_lines();
    $paymentDetailBlocks = get_payment_detail_blocks();
    $registeredOfficeLine = get_registered_office_line();
    $paymentNote = trim((string)($payment['payment_note'] ?? ''));
    $paymentId = (int)($payment['id'] ?? 0);
    $viewLink = $paymentId > 0
        ? build_app_url('tuition_receipt_details.php', [
            'student_id' => (string)($student['student_id'] ?? ''),
            'payment_id' => (string)$paymentId,
        ]) . '#receipt-preview'
        : build_app_url('tuition_receipt_details.php', [
            'student_id' => (string)($student['student_id'] ?? ''),
        ]) . '#receipt-email-preview';
    $embeddedImages = [];
    $logoSummaryMarkup = '<div style="font-family:Arial,sans-serif;font-size:16px;line-height:1.3;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#19325a;text-align:center;">SMARTENROLL</div>';
    $logoInvoiceMarkup = '<div style="font-family:Arial,sans-serif;font-size:16px;line-height:1.3;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#19325a;">SMARTENROLL</div>';
    $logoPath = __DIR__ . '/assets/logo.png';
    if (is_file($logoPath)) {
        $logoCid = 'smartenroll-logo';
        $embeddedImages[] = [
            'path' => $logoPath,
            'cid' => $logoCid,
            'name' => 'Tuition Invoice',
        ];
        $logoSummaryMarkup = '<img src="cid:' . htmlspecialchars($logoCid) . '" alt="SMARTENROLL Logo" width="108" style="display:block;width:108px;max-width:108px;height:auto;border:0;margin:0 auto;">';
        $logoInvoiceMarkup = '<img src="cid:' . htmlspecialchars($logoCid) . '" alt="SMARTENROLL Logo" width="108" style="display:block;width:108px;max-width:108px;height:auto;border:0;">';
    }

    $schoolAddressHtml = '';
    foreach ($schoolAddressLines as $line) {
        $schoolAddressHtml .= '<tr>'
            . '<td style="padding:0;font-size:13px;line-height:1.45;color:#344054;text-align:left;">' . htmlspecialchars($line) . '</td>'
            . '</tr>';
    }

    $summaryEmailItemsHtml = '';
    foreach ($items as $item) {
        $itemLabel = build_invoice_item_description($student, $item);
        $itemAmountDisplay = number_format((float)($item['amount'] ?? 0), 2);
        $summaryEmailItemsHtml .= '<tr>'
            . '<td style="padding:14px 0;border-bottom:1px solid #d8e1ef;color:#22374f;font-size:14px;line-height:1.55;">' . htmlspecialchars($itemLabel) . '</td>'
            . '<td style="padding:14px 0;border-bottom:1px solid #d8e1ef;color:#22374f;font-size:14px;line-height:1.55;text-align:right;white-space:nowrap;">' . htmlspecialchars($itemAmountDisplay) . '</td>'
            . '</tr>';
    }

    if ($summaryEmailItemsHtml === '') {
        $summaryEmailItemsHtml = '<tr>'
            . '<td style="padding:14px 0;border-bottom:1px solid #d8e1ef;color:#98a2b3;font-size:14px;line-height:1.55;font-style:italic;">No billing item added yet</td>'
            . '<td style="padding:14px 0;border-bottom:1px solid #d8e1ef;color:#22374f;font-size:14px;line-height:1.55;text-align:right;white-space:nowrap;">0.00</td>'
            . '</tr>';
    }

    $emailItemsHtml = '';
    foreach ($items as $item) {
        $itemLabel = build_invoice_item_description($student, $item);
        $itemAmountDisplay = number_format((float)($item['amount'] ?? 0), 2);
        $emailItemsHtml .= '<tr>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e7eb;color:#22374f;font-size:14px;line-height:1.55;">' . htmlspecialchars($itemLabel) . '</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e7eb;color:#22374f;font-size:14px;line-height:1.55;text-align:center;white-space:nowrap;">1.00</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e7eb;color:#22374f;font-size:14px;line-height:1.55;text-align:right;white-space:nowrap;">' . htmlspecialchars($itemAmountDisplay) . '</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e7eb;color:#22374f;font-size:14px;line-height:1.55;text-align:right;white-space:nowrap;">0.00</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e7eb;color:#22374f;font-size:14px;line-height:1.55;text-align:center;white-space:nowrap;">Tax on Sales</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e7eb;color:#22374f;font-size:14px;line-height:1.55;text-align:right;white-space:nowrap;">' . htmlspecialchars($itemAmountDisplay) . '</td>'
            . '</tr>';
    }

    if ($emailItemsHtml === '') {
        $emailItemsHtml = '<tr>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e7eb;color:#667085;font-size:14px;line-height:1.55;">No billing item added.</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e7eb;color:#22374f;font-size:14px;line-height:1.55;text-align:center;white-space:nowrap;">1.00</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e7eb;color:#22374f;font-size:14px;line-height:1.55;text-align:right;white-space:nowrap;">0.00</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e7eb;color:#22374f;font-size:14px;line-height:1.55;text-align:right;white-space:nowrap;">0.00</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e7eb;color:#22374f;font-size:14px;line-height:1.55;text-align:center;white-space:nowrap;">Tax on Sales</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e7eb;color:#22374f;font-size:14px;line-height:1.55;text-align:right;white-space:nowrap;">0.00</td>'
            . '</tr>';
    }

    $paymentDetailsHtml = '';
    foreach ($paymentDetailBlocks as $detail) {
        $paymentDetailsHtml .= '<tr>'
            . '<td style="padding:0 0 18px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">'
            . '<tr><td style="padding:0 0 6px;font-size:16px;line-height:1.35;font-weight:700;color:#1f2937;">' . htmlspecialchars((string)$detail['branch']) . '</td></tr>'
            . '<tr><td style="padding:0;font-size:13px;line-height:1.55;color:#344054;">Account Name: ' . htmlspecialchars((string)$detail['account_name']) . '</td></tr>'
            . '<tr><td style="padding:0;font-size:13px;line-height:1.55;color:#344054;">Bank: ' . htmlspecialchars((string)$detail['bank']) . '</td></tr>'
            . '<tr><td style="padding:0;font-size:13px;line-height:1.55;color:#344054;">Account No.: ' . htmlspecialchars((string)$detail['account_number']) . '</td></tr>'
            . '</table>'
            . '</td>'
            . '</tr>';
    }

    $paymentNoteHtml = '';
    if ($paymentNote !== '') {
        $paymentNoteHtml = '
                        <tr>
                            <td style="padding:28px 0 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">
                                    <tr>
                                        <td style="padding:14px 16px;border:1px solid #e5e7eb;background-color:#f8fafc;">
                                            <div style="font-size:13px;line-height:1.45;font-weight:700;color:#1f2937;">Payment Note</div>
                                            <div style="padding-top:6px;font-size:13px;line-height:1.55;color:#344054;">' . htmlspecialchars($paymentNote) . '</div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>';
    }

    $subject = 'SMARTENROLL Tuition Invoice ' . $receiptNo . ' - ' . ($student['student_id'] ?? '');
    $summaryHtml = '
    <html>
    <body style="margin:0;padding:0;background-color:#ffffff;font-family:Arial,sans-serif;color:#22374f;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;background-color:#ffffff;">
            <tr>
                <td align="center" style="padding:20px 16px 28px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:760px;border-collapse:collapse;background-color:#ffffff;">
                        <tr>
                            <td align="center" style="padding:0 0 18px;">
                                ' . $logoSummaryMarkup . '
                                <div style="padding-top:14px;font-size:22px;line-height:1.3;font-weight:400;color:#22374f;">Adreo Montessori Inc.</div>
                                <div style="padding-top:16px;font-size:54px;line-height:1;font-weight:700;color:#22374f;">' . htmlspecialchars($invoiceAmountDisplay) . ' <span style="font-size:18px;font-weight:600;color:#475467;">PHP</span></div>
                                <div style="padding-top:16px;font-size:16px;line-height:1.4;font-weight:700;color:#22374f;">Due ' . htmlspecialchars($dueDateDisplay) . '</div>
                                <div style="padding-top:6px;font-size:15px;line-height:1.4;color:#475467;">Invoice #: ' . htmlspecialchars($receiptNo) . '</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:0 20px 18px;">
                                <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">
                                    <tr>
                                        <td align="center" bgcolor="#3b82f6" style="background-color:#3b82f6;">
                                            <a href="' . htmlspecialchars($viewLink) . '" style="display:block;padding:16px 24px;font-size:16px;line-height:1.3;font-weight:700;color:#ffffff;text-decoration:none;">View Invoice</a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:0 20px 22px;">
                                <div style="font-size:15px;line-height:1.8;color:#41566d;">
                                    <p style="margin:0 0 14px;">Hi,</p>
                                    <p style="margin:0 0 14px;">Here&apos;s invoice <strong style="color:#22374f;">' . htmlspecialchars($receiptNo) . '</strong> for <strong style="color:#22374f;">' . htmlspecialchars(format_money($invoiceAmount)) . '</strong>.</p>
                                    <p style="margin:0 0 14px;">The amount outstanding of <strong style="color:#22374f;">' . htmlspecialchars(format_money($invoiceAmount)) . '</strong> is due on <strong style="color:#22374f;">' . htmlspecialchars($dueDateDisplay) . '</strong>.</p>
                                    <p style="margin:0 0 14px;">View your bill online: <a href="' . htmlspecialchars($viewLink) . '" style="color:#1d4ed8;text-decoration:underline;word-break:break-word;">' . htmlspecialchars($viewLink) . '</a></p>
                                    <p style="margin:0 0 14px;">From your online bill you can print a PDF, export a CSV, or create a free login and view your outstanding bills.</p>
                                    <p style="margin:0 0 14px;">If you have any questions, please let us know.</p>
                                    <p style="margin:0;">Thanks,<br>Adreo Montessori Inc.</p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:0 20px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">
                                    <thead>
                                        <tr>
                                            <th style="padding:0 0 12px;text-align:left;border-top:1px solid #d8e1ef;color:#344054;font-size:12px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;">Description</th>
                                            <th style="padding:0 0 12px;text-align:right;border-top:1px solid #d8e1ef;color:#344054;font-size:12px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>' . $summaryEmailItemsHtml . '</tbody>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    $invoiceHtml = '
    <html>
    <body style="margin:0;padding:0;background-color:#ffffff;font-family:Arial,sans-serif;color:#22374f;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;background-color:#ffffff;">
            <tr>
                <td align="center" style="padding:24px 20px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:980px;border-collapse:collapse;background-color:#ffffff;">
                        <tr>
                            <td style="padding:0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">
                                    <tr>
                                        <td valign="top" style="width:52%;padding:0 28px 36px 0;">
                                            <div style="font-size:56px;line-height:1;font-weight:300;letter-spacing:0.04em;color:#1f2937;">INVOICE</div>
                                            <div style="height:24px;line-height:24px;font-size:0;">&nbsp;</div>
                                            <div style="font-size:18px;line-height:1.5;font-weight:400;color:#22374f;">' . htmlspecialchars($studentName) . '</div>
                                            <div style="height:8px;line-height:8px;font-size:0;">&nbsp;</div>
                                            <div style="font-size:14px;line-height:1.7;color:#667085;">Student ID: ' . htmlspecialchars($studentId !== '' ? $studentId : 'N/A') . '</div>
                                            <div style="font-size:14px;line-height:1.7;color:#667085;">Grade Level: ' . htmlspecialchars($gradeLevel !== '' ? $gradeLevel : 'N/A') . '</div>
                                        </td>
                                        <td valign="top" style="width:48%;padding:0 0 36px;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">
                                                <tr>
                                                    <td valign="top" style="width:42%;padding:0 18px 0 0;">
                                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">
                                                            <tr>
                                                                <td style="padding:0 0 14px;">
                                                                    <div style="font-size:14px;line-height:1.35;font-weight:700;color:#1f2937;">Invoice Date</div>
                                                                    <div style="padding-top:2px;font-size:14px;line-height:1.45;color:#1f2937;">' . htmlspecialchars($invoiceDateDisplay) . '</div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td style="padding:0 0 14px;">
                                                                    <div style="font-size:14px;line-height:1.35;font-weight:700;color:#1f2937;">Invoice Number</div>
                                                                    <div style="padding-top:2px;font-size:14px;line-height:1.45;color:#1f2937;">' . htmlspecialchars($receiptNo) . '</div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td style="padding:0;">
                                                                    <div style="font-size:14px;line-height:1.35;font-weight:700;color:#1f2937;">Reference</div>
                                                                    <div style="padding-top:2px;font-size:14px;line-height:1.45;color:#1f2937;">' . nl2br(htmlspecialchars($referenceDisplay)) . '</div>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                    <td valign="top" style="width:58%;padding:0;">
                                                        <table role="presentation" align="right" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                                            <tr>
                                                                <td align="right" style="padding:0 0 8px;">' . $logoInvoiceMarkup . '</td>
                                                            </tr>
                                                            ' . $schoolAddressHtml . '
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" style="padding:0;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">
                                                <thead>
                                                    <tr>
                                                        <th style="padding:0 10px 12px 0;text-align:left;border-bottom:1px solid #1f2937;color:#344054;font-size:13px;font-weight:400;">Description</th>
                                                        <th style="padding:0 10px 12px;text-align:center;border-bottom:1px solid #1f2937;color:#344054;font-size:13px;font-weight:400;">Quantity</th>
                                                        <th style="padding:0 10px 12px;text-align:right;border-bottom:1px solid #1f2937;color:#344054;font-size:13px;font-weight:400;">Unit Price</th>
                                                        <th style="padding:0 10px 12px;text-align:right;border-bottom:1px solid #1f2937;color:#344054;font-size:13px;font-weight:400;">Discount</th>
                                                        <th style="padding:0 10px 12px;text-align:center;border-bottom:1px solid #1f2937;color:#344054;font-size:13px;font-weight:400;">Tax</th>
                                                        <th style="padding:0 0 12px 10px;text-align:right;border-bottom:1px solid #1f2937;color:#344054;font-size:13px;font-weight:400;">Amount PHP</th>
                                                    </tr>
                                                </thead>
                                                <tbody>' . $emailItemsHtml . '</tbody>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" style="padding:14px 0 0;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">
                                                <tr>
                                                    <td style="width:64%;">&nbsp;</td>
                                                    <td style="width:36%;padding:0;">
                                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">
                                                            <tr>
                                                                <td style="padding:0 0 12px;font-size:14px;line-height:1.45;color:#475467;text-align:right;">Subtotal</td>
                                                                <td style="padding:0 0 12px 18px;font-size:14px;line-height:1.45;color:#22374f;text-align:right;white-space:nowrap;">' . htmlspecialchars($invoiceAmountDisplay) . '</td>
                                                            </tr>
                                                            <tr>
                                                                <td style="padding:12px 0 12px;border-top:1px solid #1f2937;font-size:13px;line-height:1.45;font-weight:700;color:#1f2937;text-align:right;">TOTAL PHP</td>
                                                                <td style="padding:12px 0 12px 18px;border-top:1px solid #1f2937;font-size:14px;line-height:1.45;color:#22374f;text-align:right;white-space:nowrap;">' . htmlspecialchars($cumulativePaidDisplay) . '</td>
                                                            </tr>
                                                            <tr>
                                                                <td style="padding:0 0 12px;font-size:13px;line-height:1.45;font-weight:700;color:#1f2937;text-align:right;">Less Amount Paid</td>
                                                                <td style="padding:0 0 12px 18px;font-size:14px;line-height:1.45;color:#22374f;text-align:right;white-space:nowrap;">' . htmlspecialchars($invoiceAmountDisplay) . '</td>
                                                            </tr>
                                                            <tr>
                                                                <td style="padding:12px 0 0;border-top:1px solid #1f2937;font-size:18px;line-height:1.45;font-weight:700;color:#1f2937;text-align:right;">AMOUNT DUE PHP</td>
                                                                <td style="padding:12px 0 0 18px;border-top:1px solid #1f2937;font-size:18px;line-height:1.45;font-weight:700;color:#1f2937;text-align:right;white-space:nowrap;">' . htmlspecialchars($remainingBalanceDisplay) . '</td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>'
                                    . $paymentNoteHtml . '
                                    <tr>
                                        <td colspan="2" style="padding:52px 0 0;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">
                                                <tr>
                                                    <td style="padding:0 0 10px;font-size:18px;line-height:1.45;font-weight:700;color:#1f2937;">Due Date: ' . htmlspecialchars($dueDateDisplay) . '</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding:0 0 14px;font-size:16px;line-height:1.45;color:#344054;">Payment Details:</td>
                                                </tr>
                                                ' . $paymentDetailsHtml . '
                                                <tr>
                                                    <td style="padding:6px 0 0;font-size:13px;line-height:1.55;color:#344054;word-break:break-word;">
                                                        View online: <a href="' . htmlspecialchars($viewLink) . '" style="color:#1d4ed8;text-decoration:underline;">' . htmlspecialchars($viewLink) . '</a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" style="padding:28px 0 0;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">
                                                <tr>
                                                    <td style="padding-top:12px;border-top:1px solid #d8dde6;font-size:12px;line-height:1.5;color:#475467;text-align:center;">' . htmlspecialchars($registeredOfficeLine) . '</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    $html = $emailLayout === 'invoice' ? $invoiceHtml : $summaryHtml;

    $summaryText = implode("\r\n", [
        'Tuition Invoice',
        '',
        'Adreo Montessori Inc.',
        'Invoice #: ' . $receiptNo,
        'Due Date: ' . $dueDateDisplay,
        'Invoice Amount: ' . format_money($invoiceAmount),
        'View Invoice: ' . $viewLink,
        '',
        'Hi,',
        'Here\'s invoice ' . $receiptNo . ' for ' . format_money($invoiceAmount) . '.',
        'The amount outstanding of ' . format_money($invoiceAmount) . ' is due on ' . $dueDateDisplay . '.',
        '',
        'Description / Amount:',
        render_receipt_items_text($items),
        '',
        'If you have any questions, please let us know.',
        '',
        'Thanks,',
        'Adreo Montessori Inc.',
    ]);

    $invoiceText = implode("\r\n", [
        'Tuition Invoice',
        '',
        'Adreo Montessori Inc.',
        'Invoice #: ' . $receiptNo,
        'Reference: ' . $referenceDisplay,
        'Due Date: ' . $dueDateDisplay,
        'Invoice Amount: ' . format_money($invoiceAmount),
        'Amount Due: ' . format_money($remainingBalance),
        'View Invoice: ' . $viewLink,
        '',
        'Student ID: ' . ($student['student_id'] ?? ''),
        'Student Name: ' . $studentName,
        'Grade Level: ' . ($gradeLevel !== '' ? $gradeLevel : 'N/A'),
        'Invoice Date: ' . $invoiceDateDisplay,
        'Total Breakdown: ' . format_money($invoiceAmount),
        'Total Paid: ' . format_money($cumulativePaid),
        'Remaining Balance: ' . format_money($remainingBalance),
        '',
        'Description / Amount:',
        render_receipt_items_text($items),
        '',
        'Payment Details:',
        'ADREO XEVERA - BPI - 0121-0022-01',
        'ADREO ANGELES - Security Bank - 0000073919401',
        'ADREO CAMACHILES - PNB - 203570004892',
        '',
        get_registered_office_line(),
    ]);

    $text = $emailLayout === 'invoice' ? $invoiceText : $summaryText;

    return smtp_send_mail($to, $subject, $html, $text, $lastEmailError, $embeddedImages);
}

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');

    $conn->query(
        "CREATE TABLE IF NOT EXISTS tuition_payments (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            enrollment_id INT NOT NULL,
            student_id VARCHAR(100) NOT NULL,
            email VARCHAR(255) DEFAULT '',
            school_year VARCHAR(100) DEFAULT '',
            grade_level VARCHAR(100) DEFAULT '',
            payment_date DATE NOT NULL,
            amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            tuition_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            balance_after DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            receipt_no VARCHAR(100) DEFAULT '',
            payment_note VARCHAR(255) DEFAULT '',
            payment_items LONGTEXT DEFAULT NULL,
            payment_token VARCHAR(64) DEFAULT '',
            email_sent TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_payment_token (payment_token),
            KEY idx_student_id (student_id),
            KEY idx_enrollment_id (enrollment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $columnCheck = $conn->query("SHOW COLUMNS FROM tuition_payments LIKE 'payment_items'");
    if ($columnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE tuition_payments ADD COLUMN payment_items LONGTEXT DEFAULT NULL AFTER payment_note");
    }
    $columnCheck->close();

    $paymentTokenCheck = $conn->query("SHOW COLUMNS FROM tuition_payments LIKE 'payment_token'");
    if ($paymentTokenCheck->num_rows === 0) {
        $conn->query("ALTER TABLE tuition_payments ADD COLUMN payment_token VARCHAR(64) DEFAULT '' AFTER payment_items");
        $conn->query("ALTER TABLE tuition_payments ADD UNIQUE KEY uniq_payment_token (payment_token)");
    }
    $paymentTokenCheck->close();

    $emptyTokenResult = $conn->query("SELECT id FROM tuition_payments WHERE payment_token = '' OR payment_token IS NULL");
    if ($emptyTokenResult) {
        while ($tokenRow = $emptyTokenResult->fetch_assoc()) {
            $generatedToken = generate_payment_token();
            $tokenStmt = $conn->prepare("UPDATE tuition_payments SET payment_token = ? WHERE id = ?");
            $tokenStmt->bind_param('si', $generatedToken, $tokenRow['id']);
            $tokenStmt->execute();
            $tokenStmt->close();
        }
        $emptyTokenResult->close();
    }

    backfill_tuition_balances($conn);
    ensure_audit_log_table($conn);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = trim((string)($_POST['csrf_token'] ?? ''));
        if (!smartenroll_verify_csrf($csrfToken, 'tuition_receipt_details_form')) {
            throw new RuntimeException('Session verification failed. Please refresh and try again.');
        }

        $action = trim((string)($_POST['action'] ?? 'save_payment'));
        if ($selectedId === '') {
            throw new RuntimeException('Please select a student first.');
        }

        $selectedStudent = get_student($conn, $selectedId);
        if (!$selectedStudent) {
            throw new RuntimeException('The selected student could not be found.');
        }

        if ($action === 'save_payment') {
            $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
            if (!smartenroll_consume_one_time_token('tuition_save_payment', $idempotencyKey)) {
                throw new RuntimeException('Duplicate or expired submission detected. Please refresh and try again.');
            }

            $submitMode = trim((string)($_POST['submit_mode'] ?? 'save'));
            $paymentDate = trim((string)($_POST['payment_date'] ?? date('Y-m-d')));
            $receiptNo = strtoupper(trim((string)($_POST['receipt_no'] ?? '')));
            $paymentNote = trim((string)($_POST['payment_note'] ?? ''));
            $gradeLevel = trim((string)($selectedStudent['grade_level'] ?? ''));
            $selectedSchoolYear = trim((string)($selectedStudent['school_year'] ?? ''));
            $resolvedGrade = smartenroll_find_grade_level($gradeLevel, $conn);

            if ($resolvedGrade === null) {
                throw new RuntimeException('No tuition fee is configured yet for this grade level.');
            }

            $tuitionFee = round((float)($resolvedGrade['tuition_fee'] ?? 0), 2);
            $paidByOption = get_paid_amounts_by_option($conn, (int)$selectedStudent['id'], $selectedSchoolYear);
            $gradeFeeDefaults = smartenroll_resolve_grade_breakdown($gradeLevel, $conn);
            if ($gradeFeeDefaults === []) {
                $gradeFeeDefaults = ['Tuition Fee' => $tuitionFee];
            }
            $fixedFeeTotal = 0.0;
            foreach ($gradeFeeDefaults as $option => $defaultAmount) {
                if ($option !== 'Tuition Fee') {
                    $fixedFeeTotal += (float)$defaultAmount;
                }
            }

            $fullTuitionAmount = max(0, round($tuitionFee - $fixedFeeTotal, 2));
            $paymentConfig = $gradeFeeDefaults;
            $paymentConfig['Tuition Fee'] = $tuitionFee;

            $registrationKey = 'Registration Fee & Miscellaneous';
            if (array_key_exists($registrationKey, $paymentConfig)) {
                $catalogWithMonthly = [];
                foreach ($paymentConfig as $option => $amount) {
                    $catalogWithMonthly[$option] = $amount;
                    if ($option === $registrationKey) {
                        $catalogWithMonthly['Monthly Payment'] = round($fullTuitionAmount / 10, 2);
                    }
                }
                $paymentConfig = $catalogWithMonthly;
            } else {
                $paymentConfig['Monthly Payment'] = round($fullTuitionAmount / 10, 2);
            }

            foreach (array_keys($paymentConfig) as $option) {
                if ($option === 'Tuition Fee' || $option === 'Monthly Payment') {
                    continue;
                }

                $requiredAmount = round((float)($paymentConfig[$option] ?? 0), 2);
                $alreadyPaid = round((float)($paidByOption[$option] ?? 0), 2);
                if ($requiredAmount > 0 && $alreadyPaid + 0.01 >= $requiredAmount) {
                    unset($paymentConfig[$option]);
                }
            }

            $paymentOptions = array_keys($paymentConfig);
            $rawPaymentItemsJson = $submitMode === 'preview_send'
                ? trim((string)($_POST['preview_email_items_json'] ?? ''))
                : trim((string)($_POST['payment_items_json'] ?? ''));
            if ($rawPaymentItemsJson === '') {
                $rawPaymentItemsJson = trim((string)($_POST['payment_items_json'] ?? ''));
            }
            $paymentItems = parse_payment_items(
                $rawPaymentItemsJson,
                $paymentOptions,
                $paymentConfig,
                $tuitionFee
            );

            $selectedPaymentOptions = array_map(
                static fn(array $item): string => trim((string)($item['option'] ?? '')),
                $paymentItems
            );
            $hasTuitionFee = in_array('Tuition Fee', $selectedPaymentOptions, true);
            $hasMonthlyPayment = in_array('Monthly Payment', $selectedPaymentOptions, true);
            if ($hasTuitionFee && $hasMonthlyPayment) {
                throw new RuntimeException('Choose either Tuition Fee or Monthly Payment only, not both.');
            }

            $paymentDateTime = DateTime::createFromFormat('Y-m-d', $paymentDate);
            if (!$paymentDateTime || $paymentDateTime->format('Y-m-d') !== $paymentDate) {
                throw new RuntimeException('Please enter a valid payment date.');
            }

            $amountPaid = get_payment_total($paymentItems);
            if ($amountPaid <= 0) {
                throw new RuntimeException('Please select at least one billing item.');
            }

            $alreadyPaid = get_school_year_paid_total($conn, (int)$selectedStudent['id'], $selectedSchoolYear);
            $remainingBefore = max(0, round($tuitionFee - $alreadyPaid, 2));

            if ($remainingBefore <= 0) {
                throw new RuntimeException('Tuition is already fully paid for this school year.');
            }

            if ($amountPaid > $remainingBefore) {
                throw new RuntimeException('The entered amount exceeds the remaining balance of ' . format_money($remainingBefore) . '.');
            }

            $runningPaid = round($alreadyPaid + $amountPaid, 2);
            $balanceAfter = max(0, round($tuitionFee - $runningPaid, 2));
            $emailValue = trim((string)($selectedStudent['email'] ?? ''));
            $paymentItemsJson = json_encode($paymentItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($receiptNo === '' || !preg_match('/^INV-\d{4}$/', $receiptNo)) {
                $receiptNo = generate_unique_invoice_number($conn);
            }

            if ($submitMode === 'preview_send') {
                if (invoice_number_exists($conn, $receiptNo)) {
                    $receiptNo = generate_unique_invoice_number($conn);
                }

                if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['pay_tuition_warning'] = 'The student has no valid email in the enrollment form, so the invoice could not be sent.';
                    header('Location: tuition_receipt_details.php?student_id=' . urlencode($selectedId) . '#receipt-email-preview');
                    exit;
                }

                $previewPayment = [
                    'id' => 0,
                    'student_id' => $selectedStudent['student_id'],
                    'payment_date' => $paymentDate,
                    'amount_paid' => $amountPaid,
                    'tuition_fee' => $tuitionFee,
                    'balance_after' => $remainingBefore,
                    'receipt_no' => $receiptNo,
                    'payment_note' => $paymentNote,
                    'payment_items' => $paymentItemsJson,
                    'email_sent' => 0,
                ];

                $sent = send_receipt_email($selectedStudent, $previewPayment, 'summary');
                if ($sent) {
                    write_audit_log($conn, $currentUser, 'tuition_invoice_preview_emailed', 'tuition_invoice_preview', $receiptNo, [
                        'student_id' => (string)$selectedStudent['student_id'],
                        'email' => $emailValue,
                        'payment_date' => $paymentDate,
                        'amount' => $amountPaid,
                        'items' => $paymentItems,
                    ]);

                    $_SESSION['pay_tuition_success'] = 'Invoice preview sent to ' . $emailValue . '. It was not saved as a paid invoice.';
                } else {
                    $_SESSION['pay_tuition_warning'] = $lastEmailError !== ''
                        ? 'The invoice preview could not be sent: ' . $lastEmailError
                        : 'The invoice preview could not be sent.';
                }

                header('Location: tuition_receipt_details.php?student_id=' . urlencode($selectedId) . '#receipt-email-preview');
                exit;
            }

            $paymentToken = generate_payment_token();
            $conn->begin_transaction();
            try {
                if (invoice_number_exists($conn, $receiptNo)) {
                    $receiptNo = generate_unique_invoice_number($conn);
                }

                $insertStmt = $conn->prepare(
                    "INSERT INTO tuition_payments (
                        enrollment_id, student_id, email, school_year, grade_level, payment_date,
                        amount_paid, tuition_fee, balance_after, receipt_no, payment_note, payment_items, payment_token, email_sent
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
                );
                $insertStmt->bind_param(
                    'isssssdddssss',
                    $selectedStudent['id'],
                    $selectedStudent['student_id'],
                    $emailValue,
                    $selectedStudent['school_year'],
                    $selectedStudent['grade_level'],
                    $paymentDate,
                    $amountPaid,
                    $tuitionFee,
                    $balanceAfter,
                    $receiptNo,
                    $paymentNote,
                    $paymentItemsJson,
                    $paymentToken
                );
                $insertStmt->execute();
                $newPaymentId = (int)$insertStmt->insert_id;
                $insertStmt->close();

                write_audit_log($conn, $currentUser, 'tuition_payment_saved', 'tuition_payment', (string)$newPaymentId, [
                    'student_id' => (string)$selectedStudent['student_id'],
                    'school_year' => (string)$selectedSchoolYear,
                    'amount_paid' => $amountPaid,
                    'balance_after' => $balanceAfter,
                    'receipt_no' => $receiptNo,
                    'items' => $paymentItems,
                ]);

                $conn->commit();
            } catch (Throwable $txError) {
                $conn->rollback();
                throw $txError;
            }

            $_SESSION['pay_tuition_success'] = 'Invoice created. You can now send it to ' . ($emailValue !== '' ? $emailValue : 'the registered enrollment email') . '.';
            if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['pay_tuition_warning'] = 'The student has no valid email in the enrollment form, so the invoice cannot be sent yet.';
            }

            header('Location: tuition_receipt_details.php?student_id=' . urlencode($selectedId) . '&payment_id=' . $newPaymentId);
            exit;
        }

        if ($action === 'send_receipt') {
            $paymentId = (int)($_POST['payment_id'] ?? 0);
            if ($paymentId <= 0) {
                throw new RuntimeException('No saved invoice was selected.');
            }

            $paymentStmt = $conn->prepare(
                "SELECT id, student_id, payment_date, amount_paid, tuition_fee, balance_after, receipt_no, payment_note, payment_items, payment_token, email_sent
                 FROM tuition_payments
                 WHERE id = ? AND student_id = ?
                 LIMIT 1"
            );
            $paymentStmt->bind_param('is', $paymentId, $selectedId);
            $paymentStmt->execute();
            $payment = $paymentStmt->get_result()->fetch_assoc();
            $paymentStmt->close();

            if (!$payment) {
                throw new RuntimeException('The selected invoice could not be found.');
            }

            $studentEmail = trim((string)($selectedStudent['email'] ?? ''));
            if (!filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('This student does not have a valid email saved in the enrollment form.');
            }

            $sent = send_receipt_email($selectedStudent, $payment, 'invoice');
            if (!$sent) {
                throw new RuntimeException($lastEmailError ?: 'The invoice email could not be sent from this server.');
            }

            $updateStmt = $conn->prepare("UPDATE tuition_payments SET email_sent = 1, email = ? WHERE id = ?");
            $updateStmt->bind_param('si', $studentEmail, $paymentId);
            $updateStmt->execute();
            $updateStmt->close();

            write_audit_log($conn, $currentUser, 'tuition_receipt_emailed', 'tuition_payment', (string)$paymentId, [
                'student_id' => (string)$selectedStudent['student_id'],
                'email' => $studentEmail,
            ]);

            $_SESSION['pay_tuition_success'] = 'Invoice sent to ' . $studentEmail . '.';
            header('Location: tuition_receipt_details.php?student_id=' . urlencode($selectedId) . '&payment_id=' . $paymentId);
            exit;
        }
    }

    $selectedPaymentId = (int)($_GET['payment_id'] ?? 0);
    if ($selectedId === '') {
        throw new RuntimeException('Please choose a student from the student list first.');
    }

    $selectedStudent = get_student($conn, $selectedId);
    if (!$selectedStudent) {
        throw new RuntimeException('The selected student could not be found.');
    }

    $historyStmt = $conn->prepare(
        "SELECT id, payment_date, amount_paid, tuition_fee, balance_after, receipt_no, payment_note, payment_items, payment_token, email_sent, created_at
         FROM tuition_payments
         WHERE enrollment_id = ?
           AND COALESCE(school_year, '') = ?
         ORDER BY payment_date DESC, id DESC"
    );
    $selectedSchoolYear = trim((string)($selectedStudent['school_year'] ?? ''));
    $selectedEnrollmentId = (int)($selectedStudent['id'] ?? 0);
    $historyStmt->bind_param('is', $selectedEnrollmentId, $selectedSchoolYear);
    $historyStmt->execute();
    $historyResult = $historyStmt->get_result();
    while ($historyRow = $historyResult->fetch_assoc()) {
        $historyRow['items'] = decode_saved_payment_items((string)($historyRow['payment_items'] ?? ''), (float)($historyRow['amount_paid'] ?? 0));
        $paymentHistory[] = $historyRow;
        if (($selectedPaymentId > 0 && (int)$historyRow['id'] === $selectedPaymentId) || ($selectedPaymentId <= 0 && $selectedPayment === null)) {
            $selectedPayment = $historyRow;
        }
    }
    $historyStmt->close();

    $gmailHistoryActionPreview = 'tuition_invoice_preview_emailed';
    $gmailHistoryActionInvoice = 'tuition_receipt_emailed';
    $gmailHistoryStudentPattern = '%"student_id":"' . (string)$selectedStudent['student_id'] . '"%';
    $gmailHistoryStmt = $conn->prepare(
        "SELECT
            al.id,
            al.action,
            al.entity_id,
            al.details_json,
            al.created_at,
            tp.id AS linked_payment_id,
            tp.receipt_no AS linked_receipt_no,
            tp.payment_date AS linked_payment_date,
            tp.amount_paid AS linked_amount_paid,
            tp.payment_items AS linked_payment_items
         FROM audit_logs al
         LEFT JOIN tuition_payments tp
           ON al.action = 'tuition_receipt_emailed'
          AND tp.id = CAST(al.entity_id AS UNSIGNED)
         WHERE al.action IN (?, ?)
           AND al.details_json LIKE ?
         ORDER BY al.created_at DESC, al.id DESC"
    );
    $gmailHistoryStmt->bind_param('sss', $gmailHistoryActionPreview, $gmailHistoryActionInvoice, $gmailHistoryStudentPattern);
    $gmailHistoryStmt->execute();
    $gmailHistoryResult = $gmailHistoryStmt->get_result();

    while ($gmailHistoryRow = $gmailHistoryResult->fetch_assoc()) {
        $gmailHistoryDetails = json_decode((string)($gmailHistoryRow['details_json'] ?? ''), true);
        if (!is_array($gmailHistoryDetails)) {
            $gmailHistoryDetails = [];
        }

        $gmailHistoryAction = (string)($gmailHistoryRow['action'] ?? '');
        $gmailHistoryInvoiceNo = 'N/A';
        $gmailHistoryAmount = 0.0;
        $gmailHistoryItems = 'N/A';
        $gmailHistoryLink = '';
        $gmailHistoryPaymentDate = '';
        $gmailHistoryItemsData = [];

        if ($gmailHistoryAction === $gmailHistoryActionPreview) {
            $gmailHistoryItemsData = is_array($gmailHistoryDetails['items'] ?? null) ? $gmailHistoryDetails['items'] : [];
            $gmailHistoryInvoiceNo = trim((string)($gmailHistoryRow['entity_id'] ?? '')) ?: 'N/A';
            $gmailHistoryAmount = round((float)($gmailHistoryDetails['amount'] ?? 0), 2);
            $gmailHistoryItems = build_invoice_item_list(
                $selectedStudent,
                $gmailHistoryItemsData,
                'No billing item added yet'
            );
            $gmailHistoryPaymentDate = trim((string)($gmailHistoryDetails['payment_date'] ?? ''));
            if ($gmailHistoryPaymentDate === '') {
                $gmailHistoryPaymentDate = substr((string)($gmailHistoryRow['created_at'] ?? ''), 0, 10);
            }
            $gmailHistoryLink = 'tuition_receipt_details.php?student_id=' . urlencode((string)$selectedStudent['student_id']) . '#receipt-email-preview';
        } else {
            $linkedPaymentId = (int)($gmailHistoryRow['linked_payment_id'] ?? 0);
            if ($linkedPaymentId > 0) {
                $gmailHistoryItemsData = decode_saved_payment_items(
                    (string)($gmailHistoryRow['linked_payment_items'] ?? ''),
                    (float)($gmailHistoryRow['linked_amount_paid'] ?? 0)
                );
                $gmailHistoryInvoiceNo = trim((string)($gmailHistoryRow['linked_receipt_no'] ?? '')) ?: 'N/A';
                $gmailHistoryPaymentDate = trim((string)($gmailHistoryRow['linked_payment_date'] ?? ''));
                $gmailHistoryAmount = round((float)($gmailHistoryRow['linked_amount_paid'] ?? 0), 2);
                $gmailHistoryItems = build_invoice_item_list(
                    $selectedStudent,
                    $gmailHistoryItemsData
                );
                $gmailHistoryLink = 'tuition_receipt_details.php?student_id=' . urlencode((string)$selectedStudent['student_id']) . '&payment_id=' . $linkedPaymentId . '#receipt-preview';
            } else {
                $gmailHistoryInvoiceNo = 'Saved Invoice';
                $gmailHistoryItems = 'Saved invoice';
            }
        }

        $gmailSendHistory[] = [
            'sent_at' => (string)($gmailHistoryRow['created_at'] ?? ''),
            'type' => $gmailHistoryAction === $gmailHistoryActionPreview ? 'Preview Email' : 'Invoice Email',
            'invoice_no' => $gmailHistoryInvoiceNo,
            'payment_items' => $gmailHistoryItems,
            'amount' => $gmailHistoryAmount,
            'email' => trim((string)($gmailHistoryDetails['email'] ?? '')) ?: 'N/A',
            'link' => $gmailHistoryLink,
            'fill_payload' => encode_history_fill_payload(
                $selectedStudent,
                $gmailHistoryPaymentDate,
                $gmailHistoryInvoiceNo !== 'N/A' && $gmailHistoryInvoiceNo !== 'Saved Invoice' ? $gmailHistoryInvoiceNo : '',
                $gmailHistoryItemsData,
                $gmailHistoryAmount
            ),
        ];
    }
    $gmailHistoryStmt->close();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$selectedGradeLevel = $selectedStudent['grade_level'] ?? '';
$selectedTuitionFee = $selectedStudent
    ? (smartenroll_resolve_grade_tuition_fee($selectedGradeLevel, isset($conn) && $conn instanceof mysqli ? $conn : null) ?? 0.0)
    : 0.0;
$gradeFeeDefaults = $selectedStudent
    ? smartenroll_resolve_grade_breakdown($selectedGradeLevel, isset($conn) && $conn instanceof mysqli ? $conn : null)
    : [];
if ($selectedStudent && $gradeFeeDefaults === []) {
    $gradeFeeDefaults = ['Tuition Fee' => $selectedTuitionFee];
}
$fixedFeeTotal = 0.0;
foreach ($gradeFeeDefaults as $option => $defaultAmount) {
    if ($option !== 'Tuition Fee') {
        $fixedFeeTotal += (float)$defaultAmount;
    }
}
$fullTuitionAmount = max(0, round($selectedTuitionFee - $fixedFeeTotal, 2));
$monthlyPaymentAmount = max(0, round($fullTuitionAmount / 10, 2));
$savedReceiptCount = count($paymentHistory);
$totalPaid = sum_payment_history($paymentHistory);
$remainingBalance = max(0, round($selectedTuitionFee - $totalPaid, 2));
$paidByOption = get_paid_amounts_from_history($paymentHistory);
$studentName = $selectedStudent ? format_name($selectedStudent) : '';
$studentEmail = trim((string)($selectedStudent['email'] ?? ''));

$tuitionPayNowAmount = max(0, round($remainingBalance, 2));
$monthlyPaymentDisplayAmount = max(0, round(min($monthlyPaymentAmount, $remainingBalance), 2));

$paymentCatalogConfig = $gradeFeeDefaults;
$paymentCatalogConfig['Tuition Fee'] = $tuitionPayNowAmount;

$registrationKey = 'Registration Fee & Miscellaneous';
if (array_key_exists($registrationKey, $paymentCatalogConfig)) {
    $catalogWithMonthly = [];
    foreach ($paymentCatalogConfig as $option => $amount) {
        $catalogWithMonthly[$option] = $amount;
        if ($option === $registrationKey) {
            $catalogWithMonthly['Monthly Payment'] = $monthlyPaymentDisplayAmount;
        }
    }
    $paymentCatalogConfig = $catalogWithMonthly;
} else {
    $paymentCatalogConfig['Monthly Payment'] = $monthlyPaymentDisplayAmount;
}

$paymentOptions = array_keys($paymentCatalogConfig);
$paymentCatalog = [];
foreach ($paymentOptions as $option) {
    $defaultAmount = (float)($paymentCatalogConfig[$option] ?? 0);
    $disabled = false;

    if ($option === 'Tuition Fee' || $option === 'Monthly Payment') {
        $disabled = $remainingBalance <= 0;
    } else {
        $requiredAmount = round((float)($gradeFeeDefaults[$option] ?? $defaultAmount), 2);
        $alreadyPaid = round((float)($paidByOption[$option] ?? 0), 2);
        $disabled = $requiredAmount > 0 && $alreadyPaid + 0.01 >= $requiredAmount;
    }

    if ($option === 'Tuition Fee') {
        $hint = $disabled
            ? 'Tuition is already fully paid for this school year.'
            : 'Shows the current remaining balance. Use this to settle the full unpaid amount now.';
    } elseif ($option === 'Monthly Payment') {
        $hint = $disabled
            ? 'Tuition is already fully paid for this school year.'
            : 'This uses the brochure monthly tuition amount, capped by the current remaining balance.';
    } else {
        $hint = $disabled
            ? 'This item is already fully paid.'
            : 'This uses the fixed amount from the grade-level brochure you provided.';
    }
    $paymentCatalog[] = [
        'option' => $option,
        'default_amount' => round($defaultAmount, 2),
        'disabled' => $disabled,
        'hint' => $hint,
    ];
}
$hasEnabledPaymentOption = false;
foreach ($paymentCatalog as $catalogItem) {
    if (empty($catalogItem['disabled'])) {
        $hasEnabledPaymentOption = true;
        break;
    }
}

$paymentCatalogJson = htmlspecialchars(json_encode($paymentCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$tuitionReceiptCsrfToken = smartenroll_csrf_token('tuition_receipt_details_form');
$tuitionSaveIdempotencyKey = smartenroll_issue_one_time_token('tuition_save_payment');
$emailConfig = get_email_config();
$configuredAppUrl = trim((string)($emailConfig['app_url'] ?? ''));
$uploadLinkNeedsRealHost = $configuredAppUrl === '' || is_loopback_host((string)parse_url($configuredAppUrl, PHP_URL_HOST));
$schoolAddressLines = get_school_address_lines();
$paymentDetailBlocks = get_payment_detail_blocks();
$suggestedInvoiceNumber = '';
$selectedReceiptNo = '';
$selectedPaymentItems = [];
$selectedPaymentRemainingBalance = 0.0;
$selectedPaymentCumulativePaid = 0.0;
$selectedPaymentAmount = 0.0;
$selectedPaymentDateDisplay = 'N/A';
$selectedPaymentNote = '';
$selectedPaymentDueDateDisplay = 'N/A';
$registeredOfficeLine = get_registered_office_line();

if ($selectedStudent && $selectedPayment) {
    $selectedReceiptNo = trim((string)($selectedPayment['receipt_no'] ?? '')) !== '' ? (string)$selectedPayment['receipt_no'] : 'N/A';
    $selectedPaymentItems = is_array($selectedPayment['items'] ?? null)
        ? $selectedPayment['items']
        : decode_saved_payment_items((string)($selectedPayment['payment_items'] ?? ''), (float)($selectedPayment['amount_paid'] ?? 0));
    $selectedPaymentRemainingBalance = resolve_payment_balance_after($selectedPayment);
    $selectedPaymentCumulativePaid = get_payment_cumulative_paid($selectedPayment);
    $selectedPaymentAmount = round((float)($selectedPayment['amount_paid'] ?? 0), 2);
    $selectedPaymentDateDisplay = format_invoice_date((string)($selectedPayment['payment_date'] ?? ''));
    $selectedPaymentDueDateDisplay = $selectedPaymentDateDisplay;
    $selectedPaymentNote = trim((string)($selectedPayment['payment_note'] ?? ''));
}

if (isset($conn) && $conn instanceof mysqli) {
    $suggestedInvoiceNumber = generate_unique_invoice_number($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMARTENROLL | Tuition Invoice Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/pay_tuition.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page dashboard-white-page receipt-details-page">
<main class="dashboard-main pay-list-main receipt-details-main">
    <div class="dashboard-header tuition-header">
        <div class="student-header-left">
            <a href="tuition_receipt.php" class="dashboard-link back-left"><i class="fa-solid fa-arrow-left"></i></a>
            <div class="student-header-title">
                <h1>Tuition Invoice Details</h1>
                <p>Use the plus button to add the brochure breakdown items, then type the tuition amount and review the computed invoice total below.</p>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="pay-alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($successMessage): ?>
        <div class="pay-alert success"><?php echo htmlspecialchars($successMessage); ?></div>
    <?php endif; ?>
    <?php if ($warningMessage): ?>
        <div class="pay-alert info"><?php echo htmlspecialchars($warningMessage); ?></div>
    <?php endif; ?>
    <?php if ($uploadLinkNeedsRealHost): ?>
        <div class="pay-alert info">Set a real network or public URL in the mail config `app_url` value. Links using `localhost` can only be opened on this computer.</div>
    <?php endif; ?>

    <?php if ($selectedStudent): ?>
        <div class="receipt-workspace">
        <div class="selected-student-banner detail-student-banner">
            <div class="selected-student-copy">
                <span class="eyebrow">Selected Student</span>
                <h2><?php echo htmlspecialchars($studentName); ?></h2>
                <p>Grade Level: <?php echo htmlspecialchars((string)$selectedStudent['grade_level']); ?></p>
            </div>
            <div class="student-identity-grid">
                <div class="selected-student-email">
                    <span>School ID</span>
                    <strong><?php echo htmlspecialchars((string)$selectedStudent['student_id']); ?></strong>
                </div>
                <div class="selected-student-email">
                    <span>School Year</span>
                    <strong><?php echo htmlspecialchars((string)($selectedStudent['school_year'] ?: 'N/A')); ?></strong>
                </div>
                <div class="selected-student-email">
                    <span>Enrollment Email</span>
                    <strong><?php echo htmlspecialchars($studentEmail !== '' ? $studentEmail : 'No email saved on enrollment form'); ?></strong>
                </div>
            </div>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <span>Annual Program Total</span>
                <strong><?php echo htmlspecialchars(format_money($selectedTuitionFee)); ?></strong>
            </div>
            <div class="summary-card">
                <span>Saved Invoices</span>
                <strong><?php echo htmlspecialchars((string)$savedReceiptCount); ?></strong>
            </div>
            <div class="summary-card accent">
                <span>Remaining Balance</span>
                <strong id="remainingBalanceDisplay"><?php echo htmlspecialchars(format_money($remainingBalance)); ?></strong>
            </div>
            <div class="summary-card">
                <span>Email Status</span>
                <strong><?php echo filter_var($studentEmail, FILTER_VALIDATE_EMAIL) ? 'Ready to send invoice' : 'Invalid student email'; ?></strong>
            </div>
        </div>

        <?php $previewInvoiceLink = build_app_url('tuition_receipt_details.php', ['student_id' => (string)$selectedStudent['student_id']]) . '#receipt-email-preview'; ?>
        <section class="card-block invoice-email-preview-panel" id="receipt-email-preview">
            <div class="invoice-email-preview-actions">
                <button type="button" class="secondary-btn btn-sm" id="invoiceEmailPrintTrigger">
                    <i class="fa-solid fa-print"></i>
                    Print Invoice
                </button>
                <button type="button" class="primary-btn btn-sm" id="invoiceEmailSendTrigger" <?php echo filter_var($studentEmail, FILTER_VALIDATE_EMAIL) ? '' : 'disabled'; ?>>
                    <i class="fa-solid fa-paper-plane"></i>
                    Send Preview to Gmail
                </button>
                <a href="tuition_receipt_details.php?student_id=<?php echo urlencode((string)$selectedStudent['student_id']); ?>" class="primary-btn btn-sm" style="text-decoration: none;">
                    <i class="fa-solid fa-plus"></i>
                    New Transaction
                </a>
            </div>
            <div class="invoice-email-shell">
                <div class="invoice-email-brand">
                    <img src="assets/logo.png" alt="Adreo Montessori Logo">
                    <strong>Adreo Montessori Inc.</strong>
                </div>

                <div class="invoice-email-total">
                    <strong id="invoiceEmailTotal">0.00</strong>
                    <span>PHP</span>
                </div>

                <div class="invoice-email-meta">
                    <strong id="invoiceEmailDueDate">Due <?php echo htmlspecialchars(format_invoice_date(date('Y-m-d'))); ?></strong>
                    <span>Invoice #: <span id="invoiceEmailNumber"><?php echo htmlspecialchars($suggestedInvoiceNumber); ?></span></span>
                </div>

                <a class="invoice-email-cta" id="invoiceEmailCta" href="<?php echo htmlspecialchars($previewInvoiceLink); ?>">View Invoice</a>

                <div class="invoice-email-message">
                    <p>Hi,</p>
                    <p>Here&apos;s invoice <strong id="invoiceEmailBodyNumber"><?php echo htmlspecialchars($suggestedInvoiceNumber); ?></strong> for <strong id="invoiceEmailBodyAmount">PHP 0.00</strong>.</p>
                    <p>The amount outstanding of <strong id="invoiceEmailBodyOutstanding">PHP 0.00</strong> is due on <strong id="invoiceEmailBodyDueDate"><?php echo htmlspecialchars(format_invoice_date(date('Y-m-d'))); ?></strong>.</p>
                    <p>View your bill online: <a id="invoiceEmailViewLink" href="<?php echo htmlspecialchars($previewInvoiceLink); ?>"><?php echo htmlspecialchars($previewInvoiceLink); ?></a></p>
                    <p>From your online bill you can print a PDF, export a CSV, or create a free login and view your outstanding bills.</p>
                    <p>If you have any questions, please let us know.</p>
                    <p>Thanks,<br>Adreo Montessori Inc.</p>
                </div>

                <div class="invoice-email-items">
                    <div class="invoice-email-items-head">
                        <span>Description</span>
                        <span>Amount</span>
                    </div>
                    <div class="invoice-email-items-body" id="invoiceEmailItems">
                        <div class="invoice-email-line-item is-empty">
                            <div class="invoice-email-empty-action">
                                <span>No billing item added yet</span>
                                <button type="button" class="invoice-email-add-trigger" aria-label="Add payment item" aria-haspopup="true" aria-expanded="false">
                                    <i class="fa-solid fa-plus"></i>
                                    <span>Add payment item</span>
                                </button>
                                <div class="payment-catalog-card invoice-email-catalog-menu" id="invoiceEmailCatalog">
                                    <?php foreach ($paymentCatalog as $catalogItem): ?>
                                        <?php $catalogDisplayLabel = build_invoice_item_description($selectedStudent, ['label' => $catalogItem['option']]); ?>
                                        <div
                                            class="catalog-row invoice-email-catalog-row<?php echo !empty($catalogItem['disabled']) ? ' is-disabled' : ''; ?>"
                                            data-option="<?php echo htmlspecialchars($catalogItem['option']); ?>"
                                            data-display-label="<?php echo htmlspecialchars($catalogDisplayLabel); ?>"
                                            data-default="<?php echo htmlspecialchars(number_format((float)$catalogItem['default_amount'], 2, '.', '')); ?>"
                                            data-disabled="<?php echo !empty($catalogItem['disabled']) ? '1' : '0'; ?>"
                                        >
                                            <button type="button" class="catalog-add-btn" aria-label="Add <?php echo htmlspecialchars($catalogItem['option']); ?>" <?php echo !empty($catalogItem['disabled']) ? 'disabled' : ''; ?>>
                                                <i class="fa-solid <?php echo !empty($catalogItem['disabled']) ? 'fa-check' : 'fa-plus'; ?>"></i>
                                            </button>
                                            <div class="receipt-catalog-copy">
                                                <strong><?php echo htmlspecialchars($catalogDisplayLabel); ?></strong>
                                                <span><?php echo htmlspecialchars(format_invoice_money((float)$catalogItem['default_amount'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <strong>0.00</strong>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card-block history-block gmail-history-block" id="gmail-send-history">
            <div class="block-head">
                <div>
                    <span class="eyebrow eyebrow-blue">Gmail Send History</span>
                    <h3>Sent Email History</h3>
                    <p>This is a separate history for preview emails and invoice emails already sent to Gmail.</p>
                </div>
            </div>

            <div class="student-table-wrap">
                <table class="student-table history-table">
                    <thead>
                        <tr>
                            <th>Sent</th>
                            <th>Type</th>
                            <th>Invoice</th>
                            <th>Payment Items</th>
                            <th>Amount</th>
                            <th>Recipient</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($gmailSendHistory)): ?>
                            <tr>
                                <td colspan="6">No Gmail send history yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($gmailSendHistory as $gmailHistory): ?>
                                <?php $gmailHistoryFillPayload = (string)($gmailHistory['fill_payload'] ?? ''); ?>
                                <tr<?php echo $gmailHistoryFillPayload !== '' ? ' class="history-fill-row" data-history-fill="' . $gmailHistoryFillPayload . '"' : ''; ?>>
                                    <td>
                                        <?php if ($gmailHistory['link'] !== ''): ?>
                                            <a class="history-link" href="<?php echo htmlspecialchars($gmailHistory['link']); ?>">
                                                <?php echo htmlspecialchars(format_history_timestamp((string)$gmailHistory['sent_at'])); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars(format_history_timestamp((string)$gmailHistory['sent_at'])); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)$gmailHistory['type']); ?></td>
                                    <td>
                                        <?php if ($gmailHistory['link'] !== ''): ?>
                                            <a class="history-link" href="<?php echo htmlspecialchars($gmailHistory['link']); ?>">
                                                <?php echo htmlspecialchars((string)$gmailHistory['invoice_no']); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars((string)$gmailHistory['invoice_no']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)$gmailHistory['payment_items']); ?></td>
                                    <td><?php echo htmlspecialchars(format_money((float)$gmailHistory['amount'])); ?></td>
                                    <td><?php echo htmlspecialchars((string)$gmailHistory['email']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="detail-grid builder-grid">
            <section class="card-block receipt-builder-panel">
                <form class="payment-form receipt-builder-form" method="post" action="tuition_receipt_details.php" id="paymentBuilderForm">
                    <input type="hidden" name="action" value="save_payment">
                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars((string)$selectedStudent['student_id']); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($tuitionReceiptCsrfToken); ?>">
                    <input type="hidden" name="idempotency_key" value="<?php echo htmlspecialchars($tuitionSaveIdempotencyKey); ?>">
                    <input type="hidden" name="payment_items_json" id="paymentItemsJson">
                    <input type="hidden" name="preview_email_items_json" id="previewEmailItemsJson">
                    <input type="hidden" name="submit_mode" id="paymentSubmitMode" value="save">

                    <div class="receipt-builder-toolbar">
                        <div class="receipt-builder-fields">
                            <label class="receipt-inline-field">
                                <span class="sr-only">Payment Date</span>
                                <input type="date" name="payment_date" id="paymentDateInput" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" aria-label="Payment Date" required>
                            </label>
                            <label class="receipt-inline-field">
                                <span class="sr-only">Invoice No.</span>
                                <input type="text" name="receipt_no" id="receiptNumberInput" value="<?php echo htmlspecialchars($suggestedInvoiceNumber); ?>" placeholder="Invoice No." aria-label="Invoice No." readonly>
                            </label>
                        </div>
                    </div>

                    <div class="selected-payment-card receipt-lines-panel">
                        <div class="receipt-entry-table-wrap">
                            <table class="receipt-entry-table">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Discount</th>
                                        <th>Tax</th>
                                        <th>Amount PHP</th>
                                    </tr>
                                </thead>
                                <tbody
                            class="selected-payment-table receipt-entry-body"
                            id="selectedPaymentTable"
                            data-remaining="<?php echo htmlspecialchars(number_format($remainingBalance, 2, '.', '')); ?>"
                            data-full-tuition="<?php echo htmlspecialchars(number_format($selectedTuitionFee, 2, '.', '')); ?>"
                            data-paid-total="<?php echo htmlspecialchars(number_format($totalPaid, 2, '.', '')); ?>"
                                >
                                    <tr class="receipt-add-row">
                                        <td class="receipt-add-row-cell" colspan="6">
                                            <div class="receipt-add-wrap">
                                                <button
                                                    type="button"
                                                    class="receipt-add-trigger"
                                                    id="receiptAddTrigger"
                                                    aria-label="Add payment row"
                                                    aria-haspopup="true"
                                                    aria-expanded="false"
                                                    <?php echo $hasEnabledPaymentOption ? '' : 'disabled'; ?>
                                                >
                                                    <i class="fa-solid fa-plus"></i>
                                                </button>
                                                <div class="payment-catalog-card receipt-catalog-menu" id="paymentCatalog" data-catalog="<?php echo $paymentCatalogJson; ?>">
                                                    <?php foreach ($paymentCatalog as $catalogItem): ?>
                                                        <?php $catalogDisplayLabel = build_invoice_item_description($selectedStudent, ['label' => $catalogItem['option']]); ?>
                                                        <div
                                                            class="catalog-row receipt-catalog-row<?php echo !empty($catalogItem['disabled']) ? ' is-disabled' : ''; ?>"
                                                            data-option="<?php echo htmlspecialchars($catalogItem['option']); ?>"
                                                            data-display-label="<?php echo htmlspecialchars($catalogDisplayLabel); ?>"
                                                            data-default="<?php echo htmlspecialchars(number_format((float)$catalogItem['default_amount'], 2, '.', '')); ?>"
                                                            data-disabled="<?php echo !empty($catalogItem['disabled']) ? '1' : '0'; ?>"
                                                        >
                                                            <button type="button" class="catalog-add-btn" aria-label="Add <?php echo htmlspecialchars($catalogItem['option']); ?>" <?php echo !empty($catalogItem['disabled']) ? 'disabled' : ''; ?>>
                                                                <i class="fa-solid <?php echo !empty($catalogItem['disabled']) ? 'fa-check' : 'fa-plus'; ?>"></i>
                                                            </button>
                                                            <div class="receipt-catalog-copy">
                                                                <strong><?php echo htmlspecialchars($catalogDisplayLabel); ?></strong>
                                                                <span><?php echo htmlspecialchars(format_invoice_money((float)$catalogItem['default_amount'])); ?></span>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="selected-payment-empty" id="selectedPaymentEmpty">
                                        <td colspan="6"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="receipt-builder-summary">
                        <div class="receipt-builder-summary-row compact">
                            <span>Subtotal</span>
                            <strong id="paymentPreview">0.00</strong>
                        </div>
                        <div class="receipt-builder-summary-row rule-top">
                            <span>TOTAL PHP</span>
                            <strong id="totalPaidPreview">0.00</strong>
                        </div>
                        <div class="receipt-builder-summary-row">
                            <span>Less Amount Paid</span>
                            <strong id="lessAmountPaidPreview">0.00</strong>
                        </div>
                        <div class="receipt-builder-summary-row due">
                            <span>AMOUNT DUE PHP</span>
                            <strong id="balanceAfterPreview"><?php echo htmlspecialchars(format_invoice_money($remainingBalance)); ?></strong>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="primary-btn" id="saveInvoiceButton">
                            <i class="fa-solid fa-receipt"></i>
                            Save Invoice
                        </button>
                        <a class="cancel-btn" href="tuition_receipt_details.php?student_id=<?php echo urlencode((string)$selectedStudent['student_id']); ?>">
                            <i class="fa-solid fa-rotate-left"></i>
                            Clear Form
                        </a>
                    </div>
                </form>
            </section>
        </div>

        <div class="detail-grid lower-detail-grid">
            <section class="card-block history-block" id="saved-receipts">
                <div class="block-head">
                    <h3>Saved Invoices</h3>
                    <p>Each saved invoice keeps the fee breakdown and can still be sent again.</p>
                </div>

                <div class="student-table-wrap">
                    <table class="student-table history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice</th>
                                <th>Payment Items</th>
                                <th>Total Breakdown</th>
                                <th>Remaining Balance</th>
                                <th>Email Status</th>
                                <th>Send</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($paymentHistory)): ?>
                                <tr>
                                    <td colspan="7">No invoices saved yet.</td>
                                </tr>
                        <?php else: ?>
                                <?php foreach ($paymentHistory as $history): ?>
                                    <?php $isActiveReceipt = $selectedPayment && (int)$selectedPayment['id'] === (int)$history['id']; ?>
                                    <?php $historyItemNames = implode(', ', array_map(static fn($item) => (string)($item['label'] ?? ''), $history['items'])); ?>
                                    <tr<?php echo $isActiveReceipt ? ' class="active-history-row"' : ''; ?>>
                                        <td>
                                            <a class="history-link" href="tuition_receipt_details.php?student_id=<?php echo urlencode((string)$selectedStudent['student_id']); ?>&payment_id=<?php echo (int)$history['id']; ?>#receipt-preview">
                                                <?php echo htmlspecialchars((string)$history['payment_date']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a class="history-link" href="tuition_receipt_details.php?student_id=<?php echo urlencode((string)$selectedStudent['student_id']); ?>&payment_id=<?php echo (int)$history['id']; ?>#receipt-preview">
                                                <?php echo htmlspecialchars((string)($history['receipt_no'] !== '' ? $history['receipt_no'] : 'N/A')); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a class="history-link" href="tuition_receipt_details.php?student_id=<?php echo urlencode((string)$selectedStudent['student_id']); ?>&payment_id=<?php echo (int)$history['id']; ?>#receipt-preview">
                                                <?php echo htmlspecialchars($historyItemNames !== '' ? $historyItemNames : 'N/A'); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a class="history-link" href="tuition_receipt_details.php?student_id=<?php echo urlencode((string)$selectedStudent['student_id']); ?>&payment_id=<?php echo (int)$history['id']; ?>#receipt-preview">
                                                <?php echo htmlspecialchars(format_money((float)$history['amount_paid'])); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a class="history-link" href="tuition_receipt_details.php?student_id=<?php echo urlencode((string)$selectedStudent['student_id']); ?>&payment_id=<?php echo (int)$history['id']; ?>#receipt-preview">
                                                <?php echo htmlspecialchars(format_money((float)$history['balance_after'])); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="status-pill <?php echo (int)$history['email_sent'] === 1 ? 'sent' : 'pending'; ?>">
                                                <?php echo (int)$history['email_sent'] === 1 ? 'Sent' : 'Not sent'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="post" action="tuition_receipt_details.php" class="table-send-form">
                                                <input type="hidden" name="action" value="send_receipt">
                                                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars((string)$selectedStudent['student_id']); ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($tuitionReceiptCsrfToken); ?>">
                                                <input type="hidden" name="payment_id" value="<?php echo (int)$history['id']; ?>">
                                                <button type="submit" class="table-send-btn" <?php echo filter_var($studentEmail, FILTER_VALIDATE_EMAIL) ? '' : 'disabled'; ?>>
                                                    Send Invoice
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php if ($selectedPayment): ?>
                <section class="card-block invoice-preview-panel" id="receipt-preview">
                    <div class="block-head">
                        <div>
                            <span class="eyebrow eyebrow-blue">Selected Invoice</span>
                            <h3>Invoice Preview</h3>
                            <p>Showing the saved invoice you selected from the table above.</p>
                        </div>
                        <div class="invoice-preview-actions">
                            <button
                                type="button"
                                class="secondary-btn"
                                id="selectedInvoicePrintTrigger"
                                data-print-title="<?php echo htmlspecialchars($selectedReceiptNo); ?>"
                            >
                                <i class="fa-solid fa-print"></i>
                                Print Invoice
                            </button>
                            <form method="post" action="tuition_receipt_details.php" class="receipt-send-form invoice-send-form">
                                <input type="hidden" name="action" value="send_receipt">
                                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars((string)$selectedStudent['student_id']); ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($tuitionReceiptCsrfToken); ?>">
                                <input type="hidden" name="payment_id" value="<?php echo (int)$selectedPayment['id']; ?>">
                                <button type="submit" class="primary-btn" <?php echo filter_var($studentEmail, FILTER_VALIDATE_EMAIL) ? '' : 'disabled'; ?>>
                                    <i class="fa-solid fa-paper-plane"></i>
                                    Send Invoice to Gmail
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="receipt-preview">
                        <div class="invoice-sheet">
                            <div class="invoice-top">
                                <div class="invoice-title-block">
                                    <h4>INVOICE</h4>
                                    <div class="invoice-client">
                                        <strong><?php echo htmlspecialchars($studentName); ?></strong>
                                        <span>Student ID: <?php echo htmlspecialchars((string)$selectedStudent['student_id']); ?></span>
                                        <span>Grade Level: <?php echo htmlspecialchars((string)$selectedStudent['grade_level']); ?></span>
                                    </div>
                                </div>

                                <div class="invoice-header-right">
                                    <div class="invoice-meta">
                                        <div class="invoice-meta-row">
                                            <span>Invoice Date</span>
                                            <strong><?php echo htmlspecialchars($selectedPaymentDateDisplay); ?></strong>
                                        </div>
                                        <div class="invoice-meta-row">
                                            <span>Invoice Number</span>
                                            <strong><?php echo htmlspecialchars($selectedReceiptNo); ?></strong>
                                        </div>
                                        <div class="invoice-meta-row">
                                            <span>Reference</span>
                                            <strong><?php echo htmlspecialchars(trim((string)$selectedStudent['grade_level'] . ' SCHOOL YEAR ' . (string)($selectedStudent['school_year'] ?: 'N/A'))); ?></strong>
                                        </div>
                                    </div>

                                    <div class="invoice-brand">
                                        <img src="assets/logo.png" alt="SMARTENROLL Logo" class="invoice-brand-logo">
                                        <div class="invoice-brand-copy">
                                            <?php foreach ($schoolAddressLines as $line): ?>
                                                <span><?php echo htmlspecialchars($line); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="invoice-table-wrap">
                                <table class="invoice-table">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Discount</th>
                                            <th>Tax</th>
                                            <th>Amount PHP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($selectedPaymentItems as $item): ?>
                                            <?php
                                                $itemAmount = round((float)($item['amount'] ?? 0), 2);
                                                $itemLabel = build_invoice_item_description($selectedStudent, $item);
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($itemLabel); ?></td>
                                                <td>1.00</td>
                                                <td><?php echo htmlspecialchars(format_invoice_money($itemAmount)); ?></td>
                                                <td>0.00</td>
                                                <td>Tax on Sales</td>
                                                <td><?php echo htmlspecialchars(format_invoice_money($itemAmount)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="invoice-summary-wrap">
                                <div class="invoice-summary">
                                    <div class="invoice-summary-row compact">
                                        <span>Subtotal</span>
                                        <strong><?php echo htmlspecialchars(format_invoice_money($selectedPaymentAmount)); ?></strong>
                                    </div>
                                    <div class="invoice-summary-row rule-top">
                                        <span>TOTAL PHP</span>
                                        <strong><?php echo htmlspecialchars(format_invoice_money($selectedPaymentCumulativePaid)); ?></strong>
                                    </div>
                                    <div class="invoice-summary-row">
                                        <span>Less Amount Paid</span>
                                        <strong><?php echo htmlspecialchars(format_invoice_money($selectedPaymentAmount)); ?></strong>
                                    </div>
                                    <div class="invoice-summary-row due">
                                        <span>AMOUNT DUE PHP</span>
                                        <strong><?php echo htmlspecialchars(format_invoice_money($selectedPaymentRemainingBalance)); ?></strong>
                                    </div>
                                </div>
                            </div>

                            <?php if ($selectedPaymentNote !== ''): ?>
                                <div class="invoice-note">
                                    <strong>Payment Note</strong>
                                    <span><?php echo htmlspecialchars($selectedPaymentNote); ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="invoice-payment-section">
                                <div class="invoice-payment-head">
                                    <strong>Due Date: <?php echo htmlspecialchars($selectedPaymentDueDateDisplay); ?></strong>
                                    <span>Payment Details:</span>
                                </div>
                                <div class="invoice-bank-grid">
                                    <?php foreach ($paymentDetailBlocks as $detail): ?>
                                        <div class="invoice-bank-card">
                                            <strong><?php echo htmlspecialchars((string)$detail['branch']); ?></strong>
                                            <span>Account Name: <?php echo htmlspecialchars((string)$detail['account_name']); ?></span>
                                            <span>Bank: <?php echo htmlspecialchars((string)$detail['bank']); ?></span>
                                            <span>Account No.: <?php echo htmlspecialchars((string)$detail['account_number']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="invoice-footer">
                                <?php echo htmlspecialchars($registeredOfficeLine); ?>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
        </div>
    <?php endif; ?>
</main>

<template id="selectedPaymentRowTemplate">
    <tr class="selected-payment-row" data-option="">
        <td class="selected-description-cell">
            <div class="selected-description-wrap">
                <button type="button" class="remove-selected-btn" aria-label="Remove payment row">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div class="selected-payment-label">
                    <strong class="selected-item-name"></strong>
                    <span class="selected-row-status">Included automatically</span>
                </div>
            </div>
        </td>
        <td class="selected-row-qty">1.00</td>
        <td class="selected-suggested-amount">
            <span class="selected-unit-price-display">0.00</span>
            <div class="tuition-manual-wrap is-hidden">
                <input
                    type="text"
                    class="tuition-manual-input"
                    inputmode="decimal"
                    value="0.00"
                    aria-label="Tuition Fee Unit Price"
                >
            </div>
        </td>
        <td class="selected-row-discount">0.00</td>
        <td class="selected-row-tax">Tax on Sales</td>
        <td class="selected-row-entry">
            <strong class="selected-row-amount">0.00</strong>
        </td>
    </tr>
</template>

<script src="js/pay_tuition.js"></script>
</body>
</html>
