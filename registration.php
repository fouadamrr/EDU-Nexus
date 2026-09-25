<?php
require_once 'includes/header.php';

// اتأكد إن اللي داخل دا طالب عشان التسجيل للطلاب بس
if ($role !== 'student') {
 header("Location: index.php");
 exit;
}

require_once __DIR__ . '/controllers/RegistrationController.php';
$registrationController = new RegistrationController();

$registration_open = $registrationController->isRegistrationOpen($user_id);
$semester = 'Spring 2026';
$message = '';
$student_level = $registrationController->getStudentLevel($user_id);

$available_courses = $registrationController->getAvailableCourses($user_id);
$current_course_ids = $registrationController->getCurrentEnrollments($user_id, $semester);
$permitted_course_ids = $registrationController->getPermittedCourseIds($user_id);

// بنجيب حدود الساعات المسموح بيها بناءً على المعدل التراكمي واسم المرشد
$academic_info = $registrationController->getStudentAcademicInfo($user_id);
$max_hours = $academic_info['max_hours'];
$advisor_name = $academic_info['advisor_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_registration') {
 $result = $registrationController->handleRegistration(
 $_POST,
 $user_id,
 $semester,
 $registration_open,
 $available_courses,
 $role,
 $student_level
 );
 $message = $result['message'];
 if ($result['success'] && isset($result['course_ids'])) {
 $current_course_ids = $result['course_ids'];
 }
}

// بنقسم المواد حسب الفرقة الدراسية (مستوى الطالب)
$levels_ar = [1 => 'الفرقة الأولى', 2 => 'الفرقة الثانية', 3 => 'الفرقة الثالثة', 4 => 'الفرقة الرابعة', 0 => 'مقررات عامة'];
$grouped = [];
foreach ($available_courses as $c) {
 $lv = (int)($c['level'] ?? 0);
 $grouped[$lv][] = $c;
}
ksort($grouped);

$total_registered_hours = 0;
foreach ($available_courses as $c) {
 if (in_array((int)($c['id'] ?? 0), $current_course_ids)) {
 $total_registered_hours += (int)($c['credit_hours'] ?? 0);
 }
}
?>

