<?php
// ── Bootstrap: session + DB only (NO HTML output yet) ──────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
 header('Location: ../index.php'); exit;
}

require_once '../database/db_connection.php';
$pdo = get_pdo();
$role = $_SESSION['role'] ?? 'student';
$user_id = (int)$_SESSION['user_id'];

if (!in_array($role, ['instructor','admin','super_admin','dean'])) {
 header('Location: ../index.php'); exit;
}

// has_permission() is defined in includes/permissions.php (loaded via header.php on line 91)
// No stub needed here — role check on line 15 already guards access before header.php loads.

// Auto-create tables
try { $pdo->exec(file_get_contents(__DIR__ . '/../database/online_exam_schema.sql')); } catch(Exception $e){}

$instructor_id = $user_id;
$message = '';

// ── Handle POST before ANY output ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $title = trim($_POST['title'] ?? '');
 $course_id = (int)($_POST['course_id'] ?? 0);
 $type = in_array($_POST['type'] ?? '', ['quiz','midterm','assignment']) ? $_POST['type'] : 'quiz';
 $duration = max(1, (int)($_POST['duration_min'] ?? 30));
 $raw_start = trim($_POST['start_time'] ?? '');
 $raw_end = trim($_POST['end_time'] ?? '');
 // Flatpickr sends "2026-04-15 09:30:00" — already correct for PostgreSQL
 $start_time = !empty($raw_start) ? $raw_start : null;
 $end_time = !empty($raw_end) ? $raw_end : null;
 $status = ($_POST['status'] ?? 'inactive') === 'active' ? 'active' : 'inactive';
 $randomize = isset($_POST['randomize_q']);
 $show_results = isset($_POST['show_results']);
 $pass_score = min(100, max(0, (float)($_POST['pass_score'] ?? 50)));
 $description = trim($_POST['description'] ?? '');
 $college_id = (int)($_SESSION['college_id'] ?? 0);

 // Server-side time validation
 $time_error = '';
 if ($start_time && $end_time && strtotime($end_time) <= strtotime($start_time)) {
 $time_error = 'وقت الانتهاء يجب أن يكون بعد وقت البداية.';
 }

 if (!empty($title) && $course_id > 0 && !$time_error) {
 try {
 $stmt = $pdo->prepare("
 INSERT INTO online_exams
 (college_id, course_id, created_by, title, description, type,
 duration_min, start_time, end_time, status, randomize_q, show_results, pass_score)
 VALUES (:college_id, :course_id, :created_by, :title, :description, :type,
 :duration_min, :start_time, :end_time, :status, :randomize_q, :show_results, :pass_score)
 RETURNING id
 ");

 // INT — explicit NULL for optional foreign key
 if ($college_id > 0) {
 $stmt->bindValue(':college_id', $college_id, PDO::PARAM_INT);
 } else {
 $stmt->bindValue(':college_id', null, PDO::PARAM_NULL);
 }
 $stmt->bindValue(':course_id', $course_id, PDO::PARAM_INT);
 $stmt->bindValue(':created_by', $instructor_id, PDO::PARAM_INT);

 // VARCHAR / TEXT
 $stmt->bindValue(':title', $title, PDO::PARAM_STR);
 $stmt->bindValue(':description', $description, PDO::PARAM_STR);
 $stmt->bindValue(':type', $type, PDO::PARAM_STR);
 $stmt->bindValue(':status', $status, PDO::PARAM_STR);

 // INT
 $stmt->bindValue(':duration_min', $duration, PDO::PARAM_INT);

 // TIMESTAMPTZ — NULL if empty
 if (!empty($start_time)) {
 $stmt->bindValue(':start_time', $start_time, PDO::PARAM_STR);
 } else {
 $stmt->bindValue(':start_time', null, PDO::PARAM_NULL);
 }
 if (!empty($end_time)) {
 $stmt->bindValue(':end_time', $end_time, PDO::PARAM_STR);
 } else {
 $stmt->bindValue(':end_time', null, PDO::PARAM_NULL);
 }

 // BOOLEAN — PostgreSQL requires literal string 'true'/'false'
 $stmt->bindValue(':randomize_q', $randomize ? 'true' : 'false', PDO::PARAM_STR);
 $stmt->bindValue(':show_results', $show_results ? 'true' : 'false', PDO::PARAM_STR);

 // NUMERIC
 $stmt->bindValue(':pass_score', $pass_score, PDO::PARAM_STR);

 $stmt->execute();
 $new_id = (int)$stmt->fetchColumn();
 header("Location: create_question.php?exam_id={$new_id}&created=1");
 exit;
 } catch (Exception $e) {
 $message = '<div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:14px 18px;border-radius:12px;margin-bottom:16px;font-weight:700;"><i class="fas fa-bug me-2"></i>خطأ في قاعدة البيانات: ' . htmlspecialchars($e->getMessage()) . '</div>';
 }
 } elseif ($time_error) {
 $message = '<div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:14px 18px;border-radius:12px;margin-bottom:16px;font-weight:700;"><i class="fas fa-clock me-2"></i>' . $time_error . '</div>';
 } else {
 $message = '<div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:14px 18px;border-radius:12px;margin-bottom:16px;font-weight:700;"><i class="fas fa-exclamation-triangle me-2"></i>يرجى ملء العنوان واختيار المقرر.</div>';
 }

}

