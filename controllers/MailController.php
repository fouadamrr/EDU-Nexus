<?php
/**
 * EDU Nexus — Mail Controller
 * Handles sending emails via Gmail SMTP using App Passwords via Raw Sockets.
 * (Custom SMTP Implementation because PHPMailer is missing)
 */

class MailController
{
    private $host = 'smtp.gmail.com';
    private $port = 587; // TLS
    private $username;
    private $password;
    private $fromName = 'EDU Nexus System';

    public function __construct()
    {
        // Ensure .env is loaded
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath) && !getenv('MAIL_USERNAME')) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                putenv(sprintf('%s=%s', $name, $value));
            }
        }
        $this->username = getenv('MAIL_USERNAME');
        $this->password = getenv('MAIL_PASSWORD');
    }

    /**
     * Send a password reset email
     */
    public function sendResetEmail($toEmail, $resetLink, $fullName = 'مستخدم EDU Nexus')
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $logoUrl = "{$protocol}://{$host}/assets/images/logo.png?v=1.2";

        $subject = "استعادة كلمة المرور - EDU Nexus";
        $body = "
            <!DOCTYPE html>
            <html dir='rtl'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            </head>
            <body style='margin: 0; padding: 20px; background-color: #f7f9fc;'>
                <table border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #eeeeee; border-radius: 12px; overflow: hidden;'>
                    <!-- Header -->
                    <tr>
                        <td align='center' bgcolor='#ffffff' style='padding: 40px 20px; border-bottom: 1px solid #f1f5f9;'>
                            <img src='{$logoUrl}' alt='EDU Nexus Logo' width='100' style='display: block; border: 0;'>
                        </td>
                    </tr>        <!-- Body -->
                    <tr>
                        <td align='center' bgcolor='#ffffff' style='padding: 45px 35px;'>
                            <h2 style='color: #1E3A8A; font-size: 24px; margin: 0 0 15px 0; font-family: Tahoma, Arial, sans-serif;'>{$fullName} مرحباً بك،</h2>
                            <p style='color: #444444; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;'>
                                لقد تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بحسابك في منظومة <b>EDU Nexus</b>.
                            </p>
                            <p style='color: #666666; font-size: 14px; margin: 0 0 35px 0;'>
                                يرجى الضغط على الزر أدناه للمتابعة (الرابط صالح لمدة ساعة):
                            </p>
                            <table border='0' cellpadding='0' cellspacing='0'>
                                <tr>
                                    <td align='center' bgcolor='#1E3A8A' style='border-radius: 12px;'>
                                        <a href='{$resetLink}' style='display: inline-block; padding: 15px 35px; font-size: 16px; color: #ffffff; text-decoration: none; font-weight: bold;'>إعادة تعيين كلمة المرور</a>
                                    </td>
                                </tr>
                            </table>
                            <p style='color: #aaaaaa; font-size: 12px; margin-top: 50px; border-top: 1px solid #eeeeee; padding-top: 20px;'>
                                إذا لم تكن أنت من طلب ذلك، يرجى تجاهل هذا الإيميل بسلام.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td align='center' bgcolor='#fafafa' style='padding: 20px; color: #bbbbbb; font-size: 11px;'>
                            &copy; " . date('Y') . " EDU Nexus System. جميع الحقوق محفوظة.
                        </td>
                    </tr>
                </table>
            </body>
            </html>
        ";

        return $this->sendMail($toEmail, $subject, $body);
    }

    public function sendVerificationEmail($toEmail, $otp)
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $logoUrl = "{$protocol}://{$host}/assets/images/logo.png?v=1.2";

        $subject = "كود تفعيل حسابك - EDU Nexus";
        $body = "
            <!DOCTYPE html>
            <html dir='rtl'>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { margin: 0; padding: 0; background-color: #f0f4f8; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; }
                </style>
            </head>
            <body>
                <table border='0' cellpadding='0' cellspacing='0' width='100%' style='padding: 30px 10px;'>
                    <tr>
                        <td align='center'>
                            <table border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; background-color: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 15px 45px rgba(30, 58, 138, 0.1);'>
                                <!-- Header with Deep Blue Gradient -->
                                <tr>
                                    <td align='center' style='background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 50px 20px;'>
                                        <div style='background: rgba(255,255,255,0.15); width: 110px; height: 110px; border-radius: 35px; display: table; margin-bottom: 20px;'>
                                            <div style='display: table-cell; vertical-align: middle; text-align: center;'>
                                                <img src='{$logoUrl}' alt='EDU Nexus' width='80' style='display: inline-block;'>
                                            </div>
                                        </div>
                                        <h1 style='color: #ffffff; margin: 0; font-size: 26px; font-weight: bold; letter-spacing: 1px; text-shadow: 0 2px 4px rgba(0,0,0,0.1);'>EDU Nexus</h1>
                                        <p style='color: rgba(255,255,255,0.8); margin: 8px 0 0 0; font-size: 14px; font-weight: 500;'>النظام الأكاديمي الذكي</p>
                                    </td>
                                </tr>
                                <!-- Content -->
                                <tr>
                                    <td style='padding: 50px 40px; text-align: center;'>
                                        <h2 style='color: #1e293b; font-size: 24px; margin: 0 0 15px 0; font-weight: 700;'>تأكيد تسجيل الحساب</h2>
                                        <p style='color: #64748b; font-size: 16px; line-height: 1.8; margin-bottom: 40px;'>
                                            أهلاً بكِ في EDU Nexus. يرجى استخدام الرمز التالي لتفعيل حسابكِ والبدء في استكشاف المميزات الأكاديمية:
                                        </p>
                                        <!-- Professional OTP Box -->
                                        <div style='background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 24px; padding: 35px; display: inline-block; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);'>
                                            <span style='color: #2563eb; font-size: 48px; font-weight: 800; letter-spacing: 12px; display: block;'>{$otp}</span>
                                        </div>
                                        <p style='color: #94a3b8; font-size: 13px; margin-top: 45px; border-top: 1px solid #f1f5f9; padding-top: 30px;'>
                                            * هذا الرمز صالح لمدة 60 دقيقة فقط.<br>
                                            إذا لم تطلبي هذا الرمز، يرجى تجاهل هذا الإيميل.
                                        </p>
                                    </td>
                                </tr>
                                <!-- Footer -->
                                <tr>
                                    <td align='center' style='background-color: #1e293b; padding: 30px; color: #94a3b8; font-size: 11px;'>
                                        <p style='margin: 0; color: #ffffff; font-weight: bold; margin-bottom: 8px;'>EDU Nexus Infrastructure</p>
                                        <p style='margin: 0;'>&copy; " . date('Y') . " جميع الحقوق محفوظة للنظام التعليمي الذكي.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
        ";

        return $this->sendMail($toEmail, $subject, $body);
    }

    /**
     * Internal SMTP Sending Logic via Sockets
     */
    private function sendMail($to, $subject, $body)
    {
        try {
            $timeout = 30;
            $socket = fsockopen($this->host, $this->port, $errno, $errstr, $timeout);
            if (!$socket) throw new Exception("Could not connect to SMTP host: $errstr ($errno)");

            $this->getResponse($socket, "220"); // Initial connection

            $server_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $this->sendCommand($socket, "EHLO " . $server_host, "250");
            $this->sendCommand($socket, "STARTTLS", "220");

            // Enable crypto for TLS
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("Could not enable TLS encryption");
            }

            $this->sendCommand($socket, "EHLO " . $server_host, "250");
            $this->sendCommand($socket, "AUTH LOGIN", "334");
            $this->sendCommand($socket, base64_encode($this->username), "334");
            $this->sendCommand($socket, base64_encode($this->password), "235");

            $this->sendCommand($socket, "MAIL FROM: <{$this->username}>", "250");
            $this->sendCommand($socket, "RCPT TO: <{$to}>", "250");
            $this->sendCommand($socket, "DATA", "354");

            // Email Construction
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "To: <{$to}>\r\n";
            $headers .= "From: {$this->fromName} <{$this->username}>\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "Date: " . date("r") . "\r\n";

            $this->sendCommand($socket, $headers . "\r\n" . $body . "\r\n.", "250");
            $this->sendCommand($socket, "QUIT", "221");

            fclose($socket);
            return true;
        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        }
    }

    private function sendCommand($socket, $command, $expectedResponse)
    {
        fwrite($socket, $command . "\r\n");
        return $this->getResponse($socket, $expectedResponse);
    }

    private function getResponse($socket, $expectedResponse)
    {
        $response = "";
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) == " ") break;
        }
        if (strpos($response, $expectedResponse) !== 0) {
            throw new Exception("Unexpected response: $response (Expected: $expectedResponse)");
        }
        return $response;
    }
}
