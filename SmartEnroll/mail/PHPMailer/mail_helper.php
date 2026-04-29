<?php

require_once __DIR__ . '/src/Exception.php';
require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class SmartenrollMailer extends PHPMailer
{
    public function addEmbeddedImageWithoutName(
        string $path,
        string $cid,
        string $encoding = self::ENCODING_BASE64,
        string $type = '',
        string $disposition = 'inline'
    ): bool {
        $added = $this->addEmbeddedImage($path, $cid, ' ', $encoding, $type, $disposition);
        if ($added && !empty($this->attachment)) {
            $lastIndex = array_key_last($this->attachment);
            if ($lastIndex !== null) {
                $this->attachment[$lastIndex][2] = '';
            }
        }

        return $added;
    }
}

function get_email_config(): array
{
    $configPath = __DIR__ . '/email_config.php';
    if (!is_file($configPath)) {
        return [];
    }

    $config = require $configPath;
    return is_array($config) ? $config : [];
}

function smtp_send_mail(string $to, string $subject, string $htmlBody, string $textBody, ?string &$error = null, array $embeddedImages = []): bool
{
    $config = get_email_config();

    $host = trim((string)($config['host'] ?? ''));
    $port = (int)($config['port'] ?? 0);
    $encryption = strtolower(trim((string)($config['encryption'] ?? 'ssl')));
    $username = trim((string)($config['username'] ?? ''));
    $password = str_replace(' ', '', (string)($config['password'] ?? ''));
    $fromEmail = trim((string)($config['from_email'] ?? ''));
    $fromName = trim((string)($config['from_name'] ?? 'SMARTENROLL'));

    if ($host === '' || $port <= 0 || $username === '' || $password === '' || $fromEmail === '') {
        $error = 'PHPMailer SMTP is not configured yet. Update email_config.php with your sender email and app password.';
        return false;
    }

    try {
        $mail = new SmartenrollMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 20;

        if ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;

        foreach ($embeddedImages as $image) {
            $path = (string)($image['path'] ?? '');
            $cid = (string)($image['cid'] ?? '');
            $name = (string)($image['name'] ?? basename($path));

            if ($path !== '' && $cid !== '' && is_file($path)) {
                if ($name === '') {
                    $mail->addEmbeddedImageWithoutName($path, $cid);
                } else {
                    $mail->addEmbeddedImage($path, $cid, $name);
                }
            }
        }

        return $mail->send();
    } catch (Exception $e) {
        $error = 'PHPMailer error: ' . $mail->ErrorInfo;
        return false;
    } catch (Throwable $e) {
        $error = 'Mail setup error: ' . $e->getMessage();
        return false;
    }
}
