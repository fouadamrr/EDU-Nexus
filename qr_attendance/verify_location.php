<?php
/**
 * verify_location.php — الـ API بتاع التحقق من مكان الطالب في حضور الـ QR
 * بيستلم (التوكن، خط الطول، خط العرض، والدقة)
 * وبيرجع تحويل لصفحة النتيجة بالحالة
 */
require_once '../includes/header.php';

// لازم اللي بيسجل يكون طالب غير كدا يرجع للرئيسية
if ($role !== 'student') {
 header('Location: ../index.php'); exit;
}

// ── معادلة الـ Haversine عشان نحسب المسافة بين نقطتين على الخريطة ─────────────
function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float {
 $R = 6371000; // نص قطر الأرض بالمتر
 $φ1 = deg2rad($lat1); $φ2 = deg2rad($lat2);
 $Δφ = deg2rad($lat2 - $lat1);
 $Δλ = deg2rad($lng2 - $lng1);
 $a = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;
 return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

// ── فنكشن مساعدة عشان نسجل العمليات (Logs) ───────────────────────────────────
function logQR(PDO $pdo, ?int $uid, ?int $sid, string $action, string $detail = ''): void {
 try {
 $pdo->prepare("INSERT INTO qr_logs (user_id,session_id,action,details,ip_address) VALUES(?,?,?,?,?)")
 ->execute([$uid, $sid, $action, $detail, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
 } catch (Exception $e) {}
}

// ── تنظيف وتأمين البيانات اللي جاية من بره ────────────────────────────────────
$token = trim($_POST['token'] ?? '');
$lat_raw = $_POST['lat'] ?? '';
$lng_raw = $_POST['lng'] ?? '';
$accuracy = (float)($_POST['accuracy'] ?? 0);

$lat = ($lat_raw !== '') ? (float)$lat_raw : null;
$lng = ($lng_raw !== '') ? (float)$lng_raw : null;

// بنتأكد إن خطوط الطول والعرض في النطاق الصح بتاع الخريطة
if ($lat !== null && ($lat < -90 || $lat > 90)) $lat = null;
if ($lng !== null && ($lng < -180 || $lng > 180)) $lng = null;

// فنكشن مساعدة عشان التحويل بين الصفحات بالرسايل
function redirectBack(string $token, string $msg): never {
 $token_enc = urlencode($token);
 header("Location: attend.php?token={$token_enc}&result=" . urlencode($msg));
 exit;
}

if (!$token) {
 redirectBack('', 'رمز غير صالح.');
}

// ── بننهي الجلسات القديمة اللي وقتها خلص لوحدها ───────────────────────────────
try {
 $pdo->prepare("UPDATE lecture_sessions SET status='expired' WHERE status='active' AND expires_at < NOW()")->execute();
} catch (Exception $e) {}

// ── بنجيب بيانات الجلسة وبنتأكد إنها سليمة ────────────────────────────────────
try {
 $stmt = $pdo->prepare("
 SELECT ls.id, ls.status, ls.college_id, ls.course_id, ls.expires_at,
 c.name AS course_name,
 col.latitude AS college_lat, col.longitude AS college_lng
 FROM lecture_sessions ls
 JOIN courses c ON c.id = ls.course_id
 LEFT JOIN colleges col ON col.id = ls.college_id
 WHERE ls.token = ?
 ");
 $stmt->execute([$token]);
 $session = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
 logQR($pdo, $user_id, null, 'DB_ERROR', $e->getMessage());
 redirectBack($token, 'خطأ في قاعدة البيانات.');
}

if (!$session) {
 logQR($pdo, $user_id, null, 'INVALID_TOKEN', "token=$token");
 redirectBack($token, 'رمز الحضور غير صالح.');
}

$session_id = (int)$session['id'];

if ($session['status'] !== 'active') {
 logQR($pdo, $user_id, $session_id, 'SESSION_INACTIVE', "status={$session['status']}");
 redirectBack($token, 'الجلسة غير نشطة أو انتهت صلاحيتها.');
}

// ── بنتأكد إن الطالب مسجلش حضور قبل كدا في نفس المحاضرة ───────────────────────
try {
 $dup = $pdo->prepare("SELECT id FROM qr_attendance WHERE session_id=? AND student_id=?");
 $dup->execute([$session_id, $user_id]);
 if ($dup->fetch()) {
 logQR($pdo, $user_id, $session_id, 'DUPLICATE_ATTEMPT', 'Student tried to attend twice');
 redirectBack($token, 'duplicate');
 }
} catch (Exception $e) {
 redirectBack($token, 'خطأ في التحقق من التكرار.');
}

// ── بنحسب المسافة بين الطالب والكلية ─────────────────────────────────────────
$ALLOWED_RADIUS_M = 1000; // المسافة المسموحة (كيلو متر واحد)
$distance_m = null;
$attend_status = 'present';
$detail_log = '';

$college_lat = !empty($session['college_lat']) ? (float)$session['college_lat'] : null;
$college_lng = !empty($session['college_lng']) ? (float)$session['college_lng'] : null;

if ($lat !== null && $lng !== null && $college_lat !== null && $college_lng !== null) {
 $distance_m = haversine($lat, $lng, $college_lat, $college_lng);
 $detail_log = sprintf(
 'student=%.6f,%.6f college=%.6f,%.6f dist=%.1fm accuracy=%.1fm',
 $lat, $lng, $college_lat, $college_lng, $distance_m, $accuracy
 );

 if ($distance_m > $ALLOWED_RADIUS_M) {
 $attend_status = 'suspicious';
 logQR($pdo, $user_id, $session_id, 'SUSPICIOUS_LOCATION', $detail_log);
 } else {
 logQR($pdo, $user_id, $session_id, 'ATTENDANCE_PRESENT', $detail_log);
 }
} elseif ($lat === null || $lng === null) {
 // الطالب مقفل اللوكيشن أو مبعتش إحداثيات خالص
 $attend_status = 'suspicious';
 $detail_log = 'No GPS coordinates provided';
 logQR($pdo, $user_id, $session_id, 'NO_LOCATION', $detail_log);
} else {
 // الطالب باعت مكانه بس الكلية مش متظبط مكانها في الداتا بيز
 $attend_status = 'present'; // هنمشيه حضور طالما الكلية مش محددة مكانها ونثق فيه
 $detail_log = sprintf('student=%.6f,%.6f — college location not configured', $lat, $lng);
 logQR($pdo, $user_id, $session_id, 'ATTENDANCE_NO_COLLEGE_LOC', $detail_log);
}

// ── بنسجل عملية الحضور في جدول الـ QR ────────────────────────────────────────
try {
 $ins = $pdo->prepare("
 INSERT INTO qr_attendance (session_id, student_id, student_lat, student_lng, distance_m, status)
 VALUES (?,?,?,?,?,?)
 ");
 $ins->execute([
 $session_id,
 $user_id,
 $lat,
 $lng,
 $distance_m !== null ? round($distance_m, 2) : null,
 $attend_status
 ]);
 
 // ── بنسجل الحضور كمان في الجدول الأساسي عشان يظهر في التقارير العامة ─────────
 // عشان يظهر في التقارير الشهرية وشغل الإدارة المعتاد
 try {
 $sync = $pdo->prepare("
 INSERT INTO attendance (college_id, user_id, course_id, date, status)
 VALUES (?, ?, ?, CURRENT_DATE, ?)
 ON CONFLICT (user_id, course_id, date) DO UPDATE 
 SET status = EXCLUDED.status, college_id = EXCLUDED.college_id
 ");
 $sync->execute([
 $session['college_id'],
 $user_id,
 $session['course_id'] ?? null, 
 $attend_status
 ]);
 } catch (Exception $e) {
 // سجل الغلطة بس متوقفش العملية كلها عشان الطالب ميتحطلش غياب ظلم
 logQR($pdo, $user_id, $session_id, 'SYNC_ERROR', $e->getMessage());
 }

} catch (Exception $e) {
 // لو السجل موجود قبل كدا يبقى تكرار محاولة تسجيل حضور
 if (str_contains($e->getMessage(), 'unique') || str_contains($e->getMessage(), 'duplicate')) {
 redirectBack($token, 'duplicate');
 }
 logQR($pdo, $user_id, $session_id, 'INSERT_ERROR', $e->getMessage());
 redirectBack($token, 'خطأ في تسجيل الحضور. يرجى المحاولة مجدداً.');
}

// ── كله تمام: حول الطالب لصفحة النتيجة ───────────────────────────────────────
$result_param = 'success'; // بنظهر للطالب إنه نجح دايماً عشان منقلقوش وحسب المطلوب
header("Location: attend.php?token=" . urlencode($token) . "&result={$result_param}");
exit;