<div class="max-w-5xl mx-auto">
 <div class="flex items-center justify-between mb-6">
 <div>
 <h2 class="text-2xl font-bold text-primary">
 <i class="fas fa-file-signature text-accent ml-2"></i>
 التسجيل الأكاديمي &mdash; ربيع 2026
 </h2>
 <p class="text-sm text-gray-500 mt-1">فرقتك الدراسية: <strong class="text-primary">السنة <?php echo $student_level; ?></strong></p>
 </div>
 <div class="flex items-center gap-3">
 <?php if ($registration_open): ?>
 <span class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-1">
 <i class="fas fa-circle text-xs animate-pulse"></i> التسجيل مفتوح
 </span>
 <?php else: ?>
 <span class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-1">
 <i class="fas fa-lock text-xs"></i> التسجيل مغلق
 </span>
 <?php endif; ?>
 <div class="bg-bg text-primary px-4 py-2 rounded-lg text-sm font-bold">
 الساعات المسجلة: <span id="total-hours"><?php echo $total_registered_hours; ?></span> / <?php echo $max_hours; ?>
 </div>
 </div>
 </div>

 <?php echo $message; ?>

 <?php if ($registration_open || !empty($permitted_course_ids)): ?>
 <form method="POST" class="space-y-5" id="regForm">
 <input type="hidden" name="action" value="save_registration">

 <?php foreach ($grouped as $lv => $courses): 
 $level_label = $levels_ar[$lv] ?? "السنة $lv";
 $is_own_level = ($lv == $student_level || $lv == 0);
 $visible_courses = $courses; // No level filtering
 if (empty($visible_courses)) continue;
 ?>
 <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
 <div class="flex items-center justify-between px-5 py-3
 <?php echo $is_own_level ? 'bg-primary text-white' : 'bg-bg border-b border-primary text-primary'; ?>">
 <span class="font-bold text-sm flex items-center gap-2">
 <?php if ($is_own_level): ?>
 <i class="fas fa-graduation-cap"></i>
 <?php else: ?>
 <i class="fas fa-unlock-alt text-primary"></i>
 <?php endif; ?>
 <?php echo $level_label; ?>
 </span>
 <?php if (!$is_own_level): ?>
 <span class="text-xs bg-primary text-white px-2 py-1 rounded font-bold">
 مواد مفتوحة بإذن الإدارة
 </span>
 <?php endif; ?>
 </div>
 <table class="w-full text-right">
 <thead class="bg-bg border-b border-gray-100 text-sm text-gray-500 font-bold">
 <tr>
 <th class="p-3">اختيار</th>
 <th class="p-3">الكود</th>
 <th class="p-3">اسم المقرر</th>
 <th class="p-3 text-center">الساعات</th>
 <th class="p-3">أستاذ المادة</th>
 <th class="p-3 text-center">الحالة</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-50">
 <?php foreach ($visible_courses as $c):
 $cid = (int)($c['id'] ?? 0);
 $is_checked = in_array($cid, $current_course_ids);
 $is_perm = in_array($cid, $permitted_course_ids);
 ?>
 <tr class="hover:bg-bg transition <?php echo $is_checked ? 'bg-bg/40' : ''; ?>">
 <td class="p-3 text-center">
 <input type="checkbox"
 name="course_ids[]"
 value="<?php echo $cid; ?>"
 <?php echo $is_checked ? 'checked' : ''; ?>
 data-hours="<?php echo (int)($c['credit_hours'] ?? 0); ?>"
 class="reg-check w-5 h-5 text-primary rounded border-gray-300 focus:ring-primary cursor-pointer">
 </td>
 <td class="p-3 font-mono text-gray-600 text-sm"><?php echo htmlspecialchars($c['code'] ?? ''); ?></td>
 <td class="p-3 font-bold text-gray-800">
 <?php echo htmlspecialchars($c['name'] ?? ''); ?>
 <?php if ($is_perm && !$is_own_level): ?>
 <span class="mr-2 text-xs bg-primary text-white px-2 py-0.5 rounded">مفتوح بإذن</span>
 <?php endif; ?>
 </td>
 <td class="p-3 text-center font-bold text-accent"><?php echo htmlspecialchars($c['credit_hours'] ?? ''); ?></td>
 <td class="p-3 text-gray-500 text-sm"><?php echo htmlspecialchars($c['doctor'] ?? 'غير محدد'); ?></td>
 <td class="p-3 text-center">
 <?php if ($is_checked): ?>
 <span class="text-xs bg-primary text-white px-2 py-1 rounded font-bold">مسجل</span>
 <?php else: ?>
 <span class="text-xs bg-bg text-gray-500 px-2 py-1 rounded">متاح</span>
 <?php endif; ?>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 <?php endforeach; ?>

 <!-- زراير التحكم (حفظ، طباعة، إلغاء) -->
 <div class="bg-white rounded-xl shadow border border-gray-100 p-4 flex justify-end gap-3">
 <button id="printBtn" type="button"
 class="px-5 py-2 rounded-lg text-sm font-bold transition"
 onclick="handlePrint(event)">
 <i class="fas fa-print ml-2"></i> طباعة الاستمارة
 </button>
 <a href="dashboard.php" class="px-5 py-2 rounded-lg text-gray-600 hover:bg-bg transition text-sm font-bold">
 إلغاء
 </a>
 <button type="submit"
 class="bg-primary text-white px-8 py-2 rounded-lg hover:bg-opacity-90 transition font-bold shadow-md text-sm"
 onclick="return beforeSubmit()">
 <i class="fas fa-save ml-2"></i> حفظ التسجيل
 </button>
 </div>
 </form>

 <?php else: ?>
 <div class="bg-white rounded-xl shadow border border-primary p-10 text-center">
 <div class="w-20 h-20 bg-primary text-white rounded-full flex items-center justify-center mx-auto mb-4 text-4xl">
 <i class="fas fa-lock"></i>
 </div>
 <h3 class="text-2xl font-bold text-gray-800 mb-2">فترة التسجيل مغلقة حالياً</h3>
 <p class="text-gray-500 mb-6">يرجى متابعة الإعلانات لمعرفة مواعيد فتح التسجيل، أو تواصل مع شؤون الطلاب.</p>
 <a href="dashboard.php" class="inline-block bg-primary text-white px-6 py-2 rounded-lg hover:bg-opacity-90 transition font-bold shadow">
 العودة للرئيسية
 </a>
 </div>
 <?php endif; ?>

 <!-- الجزء الخاص بالطباعة (بيكون مخفي في الصفحة بس بيظهر في الطباعة) -->
 <div id="reg-print-area" style="display:none;">
 <div class="watermark">
 <img src="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/assets/images/logo.png'; ?>" alt="Watermark" style="width:600px;height:600px;object-fit:contain;filter:grayscale(1);">
 </div>
 <div class="doc-content">
 <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid #94a3b8;padding-bottom:12px;margin-bottom:20px;">
 <div style="display:flex;align-items:center;gap:16px;">
 <img src="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/assets/images/logo.png'; ?>" alt="Logo" style="width:72px;height:72px;object-fit:contain;">
 <div>
 <div style="font-size:22px;font-weight:900;color:#0f172a;">EDU Nexus</div>
 <div style="font-size:13px;font-weight:700;color:#475569;">منظومة التعليم الآمن</div>
 </div>
 </div>
 <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;text-align:right;">
 <div style="font-size:17px;font-weight:900;color:#1d4ed8;margin-bottom:8px;">استمارة تسجيل مقررات</div>
 <div style="font-size:12px;color:#64748b;margin-bottom:4px;">تاريخ الطباعة: <strong><?php echo date('Y-m-d'); ?></strong></div>
 <div style="font-size:12px;color:#64748b;margin-bottom:4px;">اسم الطالب: <strong><?php echo htmlspecialchars($full_name); ?></strong></div>
 <div style="font-size:12px;color:#64748b;margin-bottom:4px;">رقم الطالب: <strong><?php echo htmlspecialchars($username); ?></strong></div>
 <div style="font-size:12px;color:#64748b;">الفصل الدراسي: <strong>ربيع 2026</strong></div>
 </div>
 </div>
 <h2 style="font-size:16px;font-weight:900;color:#1e3a5f;margin-bottom:12px;">المقررات المسجلة</h2>
 <table>
 <thead>
 <tr>
 <th>كود المقرر</th><th>اسم المقرر</th><th style="text-align:center;">الساعات المعتمدة</th>
 </tr>
 </thead>
 <tbody>
 <?php 
 $total_hours_print = 0;
 foreach ($available_courses as $c):
 if (!in_array((int)($c['id'] ?? 0), $current_course_ids)) continue;
 $total_hours_print += (int)($c['credit_hours'] ?? 0);
 ?>
 <tr>
 <td><?php echo htmlspecialchars($c['code'] ?? ''); ?></td>
 <td><?php echo htmlspecialchars($c['name'] ?? ''); ?></td>
 <td style="text-align:center;"><?php echo htmlspecialchars($c['credit_hours'] ?? ''); ?></td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 <tfoot>
 <tr style="background:#f1f5f9;font-weight:900;">
 <td colspan="2" style="text-align:left;">إجمالي الساعات المسجلة:</td>
 <td style="text-align:center;"><?php echo $total_hours_print; ?></td>
 </tr>
 </tfoot>
 </table>
 <div style="display:flex;justify-content:space-between;margin-top:48px;padding-top:16px;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">
 <div style="text-align:center;">توقيع الطالب<div style="margin-top:32px;border-top:1px solid #94a3b8;width:150px;"></div></div>
 <div style="text-align:center;">المرشد الأكاديمي: <?php echo htmlspecialchars($advisor_name); ?><div style="margin-top:32px;border-top:1px solid #94a3b8;width:180px;"></div></div>
 <div style="text-align:center;">توقيع شئون الطلاب<div style="margin-top:32px;border-top:1px solid #94a3b8;width:150px;"></div></div>
 </div>
 </div>
 </div>