// ── NOW load header (outputs HTML) ─────────────────────────────────────
require_once '../includes/header.php';

// ── Fetch instructor's courses (for form dropdown) ──────────────────────
$courses = [];
try {
 if (in_array($role, ['admin','super_admin'])) {
 $courses = $pdo->query("SELECT id, name, code FROM courses ORDER BY name")->fetchAll();
 } elseif ($role === 'dean') {
 $cid = (int)($_SESSION['college_id'] ?? 0);
 $stmt = $pdo->prepare("SELECT id, name, code FROM courses WHERE college_id = ? ORDER BY name");
 $stmt->execute([$cid]);
 $courses = $stmt->fetchAll();
 } else {
 try {
 $stmt = $pdo->prepare("SELECT id, name, code FROM courses WHERE instructor_id = ? OR doctor_id = ? ORDER BY name");
 $stmt->execute([$instructor_id, $instructor_id]);
 $courses = $stmt->fetchAll();
 } catch (Exception $e) {
 try {
 $stmt = $pdo->prepare("SELECT id, name, code FROM courses WHERE instructor_id = ? ORDER BY name");
 $stmt->execute([$instructor_id]);
 $courses = $stmt->fetchAll();
 } catch (Exception $e2) {}
 }
 }
} catch (Exception $e) {}
?>



