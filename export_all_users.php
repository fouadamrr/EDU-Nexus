<?php
require_once __DIR__ . '/database/db_connection.php';
$pdo = get_pdo();

// توحيد جميع الباسوردات لتسهيل الدخول
$hash = password_hash('123456', PASSWORD_DEFAULT);
$pdo->exec("UPDATE users SET password = '{$hash}'");

// استخراج جميع المستخدمين
$stmt = $pdo->query("SELECT role, username, email, full_name FROM users ORDER BY role, username");
$users = $stmt->fetchAll();

$txt = "=== جميع حسابات النظام وكلمات المرور ===\n";
$txt .= "تم توحيد كلمة المرور لجميع الحسابات إلى: 123456\n\n";

foreach ($users as $u) {
    $txt .= "الرتبة: " . $u['role'] . "\n";
    $txt .= "اسم المستخدم: " . $u['username'] . "\n";
    $txt .= "البريد الإلكتروني: " . ($u['email'] ?: 'غير متوفر') . "\n";
    $txt .= "كلمة المرور: 123456\n";
    $txt .= "--------------------------------------------------\n";
}

file_put_contents(__DIR__ . '/all_passwords.txt', $txt);
echo "Done";
