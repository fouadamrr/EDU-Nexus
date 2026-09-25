<?php
// ── Bootstrap ───────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit; }

require_once '../database/db_connection.php';
$pdo = get_pdo();
$role = $_SESSION['role'] ?? 'student';
$user_id = (int)$_SESSION['user_id'];

if (!in_array($role, ['instructor','admin','super_admin','dean'])) {
 header('Location: ../index.php'); exit;
}

$exam_id = (int)($_GET['id'] ?? 0);
if ($exam_id <= 0) { header('Location: manage_exam.php'); exit; }

// Load exam
try {
 $stmt = $pdo->prepare("SELECT oe.*, c.name AS course_name FROM online_exams oe LEFT JOIN courses c ON c.id=oe.course_id WHERE oe.id=?");
 $stmt->execute([$exam_id]);
 $exam = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) { $exam = null; }

if (!$exam) { header('Location: manage_exam.php'); exit; }

// Load courses for dropdown
$courses = [];
try {
 if (in_array($role, ['admin','super_admin'])) {
 $courses = $pdo->query("SELECT id, code, name FROM courses ORDER BY name")->fetchAll();
 } elseif ($role === 'dean') {
 $cid = (int)($_SESSION['college_id'] ?? 0);
 $s = $pdo->prepare("SELECT id, code, name FROM courses WHERE college_id=? ORDER BY name");
 $s->execute([$cid]); $courses = $s->fetchAll();
 } else {
 $s = $pdo->prepare("SELECT id, code, name FROM courses WHERE instructor_id=? OR doctor_id=? ORDER BY name");
 $s->execute([$user_id, $user_id]); $courses = $s->fetchAll();
 }
} catch(Exception $e) {}

$message = '';

// Handle POST ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $title = trim($_POST['title'] ?? '');
 $course_id = (int)($_POST['course_id'] ?? 0);
 $type = in_array($_POST['type'] ?? '', ['quiz','midterm','assignment']) ? $_POST['type'] : 'quiz';
 $duration = max(1, (int)($_POST['duration_min'] ?? 30));
 $raw_start = trim($_POST['start_time'] ?? '');
 $raw_end = trim($_POST['end_time'] ?? '');
 $start_time = !empty($raw_start) ? $raw_start : null;
 $end_time = !empty($raw_end) ? $raw_end : null;
 $status = ($_POST['status'] ?? 'inactive') === 'active' ? 'active' : 'inactive';
 $randomize = isset($_POST['randomize_q']);
 $show_results = isset($_POST['show_results']);
 $pass_score = min(100, max(0, (float)($_POST['pass_score'] ?? 50)));
 $description = trim($_POST['description'] ?? '');

 // Time validation
 $time_error = '';
 if ($start_time && $end_time && strtotime($end_time) <= strtotime($start_time)) {
 $time_error = 'وقت الانتهاء يجب أن يكون بعد وقت البداية.';
 }

 if (!empty($title) && $course_id > 0 && !$time_error) {
 try {
 $upd = $pdo->prepare("
 UPDATE online_exams SET
 course_id=:course_id, title=:title, description=:description,
 type=:type, duration_min=:duration_min,
 start_time=:start_time, end_time=:end_time, status=:status,
 randomize_q=:randomize_q, show_results=:show_results, pass_score=:pass_score
 WHERE id=:id
 ");
 $upd->bindValue(':course_id', $course_id, PDO::PARAM_INT);
 $upd->bindValue(':title', $title, PDO::PARAM_STR);
 $upd->bindValue(':description', $description, PDO::PARAM_STR);
 $upd->bindValue(':type', $type, PDO::PARAM_STR);
 $upd->bindValue(':duration_min', $duration, PDO::PARAM_INT);
 $upd->bindValue(':status', $status, PDO::PARAM_STR);
 // TIMESTAMPTZ — explicit NULL
 if (!empty($start_time)) {
 $upd->bindValue(':start_time', $start_time, PDO::PARAM_STR);
 } else {
 $upd->bindValue(':start_time', null, PDO::PARAM_NULL);
 }
 if (!empty($end_time)) {
 $upd->bindValue(':end_time', $end_time, PDO::PARAM_STR);
 } else {
 $upd->bindValue(':end_time', null, PDO::PARAM_NULL);
 }
 // BOOLEAN — PostgreSQL requires 'true'/'false' strings
 $upd->bindValue(':randomize_q', $randomize ? 'true' : 'false', PDO::PARAM_STR);
 $upd->bindValue(':show_results', $show_results ? 'true' : 'false', PDO::PARAM_STR);
 $upd->bindValue(':pass_score', $pass_score, PDO::PARAM_STR);
 $upd->bindValue(':id', $exam_id, PDO::PARAM_INT);
 $upd->execute();
 $message = '<div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:14px 18px;border-radius:12px;margin-bottom:16px;font-weight:700;">
 <i class="fas fa-check-circle me-2"></i>تم تحديث الامتحان بنجاح!
 </div>';
 // Reload exam data
 $stmt->execute([$exam_id]);
 $exam = $stmt->fetch(PDO::FETCH_ASSOC);
 } catch(Exception $e) {
 $message = '<div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:14px 18px;border-radius:12px;margin-bottom:16px;font-weight:700;">
 <i class="fas fa-bug me-2"></i>خطأ: ' . htmlspecialchars($e->getMessage()) . '
 </div>';
 }
 } elseif ($time_error) {
 $message = '<div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:14px 18px;border-radius:12px;margin-bottom:16px;font-weight:700;">'.$time_error.'</div>';
 } else {
 $message = '<div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:14px 18px;border-radius:12px;margin-bottom:16px;font-weight:700;">يرجى ملء العنوان واختيار المقرر.</div>';
 }
}