</div>

<script>
let isSaved = <?php echo ($message && strpos($message, 'bg-green') !== false) ? 'true' : 'false'; ?>;
const MAX_HOURS = <?php echo $max_hours; ?>;

// عداد الساعات اللي بيحدث نفسه لما تختار مواد
document.querySelectorAll('.reg-check').forEach(function(cb) {
 cb.addEventListener('change', function() {
 isSaved = false;
 updateHours();
 updatePrintBtn();
 });
});

function updateHours() {
 let total = 0;
 document.querySelectorAll('.reg-check:checked').forEach(function(cb) {
 total += parseInt(cb.dataset.hours || 0);
 });
 const el = document.getElementById('total-hours');
 if (el) {
 el.textContent = total;
 el.closest('.bg-bg') && (el.closest('.bg-bg').className =
 total > MAX_HOURS
 ? el.closest('.bg-bg').className.replace('bg-bg text-primary', 'bg-primary text-white')
 : el.closest('.bg-bg').className);
 }
 return total;
}

function updatePrintBtn() {
 const btn = document.getElementById('printBtn');
 if (!btn) return;
 if (isSaved) {
 btn.className = 'px-5 py-2 rounded-lg text-sm font-bold transition bg-primary text-white hover:bg-primary cursor-pointer';
 } else {
 btn.className = 'px-5 py-2 rounded-lg text-sm font-bold transition bg-bg text-gray-400 cursor-not-allowed';
 }
}

