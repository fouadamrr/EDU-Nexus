<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../database/db_connection.php';
$pdo = get_pdo();

$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';

if ($role !== 'student' || $user_id <= 0) {
 header("Location: ../index.php"); exit;
}

$exam_id = (int)($_GET['exam_id'] ?? 0);
if ($exam_id <= 0) { header("Location: student_exams.php"); exit; }

// ── Load exam ──────────────────────────────────────────────────────────────
$exam = null;
try {
 $stmt = $pdo->prepare("SELECT oe.*, c.name AS course_name FROM online_exams oe LEFT JOIN courses c ON c.id=oe.course_id WHERE oe.id=?");
 $stmt->execute([$exam_id]);
 $exam = $stmt->fetch();
} catch(Exception $e){}

if (!$exam) { echo "<p>امتحان غير موجود.</p>"; exit; }

// ── Check exam is active and within time window ──────────────────────────
$now = new DateTime();
$is_active = $exam['status'] === 'active';
$time_ok = true;
$time_msg = 'الامتحان غير متاح حالياً.';

if ($exam['start_time']) {
 try {
 $start = new DateTime($exam['start_time']);
 if ($now < $start) {
 $time_ok = false;
 $time_msg = 'الامتحان لم يبدأ بعد. يبدأ في: <strong>' . $start->format('Y-m-d H:i') . '</strong>';
 }
 } catch(Exception $e){}
}
if ($exam['end_time']) {
 try {
 $end = new DateTime($exam['end_time']);
 if ($now > $end) {
 $time_ok = false;
 $time_msg = 'انتهى وقت الامتحان. كان متاحاً حتى: <strong>' . $end->format('Y-m-d H:i') . '</strong>';
 }
 } catch(Exception $e){}
}

if (!$is_active || !$time_ok) {
 $status_msg = !$is_active ? 'الامتحان غير مفعّل حالياً.' : $time_msg;
 echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head>
 <meta charset="UTF-8">
 <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;800&display=swap" rel="stylesheet">
 <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
 </head>
 <body style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:Cairo,sans-serif;background:#f1f5f9;margin:0;">
 <div style="text-align:center;color:#64748b;background:white;border-radius:20px;padding:40px;box-shadow:0 4px 20px rgba(0,0,0,.08);max-width:440px;">
 <div style="font-size:3rem;margin-bottom:16px;">🔒</div>
 <h2 style="color:#1e293b;font-weight:800;margin-bottom:12px;">الامتحان غير متاح</h2>
 <p style="margin-bottom:20px;line-height:1.7;">' . $status_msg . '</p>
 <a href="student_exams.php" style="display:inline-flex;align-items:center;gap:8px;background:#6366f1;color:white;padding:12px 24px;border-radius:12px;font-weight:800;text-decoration:none;">
 <i class="fas fa-arrow-right"></i> العودة للامتحانات
 </a>
 </div></body></html>';
 exit;
}

// ── Check for existing submission ──────────────────────────────────────────
$existing = null;
try {
 $stmt = $pdo->prepare("SELECT * FROM online_submissions WHERE exam_id=? AND student_id=?");
 $stmt->execute([$exam_id, $user_id]);
 $existing = $stmt->fetch();
} catch(Exception $e){}

if ($existing && $existing['status'] === 'submitted') {
 header("Location: exam_results.php?submission_id={$existing['id']}"); exit;
}

// ── Start submission if not started already ───────────────────────────────
$submission_id = null;
if (!$existing) {
 try {
 $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
 $stmt = $pdo->prepare("INSERT INTO online_submissions (exam_id, student_id, ip_address) VALUES (?,?,?) RETURNING id");
 $stmt->execute([$exam_id, $user_id, $ip]);
 $submission_id = $stmt->fetchColumn();

 // Log start
 $pdo->prepare("INSERT INTO exam_logs (exam_id, student_id, action, ip_address, user_agent) VALUES (?,?,?,?,?)")
 ->execute([$exam_id, $user_id, 'started', $ip, $_SERVER['HTTP_USER_AGENT'] ?? '']);
 } catch(Exception $e){}
} else {
 $submission_id = $existing['id'];
}

