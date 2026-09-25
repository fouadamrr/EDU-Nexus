<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../database/db_connection.php';
$pdo = get_pdo();

$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

if (!in_array($role, ['instructor', 'admin', 'super_admin'])) {
 echo "<script>window.location.href='../index.php';</script>"; exit;
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function generateToken(): string {
 return bin2hex(random_bytes(32)); // 64-char hex, cryptographically secure
}

function logAction(PDO $pdo, ?int $uid, ?int $sid, string $action, string $detail = ''): void {
 try {
 $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
 $pdo->prepare("INSERT INTO qr_logs (user_id,session_id,action,details,ip_address) VALUES(?,?,?,?,?)")
 ->execute([$uid, $sid, $action, $detail, $ip]);
 } catch (Exception $e) {}
}

// ── Fetch instructor's courses ────────────────────────────────────────────────
$courses = [];
try {
 $pdo->exec("ALTER TABLE courses ADD COLUMN IF NOT EXISTS doctor_id INT");
 
 if ($role === 'instructor') {
 $stmt = $pdo->prepare("
 SELECT DISTINCT c.id, c.name, c.code, c.level, col.name AS college_name, col.id AS college_id
 FROM courses c
 LEFT JOIN colleges col ON col.id = c.college_id
 WHERE (c.instructor_id = ? OR c.doctor_id = ?)
 ORDER BY c.level, c.name
 ");
 $stmt->execute([$user_id, $user_id]);
 } else {
 $stmt = $pdo->query("
 SELECT c.id, c.name, c.code, c.level, col.name AS college_name, col.id AS college_id
 FROM courses c LEFT JOIN colleges col ON col.id = c.college_id
 ORDER BY c.level, c.name
 ");
 }
 $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ── Action: Create new session ────────────────────────────────────────────────
$message = '';
$new_session = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_session') {
 $course_id = (int)($_POST['course_id'] ?? 0);
 $duration_min = min(60, max(5, (int)($_POST['duration_min'] ?? 10)));

 if (!$course_id) {
 $message = '<div class="alert alert-danger">يرجى اختيار مقرر.</div>';
 } else {
 // Get college_id from course
 $crs = $pdo->prepare("SELECT college_id FROM courses WHERE id=?");
 $crs->execute([$course_id]); $crs_row = $crs->fetch(PDO::FETCH_ASSOC);
 $college_id = $crs_row['college_id'] ?? null;

 $token = generateToken();
 $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_min} minutes"));

 try {
 $ins = $pdo->prepare("
 INSERT INTO lecture_sessions (course_id, instructor_id, college_id, token, duration_min, expires_at, status)
 VALUES (?,?,?,?,?,?,'active')
 ");
 $ins->execute([$course_id, $user_id, $college_id, $token, $duration_min, $expires_at]);
 $new_session_id = $pdo->lastInsertId();
 logAction($pdo, $user_id, $new_session_id, 'SESSION_CREATED', "course=$course_id duration={$duration_min}min");
 header("Location: start_session.php?session_id={$new_session_id}");
 exit;
 } catch (Exception $e) {
 $message = '<div class="alert alert-danger">خطأ في إنشاء الجلسة: ' . htmlspecialchars($e->getMessage()) . '</div>';
 }
 }
}

// ── Action: End session ───────────────────────────────────────────────────────
if (isset($_GET['end']) && ($sid = (int)$_GET['end'])) {
 // Verify ownership
 $chk = $pdo->prepare("SELECT id FROM lecture_sessions WHERE id=? AND instructor_id=?");
 $chk->execute([$sid, $user_id]);
 if ($chk->fetch() || in_array($role, ['admin','super_admin'])) {
 $pdo->prepare("UPDATE lecture_sessions SET status='ended', ended_at=NOW() WHERE id=?")
 ->execute([$sid]);
 logAction($pdo, $user_id, $sid, 'SESSION_ENDED', '');
 header("Location: session_report.php?session_id={$sid}"); exit;
 }
}

// ── Load active session if session_id given ───────────────────────────────────
$active_session = null;
$attend_url = '';
if (isset($_GET['session_id'])) {
 $sid = (int)$_GET['session_id'];
 // Auto-expire sessions past their expires_at
 $pdo->prepare("UPDATE lecture_sessions SET status='expired' WHERE status='active' AND expires_at < NOW()")->execute();
 $stmt = $pdo->prepare("
 SELECT ls.*, c.name AS course_name, c.code AS course_code,
 col.name AS college_name, col.latitude, col.longitude
 FROM lecture_sessions ls
 JOIN courses c ON c.id = ls.course_id
 LEFT JOIN colleges col ON col.id = ls.college_id
 WHERE ls.id=? AND ls.instructor_id=?
 ");
 $stmt->execute([$sid, $user_id]);
 $active_session = $stmt->fetch(PDO::FETCH_ASSOC);

 if (!$active_session && in_array($role, ['admin','super_admin'])) {
 $stmt2 = $pdo->prepare("
 SELECT ls.*, c.name AS course_name, c.code AS course_code,
 col.name AS college_name, col.latitude, col.longitude
 FROM lecture_sessions ls
 JOIN courses c ON c.id = ls.course_id
 LEFT JOIN colleges col ON col.id = ls.college_id
 WHERE ls.id=?
 ");
 $stmt2->execute([$sid]);
 $active_session = $stmt2->fetch(PDO::FETCH_ASSOC);
 }

 if ($active_session) {
 $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 $host = $_SERVER['HTTP_HOST'];
 if (strpos($host, ':') === false) {
     $host .= ':8081';
 }
 $base = $proto . '://' . $host;
 // Detect subfolder prefix and fix Windows backslashes
 $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
 $root_dir = str_replace('\\', '/', dirname($script_dir));
 if ($root_dir === '/') $root_dir = '';
 
 $attend_url = $base . $root_dir . '/qr_attendance/attend.php?token=' . $active_session['token'];
 }
}

// ── Live attendance count for the active session ──────────────────────────────
$live_attendees = [];
if ($active_session) {
 try {
 $stmt = $pdo->prepare("
 SELECT u.full_name, u.username, qa.marked_at, qa.status, qa.distance_m
 FROM qr_attendance qa JOIN users u ON u.id = qa.student_id
 WHERE qa.session_id = ?
 ORDER BY qa.marked_at DESC
 ");
 $stmt->execute([$active_session['id']]);
 $live_attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
 } catch (Exception $e) {}
}

$time_left_sec = $active_session
 ? max(0, strtotime($active_session['expires_at']) - time())
 : 0;

require_once '../includes/header.php';
$page_title = 'نظام الحضور بـ QR Code';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
 <?php /* header.php already outputs <html> header normally */ ?>
 <style>
 .qr-wrapper { background:#fff; border-radius:16px; padding:20px; display:inline-block; box-shadow:0 4px 24px rgba(0,0,0,.12); }
 #countdown { font-variant-numeric: tabular-nums; }
 .pulse-ring { animation: pulse 1.5s ease-in-out infinite; }
 @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.4;} }
 @media print { .no-print{display:none!important;} }
 </style>
</head>

<div class="max-w-5xl mx-auto space-y-6">

 <!-- Header -->
 <div class="flex flex-wrap items-center justify-between gap-4">
 <div>
 <h1 class="text-2xl font-bold text-secondary flex items-center gap-2">
 <i class="fas fa-qrcode text-primary bg-bg p-2 rounded-xl"></i>
 نظام الحضور بـ QR Code
 </h1>
 <p class="text-sm text-slate-500 mt-1">توليد رمز QR لتسجيل حضور المحاضرة</p>
 </div>
 <a href="my_sessions.php" class="bg-bg text-slate-700 px-4 py-2 rounded-xl font-bold hover:bg-slate-200 transition flex items-center gap-2 no-print text-sm">
 <i class="fas fa-history"></i> سجل الجلسات
 </a>
 </div>

 <?php echo $message; ?>

 <?php if (!$active_session): ?>
 <!-- ── CREATE SESSION FORM ── -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
 <h2 class="font-bold text-lg text-slate-700 mb-5 flex items-center gap-2">
 <i class="fas fa-plus-circle text-primary"></i>
 إنشاء جلسة محاضرة جديدة
 </h2>
 <?php if (empty($courses)): ?>
 <div class="bg-bg border border-primary text-primary p-5 rounded-xl text-center font-bold">
 <i class="fas fa-info-circle text-2xl mb-2 block"></i>
 لا توجد مقررات مخصصة لك. تواصل مع الإدارة لإضافة مقرراتك إلى الجدول.
 </div>
 <?php else: ?>
 <form method="POST" class="space-y-4">
 <input type="hidden" name="action" value="create_session">
 <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
 <div>
 <label class="block text-sm font-bold text-slate-600 mb-1.5">المقرر الدراسي <span class="text-primary">*</span></label>
 <select name="course_id" required class="w-full bg-bg border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium outline-none focus:ring-2 focus:ring-primary">
 <option value="">— اختر المقرر —</option>
 <?php foreach ($courses as $c): ?>
 <option value="<?php echo $c['id']; ?>">
 <?php echo htmlspecialchars($c['name']); ?>
 (<?php echo htmlspecialchars($c['code'] ?? ''); ?> — الفرقة <?php echo $c['level']; ?>)
 <?php echo $c['college_name'] ? '— ' . htmlspecialchars($c['college_name']) : ''; ?>
 </option>
 <?php endforeach; ?>
 </select>
 </div>
 <div>
 <label class="block text-sm font-bold text-slate-600 mb-1.5">مدة صلاحية QR Code</label>
 <select name="duration_min" class="w-full bg-bg border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium outline-none focus:ring-2 focus:ring-primary">
 <option value="5">5 دقائق</option>
 <option value="10" selected>10 دقائق</option>
 <option value="15">15 دقيقة</option>
 <option value="30">30 دقيقة</option>
 </select>
 </div>
 </div>
 <div class="bg-bg border border-primary rounded-xl p-4 text-sm text-primary font-medium flex items-start gap-2">
 <i class="fas fa-info-circle mt-0.5 shrink-0"></i>
 <span>بعد إنشاء الجلسة، سيظهر لك رمز QR. يقوم الطلاب بمسحه ومشاركة موقعهم. سيُقبل الحضور إذا كانوا ضمن 1 كيلومتر من الكلية.</span>
 </div>
 <button type="submit" class="bg-primary hover:bg-accent hover:text-white text-white px-8 py-3 rounded-xl font-bold transition shadow flex items-center gap-2">
 <i class="fas fa-qrcode"></i> إنشاء الجلسة وتوليد QR Code
 </button>
 </form>
 <?php endif; ?>
 </div>

 <?php else: ?>
 <!-- ── ACTIVE SESSION VIEW ── -->
 <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

 <!-- Left: QR + Info -->
 <div class="space-y-5">
 <!-- Session Info -->
 <div class="bg-bg rounded-2xl p-5 text-white">
 <div class="flex items-start justify-between gap-3">
 <div>
 <p class="text-primary text-xs font-bold uppercase tracking-wider">المحاضرة الحالية</p>
 <h2 class="text-xl font-bold mt-1"><?php echo htmlspecialchars($active_session['course_name']); ?></h2>
 <p class="text-primary text-sm font-mono mt-0.5"><?php echo htmlspecialchars($active_session['course_code'] ?? ''); ?></p>
 </div>
 <?php if ($active_session['status'] === 'active'): ?>
 <span class="flex items-center gap-1.5 bg-primary/20 border border-primary/30 rounded-xl px-3 py-1.5 text-primary text-xs font-bold shrink-0">
 <span class="w-2 h-2 bg-primary rounded-full pulse-ring"></span> نشطة
 </span>
 <?php else: ?>
 <span class="bg-primary/20 border border-primary/30 rounded-xl px-3 py-1.5 text-primary text-xs font-bold">
 <?php echo $active_session['status'] === 'ended' ? 'منتهية' : 'منتهية الصلاحية'; ?>
 </span>
 <?php endif; ?>
 </div>

 <div class="mt-4 grid grid-cols-2 gap-3">
 <div class="bg-white/10 border border-white/20 rounded-xl px-4 py-2.5">
 <p class="text-primary text-xs font-bold">الكلية</p>
 <p class="font-bold text-sm mt-0.5"><?php echo htmlspecialchars($active_session['college_name'] ?? 'غير محددة'); ?></p>
 </div>
 <div class="bg-white/10 border border-white/20 rounded-xl px-4 py-2.5">
 <p class="text-primary text-xs font-bold">الوقت المتبقي</p>
 <p class="font-bold text-xl mt-0.5" id="countdown">
 <?php echo $active_session['status'] === 'active' ? '--:--' : '00:00'; ?>
 </p>
 </div>
 </div>
 </div>

 <!-- QR Code -->
 <?php if ($active_session['status'] === 'active'): ?>
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 text-center">
 <p class="text-sm font-bold text-slate-500 mb-4">اطلب من الطلاب مسح الرمز أدناه</p>
 <div class="flex justify-center mb-4">
 <div class="qr-wrapper" id="qrcode"></div>
 </div>
 <p class="text-xs text-slate-400 font-mono break-all select-all bg-bg p-2 rounded-lg" id="attend-url">
 <?php echo htmlspecialchars($attend_url); ?>
 </p>
 <div class="mt-4 flex gap-3 justify-center no-print">
 <button onclick="window.print()" class="bg-bg text-slate-600 px-4 py-2 rounded-xl text-sm font-bold hover:bg-slate-200 transition">
 <i class="fas fa-print ml-1"></i> طباعة QR
 </button>
 <a href="?end=<?php echo $active_session['id']; ?>"
 onclick="return confirm('هل تريد إنهاء الجلسة الآن؟')"
 class="bg-bg text-primary border border-primary px-4 py-2 rounded-xl text-sm font-bold hover:bg-primary hover:text-white transition">
 <i class="fas fa-stop-circle ml-1"></i> إنهاء الجلسة
 </a>
 </div>
 </div>
 <?php else: ?>
 <div class="bg-bg border border-slate-200 rounded-2xl p-6 text-center">
 <i class="fas fa-lock text-3xl text-slate-300 mb-3 block"></i>
 <p class="font-bold text-slate-500">انتهت صلاحية الجلسة. لا يمكن تسجيل الحضور.</p>
 <a href="session_report.php?session_id=<?php echo $active_session['id']; ?>"
 class="mt-4 inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-bold hover:bg-accent hover:text-white transition">
 <i class="fas fa-chart-bar"></i> عرض التقرير النهائي
 </a>
 </div>
 <?php endif; ?>
 </div>

 <!-- Right: Live Attendance -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
 <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
 <h3 class="font-bold text-slate-700 flex items-center gap-2">
 <i class="fas fa-users text-primary"></i>
 الطلاب الحاضرون
 <span class="bg-primary text-white text-xs font-bold px-2 py-0.5 rounded-full" id="present-badge">
 <?php echo count($live_attendees); ?>
 </span>
 </h3>
 <?php if ($active_session['status'] === 'active'): ?>
 <span class="text-xs text-slate-400 font-medium flex items-center gap-1">
 <span class="w-2 h-2 bg-accent rounded-full pulse-ring"></span> تحديث تلقائي
 </span>
 <?php endif; ?>
 </div>

 <div id="attendees-list" class="max-h-[500px] overflow-y-auto divide-y divide-slate-50">
 <?php if (empty($live_attendees)): ?>
 <div id="empty-msg" class="p-10 text-center text-slate-400 text-sm font-medium">
 <i class="fas fa-user-clock text-2xl mb-2 block text-slate-300"></i>
 لم يسجل أحد حضوره بعد...
 </div>
 <?php else: ?>
 <?php foreach ($live_attendees as $att): ?>
 <div class="flex items-center gap-3 px-5 py-3">
 <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white font-bold text-sm shrink-0
 <?php echo $att['status'] === 'suspicious' ? 'bg-primary' : 'bg-primary'; ?>">
 <?php echo mb_substr($att['full_name'], 0, 1, 'UTF-8'); ?>
 </div>
 <div class="flex-1 min-w-0">
 <div class="font-bold text-slate-800 text-sm truncate"><?php echo htmlspecialchars($att['full_name']); ?></div>
 <div class="text-xs text-slate-400">
 <?php echo date('H:i:s', strtotime($att['marked_at'])); ?>
 <?php if ($att['distance_m'] !== null): ?>
 — <?php echo round($att['distance_m']); ?>م
 <?php endif; ?>
 </div>
 </div>
 <span class="text-xs font-bold px-2.5 py-1 rounded-full
 <?php echo $att['status'] === 'suspicious' ? 'bg-primary text-white' : 'bg-primary text-white'; ?>">
 <?php echo $att['status'] === 'suspicious' ? 'مشبوه' : 'حاضر'; ?>
 </span>
 </div>
 <?php endforeach; ?>
 <?php endif; ?>
 </div>

 <div class="px-5 py-3 bg-bg border-t border-slate-100">
 <a href="session_report.php?session_id=<?php echo $active_session['id']; ?>"
 class="text-sm text-primary font-bold hover:underline flex items-center gap-1">
 <i class="fas fa-external-link-alt text-xs"></i> فتح التقرير التفصيلي
 </a>
 </div>
 </div>
 </div>
 <?php endif; ?>

</div>

<!-- QR Code JS (client-side, no external API) -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
<?php if ($active_session && $active_session['status'] === 'active'): ?>
// ── Generate QR Code ──────────────────────────────────────────────────────────
const attendUrl = <?php echo json_encode($attend_url); ?>;
new QRCode(document.getElementById("qrcode"), {
 text: attendUrl,
 width: 260,
 height: 260,
 colorDark: "#2563eb",
 colorLight: "#ffffff",
 correctLevel: QRCode.CorrectLevel.H
});

// ── Countdown Timer ───────────────────────────────────────────────────────────
let expiresAt = new Date(<?php echo json_encode($active_session['expires_at']); ?>.replace(' ', 'T') + 'Z');
// Adjust for server timezone offset: calculate difference
let serverNow = new Date(<?php echo json_encode(date('Y-m-d H:i:s')); ?>.replace(' ', 'T'));
let localNow = new Date();
let tzOffset = serverNow - localNow; // rough ms offset

function updateCountdown() {
 let now = new Date();
 let diff = expiresAt.getTime() - tzOffset - now.getTime();
 if (diff <= 0) {
 document.getElementById('countdown').textContent = '00:00';
 document.getElementById('countdown').classList.add('text-primary');
 clearInterval(cdTimer);
 // Auto reload to show expired state
 setTimeout(() => location.reload(), 2000);
 return;
 }
 let mins = Math.floor(diff / 60000);
 let secs = Math.floor((diff % 60000) / 1000);
 document.getElementById('countdown').textContent =
 String(mins).padStart(2,'0') + ':' + String(secs).padStart(2,'0');
 if (diff < 60000) document.getElementById('countdown').classList.add('text-primary');
}
updateCountdown();
const cdTimer = setInterval(updateCountdown, 1000);

// ── Live Attendance AJAX Poll ─────────────────────────────────────────────────
const sessionId = <?php echo (int)$active_session['id']; ?>;

function pollAttendance() {
 fetch(`session_report.php?session_id=${sessionId}&format=json`)
 .then(r => r.json())
 .then(data => {
 document.getElementById('present-badge').textContent = data.count;
 if (data.count === 0) return;
 const list = document.getElementById('attendees-list');
 const emptyMsg = document.getElementById('empty-msg');
 if (emptyMsg) emptyMsg.remove();
 list.innerHTML = data.rows.map(row => `
 <div class="flex items-center gap-3 px-5 py-3 border-b border-slate-50">
 <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white font-bold text-sm shrink-0 ${row.status === 'suspicious' ? 'bg-primary' : 'bg-primary'}">
 ${row.full_name.charAt(0)}
 </div>
 <div class="flex-1 min-w-0">
 <div class="font-bold text-slate-800 text-sm truncate">${row.full_name}</div>
 <div class="text-xs text-slate-400">${row.marked_at}${row.distance_m ? ' — ' + Math.round(row.distance_m) + 'م' : ''}</div>
 </div>
 <span class="text-xs font-bold px-2.5 py-1 rounded-full ${row.status === 'suspicious' ? 'bg-primary text-white' : 'bg-primary text-white'}">
 ${row.status === 'suspicious' ? 'مشبوه' : 'حاضر'}
 </span>
 </div>
 `).join('');
 })
 .catch(() => {});
}
// Poll every 5 seconds
setInterval(pollAttendance, 5000);
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
