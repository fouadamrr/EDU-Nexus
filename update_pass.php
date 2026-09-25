<?php
// update_pass.php
require_once __DIR__ . '/config/database.php';
$db = Database::getConnection();

$admin_pass = password_hash('123', PASSWORD_DEFAULT);
$super_pass = password_hash('admin123', PASSWORD_DEFAULT);

$stmt1 = $db->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$stmt1->execute([$admin_pass]);
echo "Admin updated: " . $stmt1->rowCount() . "\n";

$stmt2 = $db->prepare("UPDATE users SET password = ? WHERE username = 'superadmin'");
$stmt2->execute([$super_pass]);
echo "Superadmin updated: " . $stmt2->rowCount() . "\n";
