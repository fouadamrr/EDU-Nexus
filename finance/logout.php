<?php
session_start();

// نلغي جلسة المالية فقط عشان مايخرجش من البوابة الأكاديمية لو كان مسجل دخول كطالب
if (isset($_SESSION['finance_logged_in'])) {
    unset($_SESSION['finance_logged_in']);
    
    // لو كانت الجلسة دي بتاعة مدير المالية فقط ومفيش حساب تاني (زي طالب)
    if ($_SESSION['username'] === 'finance') {
        session_unset();
        session_destroy();
    }
}

header('Location: login.php');
exit;