<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
.exam-type-card { cursor:pointer; border:2px solid #e2e8f0; border-radius:12px; padding:16px; text-align:center; transition:all .2s; }
.exam-type-card:hover, .exam-type-card.selected { border-color:#4f46e5; background:#eef2ff; }
.exam-type-card.selected .type-icon { color:#4f46e5; }
.type-icon { font-size:1.8rem; margin-bottom:8px; color:#94a3b8; }
.form-section { background:#fff; border-radius:16px; padding:24px; box-shadow:0 1px 4px rgba(0,0,0,.06); border:1px solid #f1f5f9; margin-bottom:20px; }
.section-title { font-size:1rem; font-weight:700; color:#1e293b; border-bottom:2px solid #f1f5f9; padding-bottom:12px; margin-bottom:18px; display:flex; align-items:center; gap:8px; }
</style>

<div class="max-w-4xl mx-auto pb-10">
 <!-- Page Header -->
 <div class="form-section">
 <div class="flex items-center gap-4">
 <div class="w-14 h-14 rounded-2xl bg-primary flex items-center justify-center shadow-lg shadow-sm">
 <i class="fas fa-file-signature text-white text-2xl"></i>
 </div>
 <div>
 <h1 class="text-2xl font-bold text-slate-800">إنشاء امتحان إلكتروني جديد</h1>
 <p class="text-slate-500 text-sm mt-1">حدد نوع الامتحان وبياناته الأساسية، ثم أضف الأسئلة.</p>
 </div>
 <a href="manage_exam.php" class="mr-auto bg-bg text-slate-600 px-4 py-2 rounded-xl font-bold hover:bg-slate-200 transition flex items-center gap-2 text-sm">
 <i class="fas fa-list"></i> امتحاناتي
 </a>
 </div>
 </div>

 <?php echo $message; ?>

 <form method="POST" id="examForm" onsubmit="return validateForm()">
 
 <!-- Exam Type Selection -->
 <div class="form-section">
 <div class="section-title"><i class="fas fa-shapes text-primary"></i> نوع الامتحان</div>
 <input type="hidden" name="type" id="typeInput" value="quiz" required>
 <div class="grid grid-cols-3 gap-4">
 <div class="exam-type-card selected" onclick="selectType('quiz',this)">
 <div class="type-icon"><i class="fas fa-bolt"></i></div>
 <div class="font-bold text-slate-700">كويز سريع</div>
 <div class="text-xs text-slate-400 mt-1">Quiz</div>
 </div>
 <div class="exam-type-card" onclick="selectType('midterm',this)">
 <div class="type-icon"><i class="fas fa-graduation-cap"></i></div>
 <div class="font-bold text-slate-700">اختبار ترمي</div>
 <div class="text-xs text-slate-400 mt-1">Midterm</div>
 </div>
 <div class="exam-type-card" onclick="selectType('assignment',this)">
 <div class="type-icon"><i class="fas fa-tasks"></i></div>
 <div class="font-bold text-slate-700">واجب / تكليف</div>
 <div class="text-xs text-slate-400 mt-1">Assignment</div>
 </div>
 </div>
 </div>

 <!-- Basic Info -->
 <div class="form-section">
 <div class="section-title"><i class="fas fa-info-circle text-accent"></i> البيانات الأساسية</div>
 <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
 <div class="md:col-span-2">
 <label class="block text-sm font-bold text-slate-700 mb-2">عنوان الامتحان <span class="text-primary">*</span></label>
 <input type="text" name="title" required placeholder="مثال: اختبار الفصل الأول — فيزياء"
 class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-bg focus:ring-2 focus:ring-indigo-300 focus:bg-white transition text-slate-800 font-medium">
 </div>
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">المقرر <span class="text-primary">*</span></label>
 <select name="course_id" required class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-bg focus:ring-2 focus:ring-indigo-300 focus:bg-white transition">
 <option value="">— اختر المقرر —</option>
 <?php foreach($courses as $c): ?>
 <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['code'] . ' — ' . $c['name']); ?></option>
 <?php endforeach; ?>
 </select>
 </div>
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">مدة الامتحان (بالدقائق)</label>
 <input type="number" name="duration_min" value="30" min="1" max="300"
 class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-bg focus:ring-2 focus:ring-indigo-300 focus:bg-white transition">
 </div>
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">
 <i class="fas fa-play-circle text-primary me-1"></i> وقت البداية
 <span class="text-xs font-normal text-slate-400 mr-1">(اتركه فارغاً للبدء الفوري)</span>
 </label>
 <div style="position:relative;">
 <input type="text" id="startTimePicker" placeholder="اختر التاريخ والوقت..."
 class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-bg focus:ring-2 focus:ring-indigo-300 focus:bg-white transition" readonly>
 <input type="hidden" name="start_time" id="startTimeVal">
 <i class="fas fa-calendar-alt" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;"></i>
 </div>
 </div>
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">
 <i class="fas fa-stop-circle text-primary me-1"></i> وقت الانتهاء
 <span class="text-xs font-normal text-slate-400 mr-1">(اتركه فارغاً بدون نهاية)</span>
 </label>
 <div style="position:relative;">
 <input type="text" id="endTimePicker" placeholder="اختر التاريخ والوقت..."
 class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-bg focus:ring-2 focus:ring-indigo-300 focus:bg-white transition" readonly>
 <input type="hidden" name="end_time" id="endTimeVal">
 <i class="fas fa-calendar-alt" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;"></i>
 </div>
 <p id="timeError" style="display:none;color:#dc2626;font-size:.78rem;margin-top:6px;font-weight:700;">
 <i class="fas fa-exclamation-triangle"></i> وقت الانتهاء يجب أن يكون بعد وقت البداية!
 </p>
 </div>
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">درجة النجاح (%)</label>
 <input type="number" name="pass_score" value="50" min="0" max="100"
 class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-bg focus:ring-2 focus:ring-indigo-300 focus:bg-white transition">
 </div>
 <div class="md:col-span-2">
 <label class="block text-sm font-bold text-slate-700 mb-2">وصف الامتحان (اختياري)</label>
 <textarea name="description" rows="3" placeholder="تعليمات للطلاب..."
 class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-bg focus:ring-2 focus:ring-indigo-300 focus:bg-white transition resize-none"></textarea>
 </div>
 </div>
 </div>

 <!-- Settings -->
 <div class="form-section">
 <div class="section-title"><i class="fas fa-sliders-h text-primary"></i> إعدادات الامتحان</div>
 <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
 <label class="flex items-center gap-3 p-4 bg-bg rounded-xl border border-slate-200 cursor-pointer hover:bg-bg hover:border-primary transition">
 <input type="checkbox" name="randomize_q" checked class="w-5 h-5 rounded text-primary">
 <div>
 <div class="font-bold text-slate-700 text-sm">ترتيب عشوائي</div>
 <div class="text-xs text-slate-400">ترتب الأسئلة عشوائيا لكل طالب</div>
 </div>
 </label>
 <label class="flex items-center gap-3 p-4 bg-bg rounded-xl border border-slate-200 cursor-pointer hover:bg-bg hover:border-primary transition">
 <input type="checkbox" name="show_results" checked class="w-5 h-5 rounded text-primary">
 <div>
 <div class="font-bold text-slate-700 text-sm">إظهار النتيجة</div>
 <div class="text-xs text-slate-400">يرى الطالب نتيجته بعد التسليم</div>
 </div>
 </label>
 <div class="p-4 bg-bg rounded-xl border border-slate-200">
 <div class="font-bold text-slate-700 text-sm mb-2">حالة الامتحان</div>
 <select name="status" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-indigo-300">
 <option value="active" selected>✅ مفعّل (يمكن دخوله)</option>
 <option value="inactive">⏸ غير مفعّل (مسودة)</option>
 </select>
 </div>
 </div>
 </div>

 <div class="flex justify-end gap-4">
 <a href="manage_exam.php" class="bg-bg text-slate-600 px-6 py-3 rounded-xl font-bold hover:bg-slate-200 transition">إلغاء</a>
 <button type="submit" class="bg-primary text-white px-8 py-3 rounded-xl font-bold hover:bg-primary transition shadow-md shadow-sm flex items-center gap-2">
 <i class="fas fa-arrow-left"></i> التالي: إضافة الأسئلة
 </button>
 </div>
 </form>
</div>

<!-- ══ Critical functions — must load BEFORE flatpickr ══ -->
<script>
function selectType(type, el) {
 document.querySelectorAll('.exam-type-card').forEach(c => c.classList.remove('selected'));
 el.classList.add('selected');
 document.getElementById('typeInput').value = type;
}

function validateTimes() {
 const startVal = (document.getElementById('startTimeVal')?.value || '').trim();
 const endVal = (document.getElementById('endTimeVal')?.value || '').trim();
 const errEl = document.getElementById('timeError');
 if (!errEl) return true;

 // Only validate when BOTH fields are filled
 if (startVal && endVal) {
 // Fix: JS Date needs "T" not space: "2026-04-18T10:00:00"
 const startDate = new Date(startVal.replace(' ', 'T'));
 const endDate = new Date(endVal.replace(' ', 'T'));
 if (!isNaN(startDate) && !isNaN(endDate) && endDate <= startDate) {
 errEl.style.display = 'block';
 return false;
 }
 }
 errEl.style.display = 'none';
 return true;
}


function validateForm() {
 const titleEl = document.querySelector('[name="title"]');
 const courseEl = document.querySelector('[name="course_id"]');
 if (!titleEl || !titleEl.value.trim()) {
 alert('يرجى كتابة عنوان الامتحان.');
 titleEl && titleEl.focus();
 return false;
 }
 if (!courseEl || !courseEl.value) {
 alert('يرجى اختيار المقرر الدراسي.');
 courseEl && courseEl.focus();
 return false;
 }
 if (!validateTimes()) {
 alert('وقت الانتهاء يجب أن يكون بعد وقت البداية.');
 return false;
 }
 return true;
}
</script>

<!-- ══ Flatpickr CDN — optional enhancement ══ -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"
 onerror="this.remove()">
<script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
 // Wait a tick to ensure flatpickr script has executed
 setTimeout(function () {
 if (typeof flatpickr === 'undefined') {
 // Fallback: show normal text inputs
 ['startTimePicker','endTimePicker'].forEach(function(id) {
 const el = document.getElementById(id);
 if (!el) return;
 el.type = 'datetime-local';
 el.readOnly = false;
 el.addEventListener('change', function() {
 const hidden = document.getElementById(id.replace('Picker','Val'));
 if (hidden) hidden.value = el.value
 ? el.value.replace('T',' ') + ':00'
 : '';
 validateTimes();
 });
 });
 return;
 }

 // ── Flatpickr config ───────────────────────────────────────────
 const fpCfg = {
 enableTime : true,
 dateFormat : 'Y-m-d H:i',
 altInput : true,
 altFormat : 'j F Y — H:i',
 time_24hr : true,
 minuteIncrement: 5,
 locale : typeof flatpickr.l10ns?.ar !== 'undefined' ? 'ar' : 'default',
 onReady(_, __, fp) {
 const yr = fp.calendarContainer?.querySelector('.numInput.cur-year');
 if (yr) { yr.max = 2035; yr.min = 2020; yr.maxLength = 4; }
 }
 };

 const startFP = flatpickr('#startTimePicker', {
 ...fpCfg,
 onChange(dates) {
 document.getElementById('startTimeVal').value =
 dates[0] ? flatpickr.formatDate(dates[0], 'Y-m-d H:i') + ':00' : '';
 try { endFP.set('minDate', dates[0] || null); } catch(e){}
 validateTimes();
 },
 onClear() {
 document.getElementById('startTimeVal').value = '';
 try { endFP.set('minDate', null); } catch(e){}
 validateTimes();
 }
 });

 const endFP = flatpickr('#endTimePicker', {
 ...fpCfg,
 onChange(dates) {
 document.getElementById('endTimeVal').value =
 dates[0] ? flatpickr.formatDate(dates[0], 'Y-m-d H:i') + ':00' : '';
 validateTimes();
 },
 onClear() {
 document.getElementById('endTimeVal').value = '';
 validateTimes();
 }
 });

 }, 300); // 300ms grace period for defer scripts
});
</script>

<?php require_once '../includes/footer.php'; ?>
