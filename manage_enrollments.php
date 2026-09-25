<?php
require_once 'includes/header.php';

if (!in_array($role, ['admin', 'dean', 'affairs', 'super_admin'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('registration');

require_once __DIR__ . '/controllers/ManageEnrollmentsController.php';

$ctrl = new ManageEnrollmentsController();
$message = '';

// ── التعامل مع الطلبات (الأكشنز) ────────────────────────────────────────────────────────────

// 1. تسجيل طالب في مادة (الأدمن بيقدر يسجل حتى لو فترة التسجيل مقفولة)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

 if ($_POST['action'] === 'enroll') {
 $res = $ctrl->handleEnrollRequest($_POST);
 if ($res) $message = $res;
 }

 // 2. Grant permission: open a course for a student (cross-level or closed period)
 if ($_POST['action'] === 'grant_permission') {
 $res = $ctrl->grantPermission((int)$_POST['student_id'], (int)$_POST['course_id'], $user_id);
 if ($res) $message = $res;
 }

 // 3. Revoke permission
 if ($_POST['action'] === 'revoke_permission') {
 $res = $ctrl->revokePermission((int)$_POST['student_id'], (int)$_POST['course_id']);
 if ($res) $message = $res;
 }
}

// 4. حذف تسجيل طالب من مادة
if (isset($_GET['delete'])) {
 $res = $ctrl->handleDeleteRequest((int)$_GET['delete']);
 if ($res) $message = $res;
}

// ── جلب البيانات من الداتا بيز ──────────────────────────────────────────────────────────────────────
$student_search = trim($_GET['sq'] ?? '');
$students = $ctrl->getStudents($student_search);
$courses = $ctrl->getCoursesWithLevel();
$display_enrollments = $ctrl->getEnrichedEnrollments();
$permissions = $ctrl->getAllPermissions();

$reg_open = false;
try {
 $db = Database::getConnection();
 $s = $db->query("SELECT value FROM settings WHERE key = 'registration_open' LIMIT 1")->fetchColumn();
 if ($s === '1') $reg_open = true;
} catch (Exception $e) { $reg_open = false; }
?>

<div class="max-w-6xl mx-auto space-y-8">

 <!-- هيدر الصفحة -->
 <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
 <div>
 <h2 class="text-2xl font-bold text-gray-800">
 <i class="fas fa-user-check text-accent ml-2"></i>
 إدارة تسجيل المقررات
 </h2>
 <p class="text-sm text-gray-500 mt-1">حالة التسجيل:
 <span class="font-bold <?php echo $reg_open ? 'text-primary' : 'text-primary'; ?>">
 <?php echo $reg_open ? '🟢 مفتوح' : '🔴 مغلق'; ?>
 </span>
 &mdash; يمكنك التسجيل لأي طالب بصرف النظر عن الحالة.
 </p>
 </div>
 <div class="flex flex-wrap gap-2">
 <button onclick="document.getElementById('permModal').classList.remove('hidden')"
 class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary transition shadow text-sm font-bold">
 <i class="fas fa-unlock-alt ml-1"></i> فتح مادة لطالب
 </button>
 <button onclick="document.getElementById('enrollModal').classList.remove('hidden')"
 class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-opacity-90 transition shadow text-sm font-bold">
 <i class="fas fa-plus ml-1"></i> تسجيل طالب في مادة
 </button>
 </div>
 </div>

 <?php echo $message; ?>

 <!-- بار البحث -->
 <div class="bg-white rounded-xl border border-gray-200 p-4">
 <form method="GET" class="flex gap-3 items-center">
 <div class="relative flex-1">
 <input type="text" name="sq" value="<?php echo htmlspecialchars($student_search); ?>"
 placeholder="ابحث عن طالب بالاسم أو رقم القيد..."
 class="w-full border border-gray-300 rounded-lg px-4 py-2.5 pl-10 text-sm focus:ring-2 focus:ring-primary">
 <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-sm"></i>
 </div>
 <button type="submit" class="bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-bold hover:bg-opacity-90 transition">
 <i class="fas fa-search ml-1"></i> بحث
 </button>
 <?php if ($student_search): ?>
 <a href="manage_enrollments.php" class="bg-bg text-gray-600 px-4 py-2.5 rounded-lg text-sm font-bold hover:bg-gray-200">
 <i class="fas fa-times ml-1"></i> مسح
 </a>
 <?php endif; ?>
 </form>
 <?php if ($student_search): ?>
 <p class="text-xs text-gray-500 mt-2">نتائج البحث عن: <strong><?php echo htmlspecialchars($student_search); ?></strong> (<?php echo count($students); ?> طالب)</p>
 <?php else: ?>
 <p class="text-xs text-gray-500 mt-2">إجمالي الطلاب: <strong><?php echo count($students); ?></strong></p>
 <?php endif; ?>
 </div>

 <div class="flex border-b border-gray-200 gap-1">
 <button onclick="showTab('enrollments')" id="tab-enrollments"
 class="tab-btn px-5 py-2 text-sm font-bold border-b-2 border-primary text-primary">
 قائمة التسجيلات
 </button>
 <button onclick="showTab('permissions')" id="tab-permissions"
 class="tab-btn px-5 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-primary">
 إذونات فتح المواد
 </button>
 </div>

 <!-- ── تابة قائمة التسجيلات ── -->
 <div id="tab-content-enrollments">
 <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
 <div class="overflow-x-auto">
 <table class="w-full text-right text-sm">
 <thead class="bg-bg border-b border-gray-200 text-gray-500 font-bold">
 <tr>
 <th class="p-4">#</th>
 <th class="p-4">اسم الطالب</th>
 <th class="p-4">رقم المستخدم</th>
 <th class="p-4">المقرر</th>
 <th class="p-4">الفصل الدراسي</th>
 <th class="p-4 text-center">إجراءات</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-100">
 <?php foreach ($display_enrollments as $en): ?>
 <tr class="hover:bg-bg transition">
 <td class="p-4 text-gray-400"><?php echo $en['id']; ?></td>
 <td class="p-4 font-bold text-gray-800"><?php echo htmlspecialchars($en['student_name']); ?></td>
 <td class="p-4 font-mono text-gray-600"><?php echo htmlspecialchars($en['student_username']); ?></td>
 <td class="p-4 text-primary font-bold">
 <?php echo htmlspecialchars($en['course_name']); ?>
 <span class="text-xs text-gray-400 font-normal">(<?php echo $en['course_code']; ?>)</span>
 </td>
 <td class="p-4 text-gray-500"><?php echo $en['semester']; ?></td>
 <td class="p-4 text-center">
 <div class="flex items-center justify-center gap-2">
 <a href="results.php?student_id=<?php echo $en['user_id']; ?>"
 class="text-xs bg-bg border border-primary text-primary hover:bg-primary px-3 py-1 rounded transition">
 <i class="fas fa-graduation-cap"></i> النتيجة
 </a>
 <a href="?delete=<?php echo $en['id']; ?>"
 class="text-xs bg-white border border-primary text-primary hover:bg-bg px-3 py-1 rounded transition"
 onclick="return confirm('هل أنت متأكد من إلغاء التسجيل؟')">
 <i class="fas fa-trash"></i> حذف
 </a>
 </div>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 </div>
 </div>

 <!-- ── تابة أذونات فتح المواد ── -->
 <div id="tab-content-permissions" class="hidden">
 <div class="bg-bg border border-primary rounded-xl p-4 mb-4 text-sm text-primary">
 <i class="fas fa-info-circle ml-1"></i>
 هذه القائمة تعرض المواد التي فتحتها للطلاب بشكل استثنائي (خارج مستواهم الدراسي أو في فترة مغلقة).
 الطالب سيراها في صفحة التسجيل مميزة بـ "مفتوح بإذن".
 </div>
 <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
 <?php if (empty($permissions)): ?>
 <div class="p-10 text-center text-gray-400 text-sm">
 <i class="fas fa-unlock-alt text-4xl mb-3 block text-gray-200"></i>
 لا توجد إذونات مفتوحة حالياً.
 </div>
 <?php else: ?>
 <div class="overflow-x-auto">
 <table class="w-full text-right text-sm">
 <thead class="bg-bg border-b border-primary text-primary font-bold">
 <tr>
 <th class="p-4">الطالب</th>
 <th class="p-4">المادة المفتوحة</th>
 <th class="p-4">فُتحت بواسطة</th>
 <th class="p-4">التاريخ</th>
 <th class="p-4 text-center">إلغاء</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-amber-50">
 <?php foreach ($permissions as $p): ?>
 <tr class="hover:bg-bg/50">
 <td class="p-4 font-bold"><?php echo htmlspecialchars($p['student_name']); ?></td>
 <td class="p-4 text-primary"><?php echo htmlspecialchars($p['course_name']); ?> <span class="text-xs text-gray-400">(<?php echo $p['course_code']; ?>)</span></td>
 <td class="p-4 text-gray-500"><?php echo htmlspecialchars($p['granted_by_name'] ?? 'الإدارة'); ?></td>
 <td class="p-4 text-gray-400 text-xs"><?php echo date('Y-m-d', strtotime($p['created_at'])); ?></td>
 <td class="p-4 text-center">
 <form method="POST" class="inline">
 <input type="hidden" name="action" value="revoke_permission">
 <input type="hidden" name="student_id" value="<?php echo $p['student_id']; ?>">
 <input type="hidden" name="course_id" value="<?php echo $p['course_id']; ?>">
 <button type="submit" class="text-xs bg-bg border border-primary text-primary hover:bg-primary px-3 py-1 rounded transition"
 onclick="return confirm('إلغاء الإذن؟')">
 <i class="fas fa-times"></i> إلغاء
 </button>
 </form>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 <?php endif; ?>
 </div>
 </div>
</div>

<!-- ── مودال: تسجيل طالب في مادة ── -->
<div id="enrollModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
 <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
 <div class="flex justify-between items-center mb-5 border-b pb-3">
 <h3 class="text-xl font-bold"><i class="fas fa-plus text-primary ml-2"></i>تسجيل طالب في مادة</h3>
 <button onclick="document.getElementById('enrollModal').classList.add('hidden')" class="text-gray-400 hover:text-primary">
 <i class="fas fa-times text-xl"></i>
 </button>
 </div>
 <form method="POST" class="space-y-4" id="enrollForm">
 <input type="hidden" name="action" value="enroll">
 <input type="hidden" name="student_id" id="enrollStudentId">
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">ابحث عن الطالب</label>
 <div class="relative">
 <input type="text" id="enrollStudentSearch" placeholder="اكتب الاسم أو رقم القيد للبحث..."
 class="w-full border border-gray-300 rounded-lg p-2.5 pr-10 focus:ring-2 focus:ring-primary text-sm"
 oninput="filterStudents('enroll', this.value)" autocomplete="off">
 <i class="fas fa-search absolute right-3 top-3 text-gray-400 text-sm"></i>
 </div>
 <div id="enrollStudentSelected" class="hidden mt-1 text-xs text-primary font-bold flex items-center gap-1">
 <i class="fas fa-check-circle"></i>
 <span id="enrollStudentSelectedName"></span>
 <button type="button" onclick="clearStudentSelection('enroll')" class="mr-auto text-primary hover:text-primary"><i class="fas fa-times"></i></button>
 </div>
 <div id="enrollStudentDropdown"
 class="hidden absolute z-50 bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-lg mt-1 max-h-56 overflow-y-auto text-sm"
 style="width: calc(100% - 3rem)">
 <?php foreach ($students as $s): ?>
 <div class="student-option p-3 hover:bg-primary/10 cursor-pointer border-b border-gray-100 last:border-0"
 data-id="<?php echo $s['id']; ?>"
 data-name="<?php echo htmlspecialchars($s['full_name']); ?>"
 data-username="<?php echo htmlspecialchars($s['username']); ?>"
 data-target="enroll"
 onclick="selectStudent(this, 'enroll')">
 <div class="font-bold text-gray-800"><?php echo htmlspecialchars($s['full_name']); ?></div>
 <div class="text-xs text-gray-400 font-mono"><?php echo htmlspecialchars($s['username']); ?></div>
 </div>
 <?php endforeach; ?>
 </div>
 </div>
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">المقرر</label>
 <select name="course_id" required class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-primary text-sm">
 <option value="">اختر المقرر...</option>
 <?php foreach ($courses as $c): ?>
 <option value="<?php echo $c['id']; ?>">السنة <?php echo $c['level'] ?: '?'; ?> - <?php echo htmlspecialchars($c['name']); ?> (<?php echo $c['code']; ?>)</option>
 <?php endforeach; ?>
 </select>
 </div>
 <div class="pt-3 flex gap-3">
 <button type="button" onclick="document.getElementById('enrollModal').classList.add('hidden')"
 class="flex-1 bg-bg py-2 rounded-lg font-bold hover:bg-gray-200">إلغاء</button>
 <button type="submit" id="enrollSubmitBtn" class="flex-1 bg-primary text-white py-2 rounded-lg font-bold hover:bg-opacity-90 shadow">حفظ التسجيل</button>
 </div>
 </form>
 </div>
</div>

<!-- ── مودال: فتح مادة لطالب (إذن خاص) ── -->
<div id="permModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
 <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
 <div class="flex justify-between items-center mb-5 border-b pb-3">
 <h3 class="text-xl font-bold"><i class="fas fa-unlock-alt text-primary ml-2"></i>فتح مادة لطالب</h3>
 <button onclick="document.getElementById('permModal').classList.add('hidden')" class="text-gray-400 hover:text-primary">
 <i class="fas fa-times text-xl"></i>
 </button>
 </div>
 <p class="text-sm text-gray-500 mb-4 bg-bg border border-primary rounded p-3">
 <i class="fas fa-info-circle text-accent ml-1"></i>
 يسمح هذا للطالب بتسجيل مادة من فرقة مختلفة أو في فترة التسجيل المغلقة.
 </p>
 <form method="POST" class="space-y-4" id="permForm">
 <input type="hidden" name="action" value="grant_permission">
 <input type="hidden" name="student_id" id="permStudentId">
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">ابحث عن الطالب</label>
 <div class="relative">
 <input type="text" id="permStudentSearch" placeholder="اكتب الاسم أو رقم القيد للبحث..."
 class="w-full border border-gray-300 rounded-lg p-2.5 pr-10 focus:ring-2 focus:ring-amber-400 text-sm"
 oninput="filterStudents('perm', this.value)" autocomplete="off">
 <i class="fas fa-search absolute right-3 top-3 text-gray-400 text-sm"></i>
 </div>
 <div id="permStudentSelected" class="hidden mt-1 text-xs text-primary font-bold flex items-center gap-1">
 <i class="fas fa-check-circle"></i>
 <span id="permStudentSelectedName"></span>
 <button type="button" onclick="clearStudentSelection('perm')" class="mr-auto text-primary hover:text-primary"><i class="fas fa-times"></i></button>
 </div>
 <div id="permStudentDropdown"
 class="hidden absolute z-50 bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-lg mt-1 max-h-56 overflow-y-auto text-sm"
 style="width: calc(100% - 3rem)">
 <?php foreach ($students as $s): ?>
 <div class="student-option p-3 hover:bg-bg cursor-pointer border-b border-gray-100 last:border-0"
 data-id="<?php echo $s['id']; ?>"
 data-name="<?php echo htmlspecialchars($s['full_name']); ?>"
 data-username="<?php echo htmlspecialchars($s['username']); ?>"
 data-target="perm"
 onclick="selectStudent(this, 'perm')">
 <div class="font-bold text-gray-800"><?php echo htmlspecialchars($s['full_name']); ?></div>
 <div class="text-xs text-gray-400 font-mono"><?php echo htmlspecialchars($s['username']); ?></div>
 </div>
 <?php endforeach; ?>
 </div>
 </div>
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">المادة المراد فتحها</label>
 <select name="course_id" required class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-amber-400 text-sm">
 <option value="">اختر المقرر...</option>
 <?php foreach ($courses as $c): ?>
 <option value="<?php echo $c['id']; ?>">السنة <?php echo $c['level'] ?: '?'; ?> - <?php echo htmlspecialchars($c['name']); ?> (<?php echo $c['code']; ?>)</option>
 <?php endforeach; ?>
 </select>
 </div>
 <div class="pt-3 flex gap-3">
 <button type="button" onclick="document.getElementById('permModal').classList.add('hidden')"
 class="flex-1 bg-bg py-2 rounded-lg font-bold hover:bg-gray-200">إلغاء</button>
 <button type="submit" class="flex-1 bg-primary text-white py-2 rounded-lg font-bold hover:bg-primary shadow">فتح المادة</button>
 </div>
 </form>
 </div>
</div>

<script>
function showTab(name) {
 ['enrollments', 'permissions'].forEach(function(t) {
 document.getElementById('tab-content-' + t).classList.add('hidden');
 const btn = document.getElementById('tab-' + t);
 btn.classList.remove('border-primary', 'text-secondary');
 btn.classList.add('border-transparent', 'text-gray-500');
 });
 document.getElementById('tab-content-' + name).classList.remove('hidden');
 const active = document.getElementById('tab-' + name);
 active.classList.add('border-primary', 'text-primary');
 active.classList.remove('border-transparent', 'text-gray-500');
}

// ── منطق البحث اللحظي عن الطلاب ─────────────────────────────────────────────
function filterStudents(prefix, query) {
 const dropdown = document.getElementById(prefix + 'StudentDropdown');
 const items = dropdown.querySelectorAll('.student-option');
 const q = query.trim().toLowerCase();

 // Clear previous selection when typing again
 document.getElementById(prefix + 'StudentId').value = '';
 document.getElementById(prefix + 'StudentSelected').classList.add('hidden');

 if (q.length < 1) {
 dropdown.classList.add('hidden');
 return;
 }

 let visibleCount = 0;
 items.forEach(function(item) {
 const name = item.dataset.name.toLowerCase();
 const uname = item.dataset.username.toLowerCase();
 if (name.includes(q) || uname.includes(q)) {
 item.style.display = '';
 visibleCount++;
 } else {
 item.style.display = 'none';
 }
 });

 dropdown.classList.toggle('hidden', visibleCount === 0);
}

function selectStudent(el, prefix) {
 const id = el.dataset.id;
 const name = el.dataset.name;
 const uname = el.dataset.username;

 document.getElementById(prefix + 'StudentId').value = id;
 document.getElementById(prefix + 'StudentSearch').value = name + ' (' + uname + ')';
 document.getElementById(prefix + 'StudentSelectedName').textContent = name + ' (' + uname + ')';
 document.getElementById(prefix + 'StudentSelected').classList.remove('hidden');
 document.getElementById(prefix + 'StudentDropdown').classList.add('hidden');
}

function clearStudentSelection(prefix) {
 document.getElementById(prefix + 'StudentId').value = '';
 document.getElementById(prefix + 'StudentSearch').value = '';
 document.getElementById(prefix + 'StudentSelected').classList.add('hidden');
 document.getElementById(prefix + 'StudentDropdown').classList.add('hidden');
}

// التأكد إن في طالب تم اختياره قبل ما يبعت الفورمة
document.getElementById('enrollForm').addEventListener('submit', function(e) {
 if (!document.getElementById('enrollStudentId').value) {
 e.preventDefault();
 alert('يرجى اختيار طالب من نتائج البحث أولاً');
 document.getElementById('enrollStudentSearch').focus();
 }
});
document.getElementById('permForm').addEventListener('submit', function(e) {
 if (!document.getElementById('permStudentId').value) {
 e.preventDefault();
 alert('يرجى اختيار طالب من نتائج البحث أولاً');
 document.getElementById('permStudentSearch').focus();
 }
});

// قفل القائمة المنسدلة لما يدوس في أي حتة بره
document.addEventListener('click', function(e) {
 ['enroll', 'perm'].forEach(function(p) {
 const input = document.getElementById(p + 'StudentSearch');
 const dropdown = document.getElementById(p + 'StudentDropdown');
 if (input && dropdown && !input.contains(e.target) && !dropdown.contains(e.target)) {
 dropdown.classList.add('hidden');
 }
 });
});
</script>

<?php require_once 'includes/footer.php'; ?>