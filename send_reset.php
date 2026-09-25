<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/helpers.php';

$message = '';
$status = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $message = "يرجى إدخال البريد الإلكتروني.";
        $status = 'error';
    } else {
        // 1. Check if user exists (Generic message for security)
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            // 2. Generate secure token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // 3. Store in DB
            $updateStmt = $pdo->prepare("UPDATE users SET reset_token = :token, reset_expires = :expires WHERE id = :id");
            $updateStmt->execute([
                ':token' => $token,
                ':expires' => $expiry,
                ':id' => $user['id']
            ]);

            // 4. Log event
            $db->log('PASSWORD_RESET_REQUEST', "Requested for: " . $email, $user['id']);

            // 5. Generate Link
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $resetLink = "{$protocol}://{$host}{$dir}/reset_password.php?token={$token}";

            // 6. Send Real Email via SMTP
            require_once __DIR__ . '/controllers/MailController.php';
            $mail = new MailController();
            $mailSent = $mail->sendResetEmail($email, $resetLink, $user['full_name']);
            
            // Simulation flag for UI (can be kept for testing if $mailSent fails)
            $simulation = !$mailSent;
        }

        // Generic message regardless of existence (Prevent Enumeration)
        $message = "إذا كان هذا البريد مسجلاً لدينا، فستتلقى رابطاً لإعادة تعيين كلمة المرور قريباً.";
        $status = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title> إرسال رابط التعيين - EDU Nexus </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; background-color: #F3F4F6; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-8 text-center">
        <?php if ($status === 'success'): ?>
            <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-2xl"></i>
            </div>
            <h2 class="text-xl font-bold text-secondary mb-4"><?php echo $message; ?></h2>
            
            <?php if (isset($simulation) && $simulation): ?>
                <div class="mt-6 p-4 bg-blue-50 border-r-4 border-blue-500 text-right rounded-lg">
                    <p class="text-xs font-bold text-blue-800 mb-2 underline decoration-blue-200 uppercase tracking-widest">محاكاة إرسال البريد (Simulation Mode)</p>
                    <p class="text-[11px] text-blue-700 leading-relaxed mb-4">
                        بما أنه لم يتم ضبط خادم البريد (PHPMailer) بعد، يمكنك استخدام الرابط التالي للمتابعة:
                    </p>
                    <a href="<?php echo $resetLink; ?>" class="inline-block w-full bg-white border border-blue-200 text-blue-700 text-[10px] font-mono p-3 rounded-lg break-all hover:bg-blue-100 transition-colors">
                        <?php echo $resetLink; ?>
                    </a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
            </div>
            <h2 class="text-xl font-bold text-secondary mb-4"><?php echo $message; ?></h2>
        <?php endif; ?>

        <div class="mt-8">
            <a href="index.php" class="text-primary hover:text-primary-dark font-bold text-sm transition-colors decoration-underline">العودة لتسجيل الدخول</a>
        </div>
    </div>

</body>
</html>