function beforeSubmit() {
 const checked = document.querySelectorAll('.reg-check:checked').length;
 if (checked === 0) {
 alert('الرجاء اختيار مقرر واحد على الأقل قبل الحفظ.');
 return false;
 }
 const total = updateHours();
 if (total > MAX_HOURS) {
 alert('❌ عذراً، لا يمكنك تجاوز الحد الأقصى للساعات المسموح به (' + MAX_HOURS + ' ساعة) بناءً على معدلك التراكمي.');
 return false;
 }
 return true;
}

function handlePrint(e) {
 if (!isSaved) {
 alert('يجب حفظ التسجيل أولاً قبل الطباعة.');
 return false;
 }
 var content = document.getElementById('reg-print-area');
 if (!content) { window.print(); return; }
 var styles = '';
 document.querySelectorAll('style').forEach(function(s) { styles += s.outerHTML; });
 styles += '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap">';
 var win = window.open('', '_blank', 'width=950,height=750');
 win.document.write('<html dir="rtl" lang="ar"><head><meta charset="UTF-8"><title>استمارة تسجيل</title>' + styles);
 win.document.write('<style>body{font-family:"Cairo",Arial,sans-serif;margin:0;padding:24px 32px;background:#fff;direction:rtl;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #cbd5e1;padding:10px 14px;text-align:right;} thead{background:#1e3a5f;color:#fff;} .watermark{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);opacity:0.06;pointer-events:none;z-index:0;} .doc-content{position:relative;z-index:1;}</style>');
 win.document.write('</head><body>');
 win.document.write(content.innerHTML);
 win.document.write('</body></html>');
 win.document.close();
 setTimeout(function() { win.focus(); win.print(); }, 800);
}

window.addEventListener('DOMContentLoaded', function() {
 updatePrintBtn();
 updateHours();
});
</script>

<?php require_once 'includes/footer.php'; ?>