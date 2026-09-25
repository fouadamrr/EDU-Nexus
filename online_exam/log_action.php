<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../database/db_connection.php';
$pdo = get_pdo();

// This is a beacon endpoint — log the action and exit silently
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$exam_id = (int)($_POST['exam_id'] ?? 0);
$student_id = (int)($_POST['student_id'] ?? $_SESSION['user_id'] ?? 0);
$action = htmlspecialchars(strip_tags($_POST['action'] ?? ''));
$details = htmlspecialchars(strip_tags($_POST['details'] ?? ''));

if (!$exam_id || !$student_id || !$action) { http_response_code(400); exit; }

$allowed_actions = ['tab_switch','page_leave','copy_attempt','page_refresh','time_anomaly','started','submitted'];
if (!in_array($action, $allowed_actions)) { http_response_code(400); exit; }

try {
 $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
 $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
 $pdo->prepare("INSERT INTO exam_logs (exam_id, student_id, action, details, ip_address, user_agent) VALUES (?,?,?,?,?,?)")
 ->execute([$exam_id, $student_id, $action, $details, $ip, $ua]);
} catch(Exception $e){}

http_response_code(200);
exit;
