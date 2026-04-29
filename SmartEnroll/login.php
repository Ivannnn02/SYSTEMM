<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mail/PHPMailer/mail_helper.php';

smartenroll_auth_start_session();

$currentUser = smartenroll_current_user();
if ($currentUser !== null && ($_GET['action'] ?? '') !== 'logout') {
    header('Location: dashboard.php');
    exit;
}

if (($_GET['action'] ?? '') === 'logout') {
    smartenroll_logout_user();
    header('Location: login.php?status=logged_out');
    exit;
}

$activeTab = 'login';
$errorMessage = '';
$successMessage = '';
$loginEmailValue = '';
$registerNameValue = '';
$registerEmployeeIdValue = '';
$registerEmailValue = '';
$registerVerificationCodeValue = '';
$registerVerificationRequired = false;
$loginCsrfToken = smartenroll_csrf_token('login_form');
$registerCsrfToken = smartenroll_csrf_token('register_form');

function smartenroll_registration_verification_state(): ?array
{
    $state = $_SESSION['register_verification'] ?? null;
    return is_array($state) ? $state : null;
}

function smartenroll_clear_registration_verification(): void
{
    unset($_SESSION['register_verification']);
}

function smartenroll_send_registration_code(string $email, string $fullName, string $code, ?string &$error = null): bool
{
    $subject = 'SMARTENROLL Registration Verification Code';
    $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $html = <<<HTML
        <div style="font-family:Poppins,Arial,sans-serif;color:#19325a;line-height:1.6;">
            <h2 style="margin-bottom:8px;">SMARTENROLL Email Verification</h2>
            <p>Hello {$safeName},</p>
            <p>Your verification code is:</p>
            <div style="display:inline-block;padding:12px 18px;background:#eef4ff;border-radius:12px;font-size:24px;font-weight:700;letter-spacing:4px;">{$safeCode}</div>
            <p style="margin-top:16px;">This code will expire in 10 minutes.</p>
        </div>
        HTML;
    $text = "Hello {$fullName}, your SMARTENROLL verification code is {$code}. This code will expire in 10 minutes.";

    return smtp_send_mail($email, $subject, $html, $text, $error);
}

if (($_GET['status'] ?? '') === 'logged_out') {
    $successMessage = 'You have been logged out successfully.';
}

$pendingVerification = smartenroll_registration_verification_state();
if ($pendingVerification !== null) {
    $registerNameValue = (string)($pendingVerification['full_name'] ?? '');
    $registerEmployeeIdValue = (string)($pendingVerification['employee_id'] ?? '');
    $registerEmailValue = (string)($pendingVerification['email'] ?? '');
    $registerVerificationRequired = true;
}

