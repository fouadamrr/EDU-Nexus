<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/controllers/MailController.php';

$user_id = $_SESSION['pending_verification_id'] ?? null;
$email = $_SESSION['pending_verification_email'] ?? '';

if (!$user_id || !$email) {
    echo json_encode(['success' => false, 'message' => 'بيانات الجلسة غير صالحة. يرجى التسجيل مرة أخرى.']);
    exit;
}

// بنشوف لو اليوزر لسه باعت كود من أقل من دقيقة عشان ميبعتش كتير ورا بعض ونتحظر
if (isset($_SESSION['last_otp_resend_time'])) {
    $time_passed = time() - $_SESSION['last_otp_resend_time'];
    if ($time_passed < 60) {
        $remaining = 60 - $time_passed;
        echo json_encode(['success' => false, 'message' => "يرجى الانتظار $remaining ثانية قبل المحاولة مرة أخرى."]);
        exit;
    }
}

try {
    $stmt = $pdo->prepare("SELECT id, status FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'المستخدم غير موجود.']);
        exit;
    }

    if ($user['status'] === 'active' || $user['status'] !== 'pending_verification') {
        echo json_encode(['success' => false, 'message' => 'هذا الحساب مفعل بالفعل أو ليس بانتظار التفعيل.']);
        exit;
    }

    // بنولد كود تفعيل جديد 6 أرقام
    $new_otp = rand(100000, 999999);

    // بنحدث الكود الجديد في الداتا بيز
    $updateStmt = $pdo->prepare("UPDATE users SET verification_code = :otp WHERE id = :id");
    $updateStmt->execute([':otp' => $new_otp, ':id' => $user_id]);

    // بنبعت الإيميل الجديد عن طريق نظام الإيميل بتاعنا
    $mail = new MailController();
    $mailSent = $mail->sendVerificationEmail($email, $new_otp);

    if ($mailSent) {
        $_SESSION['last_otp_resend_time'] = time();
        echo json_encode(['success' => true, 'message' => 'تم إرسال كود جديد إلى بريدك الإلكتروني بنجاح.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء محاولة إرسال الإيميل. يرجى المحاولة لاحقاً.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()]);
}
