<?php
require_once 'includes/header.php';
require_once __DIR__ . '/models/Enrollment.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Attendance.php';
require_once __DIR__ . '/models/Course.php';

// المسموح لهم رصد الغياب: الدكاترة لموادهم، والأدمن والعميد والشؤون لكل كلياتهم
if (!in_array($role, ['instructor', 'admin', 'super_admin', 'dean', 'affairs'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('absence');

$message = '';
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$date = $_GET['date'] ?? date('Y-m-d');
$is_manager = in_array($role, ['admin', 'super_admin', 'dean', 'affairs']);

// بنجيب لستة المواد (للدكتور بيشوف مواده بس، وللإدارة بيشوفوا مواد الكلية)
$all_courses = [];
try {
 if ($role === 'instructor') {
 $stmt = $pdo->prepare("
 SELECT c.* FROM courses c
 WHERE (c.instructor_id = ? OR c.doctor_id = ?)
 ORDER BY c.level, c.name
 ");
 $stmt->execute([$user_id, $user_id]);
 } elseif (in_array($role, ['dean', 'affairs'])) {
 $cid = (int)($_SESSION['college_id'] ?? 0);
 $stmt = $pdo->prepare("SELECT * FROM courses WHERE college_id = ? ORDER BY level, name");
 $stmt->execute([$cid]);
 } else {
 // admin / super_admin
 $filter_col = isset($_GET['college_id']) ? (int)$_GET['college_id'] : 0;
 if ($filter_col > 0) {
 $stmt = $pdo->prepare("SELECT * FROM courses WHERE college_id = ? ORDER BY level, name");
 $stmt->execute([$filter_col]);
 } else {
 $stmt = $pdo->query("SELECT * FROM courses ORDER BY level, name");
 }
 }
 $all_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
 $all_courses = [];
}

// بنجيب لستة الكليات للأدمن عشان يقدر يفلتر بيهم الداتا
$admin_colleges = [];
if (in_array($role, ['admin', 'super_admin'])) {
 try {
 $admin_colleges = $pdo->query("SELECT id, name FROM colleges ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
 } catch (Exception $e) {}
}
$filter_college_id = isset($_GET['college_id']) ? (int)$_GET['college_id'] : 0;

// بنحدد المادة اللي تم اختيارها من اللستة
$selected_course = null;
foreach ($all_courses as $c) {
 if ((int)$c['id'] === $course_id) { $selected_course = $c; break; }
}

// بنجيب لستة الطلاب اللي مسجلين في المادة والغياب القديم لو موجود
$students = [];
$attendance_map = []; // student_id => status for current date
$attendanceModel = new Attendance();

if ($course_id && $selected_course) {
 try {
 $stmt = $pdo->prepare("
 SELECT u.id, u.full_name, u.username, s.level
 FROM enrollments e
 JOIN users u ON u.id = e.user_id
 LEFT JOIN students s ON s.user_id = u.id
 WHERE e.course_id = ?
 ORDER BY u.full_name
 ");
 $stmt->execute([$course_id]);
 $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
 } catch (Exception $e) { $students = []; }

 // بنشوف لو فيه غياب متسجل للمادة دي في اليوم ده قبل كدا عشان يظهر
 try {
 $stmt = $pdo->prepare("SELECT user_id, status FROM attendance WHERE course_id = ? AND date = ?");
 $stmt->execute([$course_id, $date]);
 foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
 $attendance_map[$row['user_id']] = $row['status'];
 }
 } catch (Exception $e) {}
}

// تنفيذ عملية حفظ الغياب لما الفورمة تتبعت
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'attendance') {
 $post_course_id = (int)$_POST['course_id'];
 $post_date = $_POST['date'];
 $present_ids = array_map('intval', $_POST['present'] ?? []);

 // تأمين: نتأكد إن الدكتور له حق يسجل غياب للمادة دي فعلاً ومبيسجلش لمادة تانية
 if ($role === 'instructor') {
 $check = $pdo->prepare("SELECT COUNT(*) FROM schedule_slots WHERE course_id = ? AND instructor_id = ?");
 $check->execute([$post_course_id, $user_id]);
 if ($check->fetchColumn() == 0) {
 $message = '<div class="bg-primary text-white p-3 rounded-xl mb-4 font-bold">غير مصرح لك بتسجيل غياب هذا المقرر.</div>';
 goto render;
 }
 }

 // هات كل الطلاب المسجلين في المادة دي عشان نعرف مين غاب
 $stmt = $pdo->prepare("SELECT user_id FROM enrollments WHERE course_id = ?");
 $stmt->execute([$post_course_id]);
 $all_enrolled_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'user_id');

 // بنمسح أي غياب قديم متسجل في نفس اليوم والمادة عشان نسجل الجديد مكانه
 $pdo->prepare("DELETE FROM attendance WHERE course_id = ? AND date = ?")->execute([$post_course_id, $post_date]);

 // تسجيل الحضور والغياب الجديد لكل الطلاب
 $ins = $pdo->prepare("INSERT INTO attendance (user_id, course_id, date, status) VALUES (?, ?, ?, ?)");
 $absent_count = 0;
 foreach ($all_enrolled_ids as $sid) {
 $status = in_array($sid, $present_ids) ? 'present' : 'absent';
 if ($status === 'absent') $absent_count++;
 $ins->execute([$sid, $post_course_id, $post_date, $status]);
 }

 $message = '<div class="bg-primary text-white p-3 rounded-xl mb-4 font-bold flex items-center gap-2">
 <i class="fas fa-check-circle text-primary"></i>
 تم حفظ الغياب بنجاح — ' . count($all_enrolled_ids) . ' طالب، غائب: ' . $absent_count . '
 </div>';

 // بنحدث بيانات الغياب في الصفحة عشان تظهر الحالة الجديدة فورا
 $course_id = $post_course_id;
 $date = $post_date;
 foreach ($all_enrolled_ids as $sid) {
 $attendance_map[$sid] = in_array($sid, $present_ids) ? 'present' : 'absent';
 }
}

render:
?>

<div class="max-w-5xl mx-auto space-y-6 animate-fade-in-up">

 <!-- Header -->
 <div class="flex items-center justify-between flex-wrap gap-4">
 <div>
 <h1 class="text-2xl font-bold text-secondary flex items-center gap-2">
 <i class="fas fa-clipboard-list text-primary bg-bg p-2 rounded-xl"></i>
 رصد الحضور والغياب اليومي
 </h1>
 <p class="text-sm text-slate-500 mt-1">تسجيل حضور وغياب الطلاب لكل محاضرة</p>
 </div>
 </div>

 <?php echo $message; ?>

 <!-- ── FILTER BAR ── -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
 <form method="GET" class="flex flex-wrap items-end gap-4">
 <input type="hidden" name="tab" value="absence">

 <?php if (!empty($admin_colleges)): ?>
 <div class="flex flex-col gap-1.5 min-w-[180px]">
 <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">الكلية</label>
 <select name="college_id" onchange="this.form.submit()"
 class="bg-bg border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary">
 <option value="0">— جميع الكليات —</option>
 <?php foreach ($admin_colleges as $col): ?>
 <option value="<?php echo $col['id']; ?>" <?php echo $filter_college_id == $col['id'] ? 'selected' : ''; ?>>
 <?php echo htmlspecialchars($col['name']); ?>
 </option>
 <?php endforeach; ?>
 </select>
 </div>
 <?php endif; ?>

 <div class="flex flex-col gap-1.5 flex-1 min-w-[180px]">
 <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">المقرر الدراسي</label>
 <select name="id" onchange="this.form.submit()"
 class="w-full bg-bg border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary">
 <option value="">— اختر المقرر —</option>
 <?php foreach ($all_courses as $c): ?>
 <option value="<?php echo $c['id']; ?>" <?php echo $course_id == $c['id'] ? 'selected' : ''; ?>>
 <?php echo htmlspecialchars($c['name']); ?> (الفرقة <?php echo $c['level']; ?>)
 </option>
 <?php endforeach; ?>
 </select>
 </div>

 <div class="flex flex-col gap-1.5">
 <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">تاريخ المحاضرة</label>
 <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>"
 onchange="this.form.submit()"
 max="<?php echo date('Y-m-d'); ?>"
 class="bg-bg border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary">
 </div>
 <?php if ($filter_college_id): ?>
 <input type="hidden" name="college_id" value="<?php echo $filter_college_id; ?>">
 <?php endif; ?>
 </form>
 </div>

 <?php if ($selected_course && !empty($students)): ?>
 <!-- ── COURSE INFO BAR ── -->
 <div class="bg-bg rounded-2xl p-4 flex flex-wrap items-center justify-between gap-3 text-white">
 <div>
 <p class="font-bold text-lg"><?php echo htmlspecialchars($selected_course['name']); ?></p>
 <p class="text-primary text-sm font-mono mt-0.5"><?php echo htmlspecialchars($selected_course['code'] ?? ''); ?> — الفرقة <?php echo $selected_course['level']; ?></p>
 </div>
 <div class="flex items-center gap-4 text-sm">
 <span class="bg-white/10 border border-white/20 rounded-xl px-4 py-2 font-bold">
 <i class="fas fa-users ml-1"></i> <?php echo count($students); ?> طالب
 </span>
 <span class="bg-white/10 border border-white/20 rounded-xl px-4 py-2 font-bold">
 <i class="fas fa-calendar ml-1"></i> <?php echo $date; ?>
 </span>
 <?php
 $already_taken = !empty($attendance_map);
 if ($already_taken):
 $present_n = count(array_filter($attendance_map, fn($v) => $v === 'present'));
 $absent_n = count($attendance_map) - $present_n;
 ?>
 <span class="bg-primary/20 border border-primary/30 rounded-xl px-4 py-2 font-bold text-primary">
 <i class="fas fa-history ml-1"></i> تم التسجيل — حاضر: <?php echo $present_n; ?> | غائب: <?php echo $absent_n; ?>
 </span>
 <?php endif; ?>
 </div>
 </div>

 <!-- ── ATTENDANCE FORM ── -->
 <form method="POST" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
 <input type="hidden" name="action" value="attendance">
 <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
 <input type="hidden" name="date" value="<?php echo $date; ?>">

 <!-- Toolbar -->
 <div class="px-5 py-3 bg-bg border-b border-slate-100 flex items-center justify-between gap-4">
 <span class="text-sm font-bold text-slate-600">
 <i class="fas fa-user-check text-primary ml-1"></i>
 حدد الغائبين — الكل حاضر بشكل افتراضي
 </span>
 <div class="flex items-center gap-2">
 <button type="button" onclick="setAll(true)"
 class="text-xs bg-bg text-primary border border-primary px-3 py-1.5 rounded-lg font-bold hover:bg-primary transition">
 <i class="fas fa-check-double ml-1"></i> الكل حاضر
 </button>
 <button type="button" onclick="setAll(false)"
 class="text-xs bg-bg text-primary border border-primary px-3 py-1.5 rounded-lg font-bold hover:bg-primary transition">
 <i class="fas fa-times ml-1"></i> الكل غائب
 </button>
 </div>
 </div>

 <div class="divide-y divide-slate-50">
 <?php foreach ($students as $i => $s):
 $saved_status = $attendance_map[$s['id']] ?? 'present';
 $is_present = ($saved_status === 'present');
 ?>
 <label class="flex items-center gap-4 px-5 py-3 cursor-pointer hover:bg-bg transition group student-row <?php echo $is_present ? '' : 'bg-bg/40'; ?>"
 id="row-<?php echo $s['id']; ?>">
 <span class="text-slate-400 text-sm font-mono w-6 text-center"><?php echo $i + 1; ?></span>

 <div class="flex-1">
 <div class="font-bold text-slate-800"><?php echo htmlspecialchars($s['full_name']); ?></div>
 <div class="text-xs text-slate-400 font-mono"><?php echo htmlspecialchars($s['username'] ?? ''); ?></div>
 </div>

 <!-- Toggle switch -->
 <div class="flex items-center gap-3">
 <span class="text-xs font-bold absent-label <?php echo $is_present ? 'text-slate-300' : 'text-primary'; ?>">غائب</span>
 <div class="relative">
 <input type="checkbox" name="present[]" value="<?php echo $s['id']; ?>"
 <?php echo $is_present ? 'checked' : ''; ?>
 class="sr-only peer attendance-check"
 onchange="updateRow(this)">
 <div class="w-12 h-6 bg-primary peer-checked:bg-primary rounded-full transition-colors duration-200 cursor-pointer
 after:content-[''] after:absolute after:w-5 after:h-5 after:bg-white after:rounded-full after:shadow
 after:top-0.5 after:right-0.5 peer-checked:after:translate-x-[-24px] after:transition-transform after:duration-200"></div>
 </div>
 <span class="text-xs font-bold present-label <?php echo $is_present ? 'text-primary' : 'text-slate-300'; ?>">حاضر</span>
 </div>
 </label>
 <?php endforeach; ?>
 </div>

 <!-- Live Counter -->
 <div class="px-5 py-3 bg-bg border-t border-slate-100 flex items-center justify-between gap-4">
 <div class="flex items-center gap-4 text-sm font-bold">
 <span class="text-primary"><i class="fas fa-user-check ml-1"></i> حاضر: <span id="present-count">0</span></span>
 <span class="text-primary"><i class="fas fa-user-times ml-1"></i> غائب: <span id="absent-count">0</span></span>
 </div>
 <button type="submit"
 class="bg-primary hover:bg-accent hover:text-white text-white px-8 py-2.5 rounded-xl font-bold transition shadow flex items-center gap-2">
 <i class="fas fa-save"></i> حفظ الغياب
 </button>
 </div>
 </form>

 <?php elseif ($course_id && empty($students)): ?>
 <div class="bg-bg border border-primary text-primary p-6 rounded-2xl text-center font-bold">
 <i class="fas fa-info-circle text-2xl mb-2 block"></i>
 لا يوجد طلاب مسجلين في هذا المقرر.
 </div>
 <?php elseif (empty($all_courses)): ?>
 <div class="bg-bg border border-slate-200 text-slate-500 p-8 rounded-2xl text-center font-bold">
 <i class="fas fa-book-open text-3xl mb-3 block text-slate-300"></i>
 <?php echo $role === 'instructor' ? 'لا توجد مقررات مخصصة لك في الجدول الدراسي.' : 'لا توجد مقررات متاحة.'; ?>
 </div>
 <?php else: ?>
 <div class="bg-bg border border-primary text-primary p-6 rounded-2xl text-center font-bold">
 <i class="fas fa-hand-point-up text-2xl mb-2 block"></i>
 اختر مقرراً وتاريخاً من الأعلى لبدء تسجيل الغياب.
 </div>
 <?php endif; ?>

</div>

<script>
function updateRow(checkbox) {
 const label = checkbox.closest('.student-row');
 const absent = label.querySelector('.absent-label');
 const present = label.querySelector('.present-label');
 if (checkbox.checked) {
 label.classList.remove('bg-bg/40');
 absent.classList.replace('text-primary', 'text-slate-300');
 present.classList.replace('text-slate-300', 'text-primary');
 } else {
 label.classList.add('bg-bg/40');
 absent.classList.replace('text-slate-300', 'text-primary');
 present.classList.replace('text-primary', 'text-slate-300');
 }
 updateCounters();
}

function setAll(present) {
 document.querySelectorAll('.attendance-check').forEach(cb => {
 if (cb.checked !== present) { cb.checked = present; updateRow(cb); }
 });
}

function updateCounters() {
 const checks = document.querySelectorAll('.attendance-check');
 const presentN = [...checks].filter(c => c.checked).length;
 document.getElementById('present-count').textContent = presentN;
 document.getElementById('absent-count').textContent = checks.length - presentN;
}
updateCounters();
</script>

<?php require_once 'includes/footer.php'; ?>