// ── Load questions ─────────────────────────────────────────────────────────
$questions = [];
try {
 $stmt = $pdo->prepare("SELECT * FROM online_questions WHERE exam_id=? ORDER BY order_index");
 $stmt->execute([$exam_id]);
 $questions = $stmt->fetchAll();

 // Randomize if exam setting says so
 if ($exam['randomize_q']) shuffle($questions);

 foreach ($questions as &$q) {
 $sc = $pdo->prepare("SELECT * FROM online_choices WHERE question_id=? ORDER BY order_index");
 $sc->execute([$q['id']]);
 $q['choices'] = $sc->fetchAll();
 // Shuffle MCQ choices per question too
 if ($q['type'] === 'mcq' && $exam['randomize_q']) shuffle($q['choices']);
 }
 unset($q);
} catch(Exception $e){}

// Duration in seconds for JS
$duration_sec = (int)($exam['duration_min']) * 60;

// Calculate elapsed time if already started
$elapsed = 0;
if ($existing && $existing['started_at']) {
 $started = new DateTime($existing['started_at']);
 $elapsed = (int)($now->getTimestamp() - $started->getTimestamp());
 if ($elapsed >= $duration_sec) {
 // Time expired — force submit
 header("Location: submit_exam.php?submission_id={$submission_id}&auto=1"); exit;
 }
}

$remaining_sec = max(0, $duration_sec - $elapsed);