try {
    $conn = smartenroll_auth_db();
    smartenroll_ensure_users_table($conn);
} catch (Throwable $e) {
    $conn = null;
    $errorMessage = 'Database connection failed. Please check the MySQL server and database.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn instanceof mysqli) {
    $authAction = $_POST['auth_action'] ?? 'login';

    if ($authAction === 'login') {
        $activeTab = 'login';
        $csrfToken = trim((string)($_POST['csrf_token'] ?? ''));
        $loginEmailValue = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $attemptKey = smartenroll_login_attempt_key($loginEmailValue);

        if (!smartenroll_verify_csrf($csrfToken, 'login_form')) {
            $errorMessage = 'Session verification failed. Please refresh and try again.';
        } elseif (!smartenroll_login_is_allowed($attemptKey, $retryAfterSeconds)) {
            $errorMessage = 'Too many login attempts. Try again in ' . max(1, (int)ceil(((int)$retryAfterSeconds) / 60)) . ' minute(s).';
        } elseif ($loginEmailValue === '' || $password === '') {
            $errorMessage = 'Please enter your email address and password.';
        } else {
            $user = smartenroll_find_user_by_email($conn, $loginEmailValue);

            if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
                smartenroll_record_login_failure($attemptKey);
                $errorMessage = 'The email address or password is incorrect.';
            } else {
                smartenroll_clear_login_failures($attemptKey);
                smartenroll_login_user($user);
                header('Location: dashboard.php');
                exit;
            }
        }
    }

    if ($authAction === 'register') {
        $activeTab = 'register';
        $csrfToken = trim((string)($_POST['csrf_token'] ?? ''));
        $registerNameValue = trim((string) ($_POST['full_name'] ?? ''));
        $registerEmployeeIdValue = trim((string) ($_POST['employee_id'] ?? ''));
        $registerEmailValue = trim((string) ($_POST['register_email'] ?? ''));
        $registerRoleValue = 'finance';
        $password = (string) ($_POST['register_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (!smartenroll_verify_csrf($csrfToken, 'register_form')) {
            $errorMessage = 'Session verification failed. Please refresh and try again.';
        } elseif ($registerNameValue === '' || $registerEmployeeIdValue === '' || $registerEmailValue === '' || $password === '' || $confirmPassword === '') {
            $errorMessage = 'Please complete all registration fields.';
        } elseif (!filter_var($registerEmailValue, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please enter a valid email address for registration.';
        } elseif (smartenroll_find_user_by_employee_id($conn, $registerEmployeeIdValue) !== null) {
            $errorMessage = 'That Employee ID is already registered.';
        } elseif (strlen($password) < 6) {
            $errorMessage = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirmPassword) {
            $errorMessage = 'Password confirmation does not match.';
        } elseif (smartenroll_find_user_by_email($conn, $registerEmailValue) !== null) {
            $errorMessage = 'That email is already registered.';
        } elseif (smartenroll_find_user_by_role($conn, $registerRoleValue) !== null) {
            $errorMessage = 'A unique ' . ucfirst($registerRoleValue) . ' account already exists.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $verificationCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $mailError = '';

            if (!smartenroll_send_registration_code($registerEmailValue, $registerNameValue, $verificationCode, $mailError)) {
                $errorMessage = $mailError !== '' ? $mailError : 'Unable to send verification code right now.';
            } else {
                $_SESSION['register_verification'] = [
                    'full_name' => $registerNameValue,
                    'employee_id' => $registerEmployeeIdValue,
                    'email' => $registerEmailValue,
                    'password_hash' => $passwordHash,
                    'role' => $registerRoleValue,
                    'code' => $verificationCode,
                    'expires_at' => time() + 600,
                ];
                $registerVerificationRequired = true;
                $successMessage = 'We sent a verification code to your Gmail account. Enter it below to finish registration.';
            }
        }
    }

    if ($authAction === 'verify_register_code') {
        $activeTab = 'register';
        $csrfToken = trim((string)($_POST['csrf_token'] ?? ''));
        $registerVerificationCodeValue = trim((string)($_POST['verification_code'] ?? ''));
        $pendingVerification = smartenroll_registration_verification_state();
        $registerVerificationRequired = true;

        if ($pendingVerification !== null) {
            $registerNameValue = (string)($pendingVerification['full_name'] ?? '');
            $registerEmployeeIdValue = (string)($pendingVerification['employee_id'] ?? '');
            $registerEmailValue = (string)($pendingVerification['email'] ?? '');
        }

        if (!smartenroll_verify_csrf($csrfToken, 'register_form')) {
            $errorMessage = 'Session verification failed. Please refresh and try again.';
        } elseif ($pendingVerification === null) {
            $errorMessage = 'Your verification session has expired. Please register again.';
            $registerVerificationRequired = false;
        } elseif ($registerVerificationCodeValue === '') {
            $errorMessage = 'Please enter the verification code sent to your email.';
        } elseif (time() > (int)($pendingVerification['expires_at'] ?? 0)) {
            smartenroll_clear_registration_verification();
            $errorMessage = 'Your verification code has expired. Please register again.';
            $registerVerificationRequired = false;
        } elseif (!hash_equals((string)($pendingVerification['code'] ?? ''), $registerVerificationCodeValue)) {
            $errorMessage = 'The verification code is incorrect.';
        } elseif (smartenroll_find_user_by_employee_id($conn, (string)$pendingVerification['employee_id']) !== null) {
            smartenroll_clear_registration_verification();
            $errorMessage = 'That Employee ID is already registered.';
            $registerVerificationRequired = false;
        } elseif (smartenroll_find_user_by_email($conn, (string)$pendingVerification['email']) !== null) {
            smartenroll_clear_registration_verification();
            $errorMessage = 'That email is already registered.';
            $registerVerificationRequired = false;
        } elseif (smartenroll_find_user_by_role($conn, (string)$pendingVerification['role']) !== null) {
            smartenroll_clear_registration_verification();
            $errorMessage = 'A unique ' . ucfirst((string)$pendingVerification['role']) . ' account already exists.';
            $registerVerificationRequired = false;
        } else {
            $stmt = $conn->prepare('INSERT INTO users (full_name, employee_id, email, password_hash, role) VALUES (?, ?, ?, ?, ?)');
            $fullName = (string)$pendingVerification['full_name'];
            $employeeId = (string)$pendingVerification['employee_id'];
            $email = (string)$pendingVerification['email'];
            $passwordHash = (string)$pendingVerification['password_hash'];
            $role = (string)$pendingVerification['role'];
            $stmt->bind_param('sssss', $fullName, $employeeId, $email, $passwordHash, $role);
            $stmt->execute();
            $stmt->close();

            smartenroll_clear_registration_verification();
            $successMessage = ucfirst($role) . ' account registered successfully. You can sign in now.';
            $activeTab = 'login';
            $loginEmailValue = $email;
            $registerNameValue = '';
            $registerEmployeeIdValue = '';
            $registerEmailValue = '';
            $registerVerificationCodeValue = '';
            $registerVerificationRequired = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMARTENROLL | Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Staff login page for SMARTENROLL.">
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:opsz,wght@6..96,600;6..96,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="login-page">

<header class="landing-header">
    <a href="index.php#main-screen" class="logo" aria-label="Go to main screen">
        <img src="assets/logo.png" alt="Adreo Montessori Inc. Logo">
        <span>SMARTENROLL</span>
    </a>
</header>

<main class="login-main">
    <div class="login-panel">
        <div class="login-intro">
            <h1>Welcome to SMARTENROLL</h1>
                <p>Your official finance workspace for handling enrollment records, requirements, tuition, and receipts.</p>
            <p class="login-intro-sub">
                Built for Adreo Montessori Inc. to keep the finance process organized, accurate, and easy to monitor.
            </p>
            <p class="login-intro-sub">
                Sign in to review requirements, confirm enrollment records, and manage billing from one place.
            </p>
            <div class="login-role-note">
                <h3>Account rules</h3>
                <p>Employee ID and email address must both be unique for the finance account.</p>
            </div>
        </div>

        <div class="login-card" data-active-tab="<?php echo htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="login-brand-mark">
                <img src="assets/logo.png" alt="SMARTENROLL Logo">
            </div>

            <div class="auth-switch" role="tablist" aria-label="Authentication forms">
                <button type="button" class="auth-switch-btn <?php echo $activeTab === 'login' ? 'active' : ''; ?>" data-auth-target="login">Sign In</button>
                <button type="button" class="auth-switch-btn <?php echo $activeTab === 'register' ? 'active' : ''; ?>" data-auth-target="register">Register</button>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="auth-alert auth-alert-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($successMessage !== ''): ?>
                <div class="auth-alert auth-alert-success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <section class="auth-panel <?php echo $activeTab === 'login' ? 'active' : ''; ?>" data-auth-panel="login">
                <p class="login-subtitle login-subtitle-centered">Use your SMARTENROLL credentials.</p>
                <form class="login-form" id="loginForm" action="login.php" method="post">
                    <input type="hidden" name="auth_action" value="login">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($loginCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <label>
                        Email Address
                        <input type="email" name="email" id="loginEmail" value="<?php echo htmlspecialchars($loginEmailValue, ENT_QUOTES, 'UTF-8'); ?>" placeholder="adreomontessori@gmail.com" required>
                    </label>
                    <label>
                        Password
                        <div class="password-field">
                            <input type="password" name="password" placeholder="Enter your password" required>
                            <span class="password-icon"><i class="fa-solid fa-eye"></i></span>
                        </div>
                    </label>
                    <div class="login-meta">
                        <label class="remember">
                            <input type="checkbox" name="remember" id="rememberLogin">
                            Remember me
                        </label>
                    </div>
                    <button class="login-submit" type="submit">Sign In</button>
                    <div class="login-help">
                        <p>Use the official school account to continue to the SMARTENROLL dashboard.</p>
                    </div>
                </form>
            </section>

            <section class="auth-panel <?php echo $activeTab === 'register' ? 'active' : ''; ?>" data-auth-panel="register">
                <p class="login-subtitle login-subtitle-centered">Create the SMARTENROLL finance account.</p>
                <?php if ($registerVerificationRequired): ?>
                    <form class="login-form" id="registerVerifyForm" action="login.php" method="post">
                        <input type="hidden" name="auth_action" value="verify_register_code">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($registerCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <label>
                            Gmail Address
                            <input type="email" value="<?php echo htmlspecialchars($registerEmailValue, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </label>
                        <label>
                            Verification Code
                            <input type="text" name="verification_code" value="<?php echo htmlspecialchars($registerVerificationCodeValue, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter 6-digit code" inputmode="numeric" maxlength="6" required>
                        </label>
                        <button class="login-submit" type="submit">Verify Code</button>
                        <div class="login-help">
                            <p>Check your Gmail inbox for the confirmation code we sent.</p>
                        </div>
                    </form>
                <?php else: ?>
                    <form class="login-form" id="registerForm" action="login.php" method="post">
                        <input type="hidden" name="auth_action" value="register">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($registerCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <label>
                            Full Name
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($registerNameValue, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter full name" required>
                        </label>
                        <label>
                            Employee ID
                            <input type="text" name="employee_id" value="<?php echo htmlspecialchars($registerEmployeeIdValue, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter Employee ID" required>
                        </label>
                        <label>
                            Email Address
                            <input type="email" name="register_email" value="<?php echo htmlspecialchars($registerEmailValue, ENT_QUOTES, 'UTF-8'); ?>" placeholder="name@example.com" required>
                        </label>
                        <label>
                            Password
                            <div class="password-field">
                                <input type="password" name="register_password" placeholder="At least 6 characters" required>
                                <span class="password-icon"><i class="fa-solid fa-eye"></i></span>
                            </div>
                        </label>
                        <label>
                            Confirm Password
                            <div class="password-field">
                                <input type="password" name="confirm_password" placeholder="Re-enter password" required>
                                <span class="password-icon"><i class="fa-solid fa-eye"></i></span>
                            </div>
                        </label>
                        <button class="login-submit" type="submit">Send Verification Code</button>
                        <div class="login-help">
                            <p>Use your official Employee ID and Gmail address to create an account.</p>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<script src="js/script.js"></script>
<script src="js/login.js"></script>
</body>
</html> 