require_once '../includes/header.php';

// Format dates for display in flatpickr
$start_display = $exam['start_time'] ? date('Y-m-d H:i', strtotime($exam['start_time'])) : '';
$end_display = $exam['end_time'] ? date('Y-m-d H:i', strtotime($exam['end_time'])) : '';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" onerror="this.remove()">
<style>
.exam-type-card { cursor:pointer; border:2px solid #e2e8f0; border-radius:12px; padding:16px; text-align:center; transition:all .2s; }
.exam-type-card:hover, .exam-type-card.selected { border-color:#4f46e5; background:#eef2ff; }
.exam-type-card.selected .type-icon { color:#4f46e5; }
.type-icon { font-size:1.8rem; margin-bottom:8px; color:#94a3b8; }
.form-section { background:#fff; border-radius:16px; padding:24px; box-shadow:0 1px 4px rgba(0,0,0,.06); border:1px solid #f1f5f9; margin-bottom:20px; }
.section-title { font-size:1rem; font-weight:700; color:#1e293b; border-bottom:2px solid #f1f5f9; padding-bottom:12px; margin-bottom:18px; display:flex; align-items:center; gap:8px; }
</style>

<div class="max-w-4xl mx-auto pb-10">
 <!-- Header -->
 <div class="form-section">
 <div class="flex items-center gap-4">
 <div class="w-14 h-14 rounded-2xl bg-primary flex items-center justify-center shadow-lg shadow-sm">
 <i class="fas fa-edit text-white text-2xl"></i>
 </div>
 <div>
 <h1 class="text-2xl font-bold text-slate-800">تعديل الامتحان</h1>
 <p class="text-slate-500 text-sm mt-1"><?php echo htmlspecialchars($exam['title']); ?></p>
 </div>
 <a href="manage_exam.php" class="mr-auto bg-bg text-slate-600 px-4 py-2 rounded-xl font-bold hover:bg-slate-200 transition flex items-center gap-2 text-sm">
 <i class="fas fa-arrow-right"></i> رجوع
 </a>
 </div>
 </div>

 <?php echo $message; ?>

 <form method="POST" id="examForm" onsubmit="return validateForm()">

 <!-- Exam Type -->
 <div class="form-section">
 <div class="section-title"><i class="fas fa-shapes text-primary"></i> نوع الامتحان</div>
 <input type="hidden" name="type" id="typeInput" value="<?php echo htmlspecialchars($exam['type']); ?>">
 <div class="grid grid-cols-3 gap-4">
 <?php foreach(['quiz'=>['كويز سريع','fa-bolt'], 'midterm'=>['اختبار ترمي','fa-graduation-cap'], 'assignment'=>['واجب / تكليف','fa-tasks']] as $t=>[$label,$icon]): ?>
 <div class="exam-type-card <?php echo $exam['type']===$t?'selected':''; ?>" onclick="selectType('<?php echo $t; ?>',this)">
 <div class="type-icon"><i class="fas <?php echo $icon; ?>"></i></div>
 <div class="font-bold text-slate-700"><?php echo $label; ?></div>
 </div>
 <?php endforeach; ?>
 </div>
 </div>

 <!-- Basic Info -->
 <div class="form-section">
 <div class="section-title"><i class="fas fa-info-circle text-accent"></i> البيانات الأساسية</div>
 <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
 <div class="md:col-span-2">
 <label class="block text-sm font-bold text-slate-700 mb-2">عنوان الامتحان <span class="text-primary">*</span></label>
 <input type="text" name="title" required value="<?php echo htmlspecialchars($exam['title']); ?>"
 class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-bg focus:ring-2 focus:ring-indigo-300 focus:bg-white transition text-slate-800 font-medium">
 </div>
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">المقرر <span class="text-primary">*</span></label>
 <select name="course_id" required class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-bg focus:ring-2 focus:ring-indigo-300 focus:bg-white transition">
 <option value="">— اختر المقرر —</option>
 <?php foreach($courses as $c): ?>
 <option value="<?php echo $c['id']; ?>" <?php echo $exam['course_id']==$c['id']?'selected':''; ?>>
 <?php echo htmlspecialchars($c['code'] . ' — ' . $c['name']); ?>
 </option>
 <?php endforeach; ?>
 </select>
 </div>
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">مدة الامتحان (بالدقائق)</label>
 <input type="number" name="duration_min" value="<?php echo (int)$exam['duration_min']; ?>" min="1" max="300"
 class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-bg focus:ring-2 focus:ring-indigo-300 focus:bg-white transition">
 </div>
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">
 <i class="fas fa-play-circle text-primary me-1"></i> وقت البداية
 <span class="text-xs font-normal text-slate-400 mr-1">(اتركه فارغاً للبدء الفوري)</span>
 </label>
 <div style="position:relative;">
 <input type="text" id="startTimePicker" value="<?php echo $start_display; ?>" placeholder="اختر التاريخ والوقت..."
 class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-bg focus:ring-2 focus:ring-indigo-300 focus:bg-white transition" readonly>
 <input type="hidden" name="start_time" id="startTimeVal" value="<?php echo htmlspecialchars($exam['start_time']??''); ?>">
 <i class="fas fa-calendar-alt" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;"></i>
 </div>
 </div>
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">
 <i class="fas fa-stop-circle text-primary me-1"></i> وقت الانتهاء
 <span class="text-xs font-normal text-slate-400 mr-1">(اتركه فارغاً بدون نهاية)</span>
 </label>
 <div style="position:relative;">
 <input type="text" id="endTimePicker" value="<?php echo $end_display; ?>" placeholder="اختر التاريخ والوقت..."
 class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-bg focus:ring-2 focus:ring-indigo-300 focus:bg-white transition" readonly>
 <input type="hidden" name="end_time" id="endTimeVal" value="<?php echo htmlspecialchars($exam['end_time']??''); ?>">
 <i class="fas fa-calendar-alt" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;"></i>
 </div>
 <?php if($exam['end_time'] && $exam['end_time'] < date('Y-m-d H:i:s')): ?>
 <p style="color:#dc2626;font-size:.8rem;margin-top:6px;font-weight:700;">
 <i class="fas fa-exclamation-triangle"></i> تحذير: وقت النهاية انتهى! الطلاب لا يمكنهم رؤية الامتحان. اتركه فارغاً أو غيّره لوقت مستقبلي.
 </p>
 <?php endif; ?>
 </div>
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">درجة النجاح (%)</label>
 <input type="number" name="pass_score" value="<?php echo (float)$exam['pass_score']; ?>" min="0" max="100"
 class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-bg focus:ring-2 focus:ring-indigo-300 focus:bg-white transition">
 </div>
 <div class="md:col-span-2">
 <label class="block text-sm font-bold text-slate-700 mb-2">وصف الامتحان (اختياري)</label>
 <textarea name="description" rows="3" placeholder="تعليمات للطلاب..."
 class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-bg focus:ring-2 focus:ring-indigo-300 focus:bg-white transition resize-none"><?php echo htmlspecialchars($exam['description']??''); ?></textarea>
 </div>
 </div>
 </div>

 <!-- Settings -->
 <div class="form-section">
 <div class="section-title"><i class="fas fa-sliders-h text-primary"></i> إعدادات الامتحان</div>
 <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
 <label class="flex items-center gap-3 p-4 bg-bg rounded-xl border border-slate-200 cursor-pointer hover:bg-bg hover:border-primary transition">
 <input type="checkbox" name="randomize_q" <?php echo $exam['randomize_q']?'checked':''; ?> class="w-5 h-5 rounded text-primary">
 <div>
 <div class="font-bold text-slate-700 text-sm">ترتيب عشوائي</div>
 <div class="text-xs text-slate-400">ترتب الأسئلة عشوائياً لكل طالب</div>
 </div>
 </label>
 <label class="flex items-center gap-3 p-4 bg-bg rounded-xl border border-slate-200 cursor-pointer hover:bg-bg hover:border-primary transition">
 <input type="checkbox" name="show_results" <?php echo $exam['show_results']?'checked':''; ?> class="w-5 h-5 rounded text-primary">
 <div>
 <div class="font-bold text-slate-700 text-sm">إظهار النتيجة</div>
 <div class="text-xs text-slate-400">يرى الطالب نتيجته بعد التسليم</div>
 </div>
 </label>
 <div class="p-4 bg-bg rounded-xl border border-slate-200">
 <div class="font-bold text-slate-700 text-sm mb-2">حالة الامتحان</div>
 <select name="status" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-indigo-300">
 <option value="active" <?php echo $exam['status']==='active'?'selected':''; ?>>✅ مفعّل (يمكن دخوله)</option>
 <option value="inactive" <?php echo $exam['status']==='inactive'?'selected':''; ?>>⏸ غير مفعّل (مسودة)</option>
 </select>
 </div>
 </div>
 </div>

 <div class="flex justify-end gap-4">
 <a href="manage_exam.php" class="bg-bg text-slate-600 px-6 py-3 rounded-xl font-bold hover:bg-slate-200 transition">إلغاء</a>
 <button type="submit" class="bg-primary text-white px-8 py-3 rounded-xl font-bold hover:bg-primary transition shadow-md shadow-sm flex items-center gap-2">
 <i class="fas fa-save"></i> حفظ التعديلات
 </button>
 </div>
 </form>
</div>

<script>
function selectType(type, el) {
 document.querySelectorAll('.exam-type-card').forEach(c => c.classList.remove('selected'));
 el.classList.add('selected');
 document.getElementById('typeInput').value = type;
}
function validateForm() {
 const t = document.querySelector('[name="title"]');
 const c = document.querySelector('[name="course_id"]');
 if (!t || !t.value.trim()) { alert('يرجى كتابة عنوان الامتحان.'); t && t.focus(); return false; }
 if (!c || !c.value) { alert('يرجى اختيار المقرر الدراسي.'); c && c.focus(); return false; }
 return true;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
 setTimeout(function () {
 if (typeof flatpickr === 'undefined') return;
 const fpCfg = {
 enableTime: true, dateFormat: 'Y-m-d H:i', altInput: true,
 altFormat: 'j F Y — H:i', time_24hr: true, minuteIncrement: 5,
 };
 flatpickr('#startTimePicker', { ...fpCfg,
 defaultDate: document.getElementById('startTimeVal').value || null,
 onChange(d) { document.getElementById('startTimeVal').value = d[0] ? flatpickr.formatDate(d[0],'Y-m-d H:i')+':00' : ''; },
 onClear() { document.getElementById('startTimeVal').value = ''; }
 });
 flatpickr('#endTimePicker', { ...fpCfg,
 defaultDate: document.getElementById('endTimeVal').value || null,
 onChange(d) { document.getElementById('endTimeVal').value = d[0] ? flatpickr.formatDate(d[0],'Y-m-d H:i')+':00' : ''; },
 onClear() { document.getElementById('endTimeVal').value = ''; }
 });
 }, 300);
});
</script>
<?php require_once '../includes/footer.php'; ?>