$type_labels = ['quiz'=>'كويز','midterm'=>'اختبار ترمي','assignment'=>'واجب'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($exam['title']); ?> | EDU Nexus</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family: 'Cairo', sans-serif; background: #f1f5f9; margin: 0; color: #1e293b; }
.exam-header {
 position: sticky; top: 0; z-index: 100;
 background: #1e293b; color: white; padding: 0 24px;
 display: flex; align-items: center; justify-content: space-between; min-height: 64px;
 box-shadow: 0 4px 12px rgba(0,0,0,.3);
}
.exam-title { font-weight: 800; font-size: 1rem; }
.exam-course { font-size: .8rem; color: #94a3b8; }
.timer-box {
 display: flex; align-items: center; gap: 10px;
 background: rgba(255,255,255,.1); border-radius: 12px; padding: 8px 18px;
 font-size: 1.4rem; font-weight: 800; font-family: monospace;
 border: 2px solid rgba(255,255,255,.15);
 transition: background .5s;
}
.timer-box.warning { background: rgba(245,158,11,.3); border-color: rgba(245,158,11,.5); color: #fde68a; }
.timer-box.critical { background: rgba(239,68,68,.3); border-color: rgba(239,68,68,.5); color: #fca5a5; animation: pulse 1s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.7} }
.progress-bar-wrap { background: rgba(255,255,255,.1); border-radius: 999px; height: 6px; width: 200px; overflow: hidden; }
.progress-bar-fill { height: 100%; background: #6366f1; border-radius: 999px; transition: width .5s; }
.main-content { max-width: 860px; margin: 0 auto; padding: 24px 16px 100px; }
.question-card {
 background: white; border-radius: 16px; padding: 24px; margin-bottom: 20px;
 border: 2px solid #e2e8f0; transition: border-color .2s;
 position: relative;
}
.question-card:focus-within { border-color: #6366f1; }
.q-num { position:absolute; top:-14px; right:20px; background:#6366f1; color:white; padding:4px 14px; border-radius:999px; font-size:.75rem; font-weight:800; }
.q-type-badge { display:inline-block; padding:2px 10px; border-radius:6px; font-size:.7rem; font-weight:700; margin-bottom:10px; }
.q-type-mcq { background:#ede9fe; color:#7c3aed; }
.q-type-true_false{ background:#dcfce7; color:#166534; }
.q-type-essay { background:#fef3c7; color:#92400e; }
.question-text { font-size:1rem; font-weight:700; color:#1e293b; line-height:1.6; margin-bottom:16px; }
.choice-label {
 display:flex; align-items:center; gap:12px;
 padding:12px 16px; border-radius:12px; border:2px solid #e2e8f0;
 cursor:pointer; transition:.2s; margin-bottom:10px; user-select:none;
}
.choice-label:hover { border-color:#6366f1; background:#eef2ff; }
.choice-label input[type=radio] { width:18px; height:18px; accent-color:#6366f1; flex-shrink:0; }
.choice-label.selected { border-color:#6366f1; background:#eef2ff; }
.essay-textarea {
 width:100%; border:2px solid #e2e8f0; border-radius:12px; padding:14px;
 font-family:'Cairo',sans-serif; font-size:.95rem; resize:vertical; min-height:120px;
 transition:border-color .2s; outline:none;
}
.essay-textarea:focus { border-color:#6366f1; }
.answered-dot { width:10px; height:10px; border-radius:50%; background:#6366f1; display:inline-block; }
.unanswered-dot { width:10px; height:10px; border-radius:50%; background:#e2e8f0; display:inline-block; }
.submit-bar {
 position:fixed; bottom:0; left:0; right:0; background:white;
 border-top:2px solid #e2e8f0; padding:14px 24px; display:flex;
 align-items:center; justify-content:space-between; gap:12px; z-index:90;
}
.submit-btn {
 background:#6366f1; color:white; padding:12px 32px; border-radius:12px;
 font-weight:800; font-size:1rem; border:none; cursor:pointer; transition:.2s;
 display:flex; align-items:center; gap:8px;
}
.submit-btn:hover { background:#4f46e5; }
.nav-dots { display:flex; gap:6px; flex-wrap:wrap; max-width:380px; }
#warning-overlay {
 display:none; position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:999;
 align-items:center; justify-content:center;
}
#warning-box {
 background:white; border-radius:20px; padding:32px; text-align:center; max-width:400px;
 animation:bounce-in .3s;
}
@keyframes bounce-in { 0%{transform:scale(.8)} 100%{transform:scale(1)} }
.cheating-count { position:fixed; top:70px; right:16px; background:#dc2626; color:white; padding:6px 14px; border-radius:999px; font-size:.8rem; font-weight:700; display:none; z-index:200; }
</style>
</head>
<body>

<!-- Anti-Cheat Warning Overlay -->
<div id="warning-overlay" style="display:flex;display:none">
 <div id="warning-box">
 <div style="font-size:3rem;margin-bottom:12px;">⚠️</div>
 <h2 style="color:#dc2626;font-weight:800;margin-bottom:8px;" id="warning-title">تحذير!</h2>
 <p style="color:#64748b;margin-bottom:20px;" id="warning-msg">تم رصد محاولة للغش. هذا التحذير سيُسجَّل.</p>
 <button onclick="dismissWarning()" style="background:#dc2626;color:white;padding:10px 28px;border-radius:10px;font-weight:800;border:none;cursor:pointer;font-family:Cairo,sans-serif;">
 العودة للامتحان
 </button>
 </div>
</div>

<!-- Cheat Counter Badge -->
<div class="cheating-count" id="cheatCount">⚠ مخالفات: <span id="cheatNum">0</span></div>

<!-- Exam Header -->
<div class="exam-header">
 <div>
 <div class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></div>
 <div class="exam-course"><?php echo htmlspecialchars($exam['course_name'] ?? ''); ?></div>
 </div>
 <div class="flex items-center gap-4">
 <!-- Timer -->
 <div class="timer-box" id="timerBox">
 <i class="fas fa-clock"></i>
 <span id="timerDisplay">--:--</span>
 </div>
 <!-- Progress -->
 <div style="text-align:center;">
 <div style="font-size:.7rem;color:#94a3b8;margin-bottom:4px;">التقدم</div>
 <div class="progress-bar-wrap">
 <div class="progress-bar-fill" id="progressFill" style="width:0%"></div>
 </div>
 <div style="font-size:.7rem;color:#94a3b8;margin-top:3px;"><span id="answeredCount">0</span> / <?php echo count($questions); ?></div>
 </div>
 </div>
</div>

<!-- Main Content -->
<div class="main-content">
 <form id="examForm" method="POST" action="submit_exam.php">
 <input type="hidden" name="submission_id" value="<?php echo $submission_id; ?>">
 <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
 <input type="hidden" name="cheat_count" id="hiddenCheatCount" value="0">

 <?php foreach($questions as $idx => $q): ?>
 <div class="question-card" id="qcard-<?php echo $q['id']; ?>">
 <div class="q-num">السؤال <?php echo $idx + 1; ?></div>
 <div class="q-type-badge q-type-<?php echo $q['type']; ?>">
 <?php 
 $tl = ['mcq'=>'اختيار متعدد','true_false'=>'صح / خطأ','essay'=>'مقال'];
 echo $tl[$q['type']] ?? $q['type'];
 ?>
 <span style="margin-right:6px;opacity:.7;">(<?php echo $q['points']; ?> نقطة)</span>
 </div>
 <div class="question-text"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></div>

 <?php if ($q['type'] === 'mcq' || $q['type'] === 'true_false'): ?>
 <?php foreach ($q['choices'] as $c): ?>
 <label class="choice-label" id="label-<?php echo $c['id']; ?>" onclick="markAnswered(<?php echo $q['id']; ?>)">
 <input type="radio" name="answer[<?php echo $q['id']; ?>]" value="<?php echo $c['id']; ?>">
 <span><?php echo htmlspecialchars($c['choice_text']); ?></span>
 </label>
 <?php endforeach; ?>

 <?php elseif ($q['type'] === 'essay'): ?>
 <textarea
 class="essay-textarea"
 name="essay[<?php echo $q['id']; ?>]"
 placeholder="اكتب إجابتك هنا..."
 oninput="markAnswered(<?php echo $q['id']; ?>)"
 onchange="markAnswered(<?php echo $q['id']; ?>)"
 ></textarea>
 <?php endif; ?>
 </div>
 <?php endforeach; ?>

 <!-- Submit handled by bottom bar -->
 </form>
</div>

<!-- Bottom Submit Bar -->
<div class="submit-bar">
 <div class="nav-dots" id="navDots">
 <?php foreach($questions as $idx => $q): ?>
 <div class="unanswered-dot" id="dot-<?php echo $q['id']; ?>" title="سؤال <?php echo $idx+1; ?>" style="cursor:pointer;" onclick="document.getElementById('qcard-<?php echo $q['id']; ?>').scrollIntoView({behavior:'smooth'})"></div>
 <?php endforeach; ?>
 </div>
 <button type="button" class="submit-btn" onclick="confirmSubmit()">
 <i class="fas fa-paper-plane"></i> تسليم الامتحان
 </button>
</div>

<script>
// ── Timer ──────────────────────────────────────────────────────────────────
let remaining = <?php echo $remaining_sec; ?>;
const totalSec = <?php echo $duration_sec; ?>;
const timerEl = document.getElementById('timerDisplay');
const timerBox = document.getElementById('timerBox');

function formatTime(sec) {
 const m = Math.floor(sec / 60).toString().padStart(2,'0');
 const s = (sec % 60).toString().padStart(2,'0');
 return m + ':' + s;
}

function tick() {
 timerEl.textContent = formatTime(remaining);
 const pct = (remaining / totalSec) * 100;
 if (remaining <= 60) { timerBox.className = 'timer-box critical'; }
 else if (remaining <= 300) { timerBox.className = 'timer-box warning'; }

 if (remaining <= 0) {
 document.getElementById('examForm').submit();
 return;
 }
 remaining--;
 setTimeout(tick, 1000);
}
tick();

// ── Progress Tracker ───────────────────────────────────────────────────────
const answeredQuestions = new Set();
const total = <?php echo count($questions); ?>;

function markAnswered(qid) {
 answeredQuestions.add(qid);
 const dot = document.getElementById('dot-' + qid);
 if (dot) { dot.className = 'answered-dot'; }
 document.getElementById('answeredCount').textContent = answeredQuestions.size;
 const pct = (answeredQuestions.size / total) * 100;
 document.getElementById('progressFill').style.width = pct + '%';
}

// Style selected radio choices
document.querySelectorAll('input[type=radio]').forEach(radio => {
 radio.addEventListener('change', function() {
 const group = this.name;
 document.querySelectorAll(`input[name="${group}"]`).forEach(r => {
 r.closest('label').classList.remove('selected');
 });
 this.closest('label').classList.add('selected');
 });
});

// ── Submit Confirmation ────────────────────────────────────────────────────
function confirmSubmit() {
 const unanswered = total - answeredQuestions.size;
 const msg = unanswered > 0
 ? `لديك ${unanswered} سؤال لم تجب عليه بعد. هل تريد التسليم الآن؟`
 : 'هل أنت متأكد من تسليم الامتحان؟';
 if (confirm(msg)) {
 document.getElementById('hiddenCheatCount').value = cheatCount;
 document.getElementById('examForm').submit();
 }
}

// ── Anti-Cheat: Tab switch & Page visibility ───────────────────────────────
let cheatCount = 0;

function logSuspicion(action, detail) {
 cheatCount++;
 document.getElementById('cheatNum').textContent = cheatCount;
 document.getElementById('cheatCount').style.display = 'block';
 
 // Log to server via beacon (non-blocking)
 const data = new FormData();
 data.append('exam_id', <?php echo $exam_id; ?>);
 data.append('student_id', <?php echo $user_id; ?>);
 data.append('action', action);
 data.append('details', detail);
 navigator.sendBeacon('log_action.php', data);

 // Show warning overlay
 document.getElementById('warning-title').textContent = 'تحذير أمني!';
 document.getElementById('warning-msg').textContent = `تم رصد: ${detail}. تم تسجيل هذا الحدث.`;
 document.getElementById('warning-overlay').style.display = 'flex';
}

function dismissWarning() {
 document.getElementById('warning-overlay').style.display = 'none';
 window.focus();
}

// Page visibility (tab switch)
document.addEventListener('visibilitychange', function() {
 if (document.hidden) {
 logSuspicion('tab_switch', 'المستخدم غادر صفحة الامتحان');
 }
});

// Disable right-click
document.addEventListener('contextmenu', e => { e.preventDefault(); logSuspicion('copy_attempt', 'محاولة فتح قائمة السياق'); });

// Disable copy/paste
document.addEventListener('copy', e => { e.preventDefault(); logSuspicion('copy_attempt', 'محاولة نسخ المحتوى'); });
document.addEventListener('cut', e => { e.preventDefault(); });

// Disable keyboard shortcuts
document.addEventListener('keydown', function(e) {
 if ((e.ctrlKey || e.metaKey) && ['c','v','u','s','p','a'].includes(e.key.toLowerCase())) {
 e.preventDefault();
 if(e.key.toLowerCase()==='c'||e.key.toLowerCase()==='u') logSuspicion('copy_attempt', 'ضغط مختصر نسخ: Ctrl+' + e.key.toUpperCase());
 }
 if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I')) {
 e.preventDefault();
 logSuspicion('copy_attempt', 'محاولة فتح أدوات المطور');
 }
});

// Warn on page refresh
window.addEventListener('beforeunload', function(e) {
 if (remaining > 0 && answeredQuestions.size > 0) {
 logSuspicion('page_refresh', 'محاولة إغلاق/تحديث الصفحة أثناء الامتحان');
 const msg = 'ستفقد تقدمك إذا غادرت!';
 e.returnValue = msg;
 return msg;
 }
});
</script>
</body>
</html>
