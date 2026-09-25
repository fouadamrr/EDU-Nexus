<?php
require_once 'includes/header.php';

// بنسمح للعمداء وموظفين الشؤون يدخلوا الصفحة دي كمان
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
require_permission('students');

$is_scoped = in_array($role, ['dean', 'affairs']);
$session_college_id = (int)($_SESSION['college_id'] ?? 0);

$colleges = $db->findAll('colleges');
$colleges_map = [];
foreach ($colleges as $c) { $colleges_map[$c['id']] = $c; }

$message = '';
// إضافة طالب جديد للسيستم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_student') {
    $target_college_id = $is_scoped ? $session_college_id : (int)($_POST['college_id'] ?? 0);
    $new_username = trim($_POST['username'] ?? '');
    $new_fullname = trim($_POST['full_name'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $new_national_id = trim($_POST['national_id'] ?? '');
    $new_major = trim($_POST['major'] ?? '');
    $new_level = (int)($_POST['level'] ?? 1);
    $enrollment_year = (int)($_POST['enrollment_year'] ?? date('Y'));

    if (!$target_college_id || !isset($colleges_map[$target_college_id])) {
        $message = '<div class="bg-rose-50 text-rose-600 p-4 rounded-xl mb-4">خطأ في الكلية</div>';
    } elseif (empty($new_username) || empty($new_fullname) || empty($new_password)) {
        $message = '<div class="bg-rose-50 text-rose-600 p-4 rounded-xl mb-4">يرجى ملء الحقول</div>';
    } else {
        $existing = $db->find('users', 'username', $new_username);
        if ($existing) {
            $message = '<div class="bg-rose-50 text-rose-600 p-4 rounded-xl mb-4">رقم القيد موجود</div>';
        } else {
            $new_id = $db->insert('users', [
                'username' => $new_username, 'password' => password_hash($new_password, PASSWORD_DEFAULT),
                'full_name' => $new_fullname, 'role' => 'student', 'college_id' => $target_college_id, 'status' => 'active',
            ]);
            $db->insert('student_details', [
                'user_id' => $new_id, 'college_id' => $target_college_id, 'national_id' => $new_national_id,
                'major' => $new_major, 'level' => $new_level, 'enrollment_status' => 'enrolled', 'gpa' => 0.0, 'enrollment_year' => $enrollment_year
            ]);
            $message = '<div class="bg-emerald-50 text-emerald-700 p-4 rounded-xl mb-4">تمت الإضافة بنجاح</div>';
        }
    }
}

// مسح طالب
if (isset($_GET['delete'])) {
    $del_uid = (int)$_GET['delete'];
    $target = $db->find('users', 'id', $del_uid);
    if ($target && $target['role'] === 'student') {
        if ($is_scoped && $target['college_id'] != $session_college_id) {
             $message = '<div class="bg-rose-50 text-rose-600 p-4 rounded-xl">غير مصرح لك</div>';
        } else {
            $db->delete('users', $del_uid);
            $message = '<div class="bg-amber-50 text-amber-700 p-4 rounded-xl mb-4">تم حذف الطالب بنجاح</div>';
        }
    }
}

// تجميع البيانات والبحث
$filter_college = $is_scoped ? $session_college_id : (int)($_GET['college'] ?? 0);
$search_query = trim($_GET['search'] ?? '');

$stuSql = "SELECT u.id, u.username, u.full_name, u.status, u.college_id, c.name AS _college_name 
FROM users u LEFT JOIN colleges c ON u.college_id = c.id WHERE u.role = 'student'";
$stuParams = [];
if ($filter_college > 0) { $stuSql .= ' AND u.college_id = :cid'; $stuParams[':cid'] = $filter_college; }
if (!empty($search_query)) { $stuSql .= ' AND (u.full_name ILIKE :sq OR u.username ILIKE :sq)'; $stuParams[':sq'] = '%' . $search_query . '%'; }

$stuSql .= ' ORDER BY c.name, u.full_name';
$stuStmt = $pdo->prepare($stuSql);
$stuStmt->execute($stuParams);
$all_students = $stuStmt->fetchAll();

$report_title = "قائمة الطلاب المقيدين بالجامعة";
$sub_title = $filter_college ? "كلية: " . $colleges_map[$filter_college]['name'] : "جميع الكليات";
$count_label = "إجمالي عدد الطلاب";
$count_value = count($all_students);
$logo_path = 'assets/images/logo.png';
$logo_b64 = file_exists($logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path)) : '';
?>

<style>
@media print {
    header, .no-print, .sidebar, #sidebar, footer { display: none !important; }
    body, html { background: #fff !important; margin: 0; padding: 0; }
    .print-container { width: 100% !important; max-width: none !important; box-shadow: none !important; border: none !important; padding: 0 !important; }
    
    body::before {
        content: ""; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
        width: 400px; height: 400px; background: url('<?= $logo_b64 ?>') no-repeat center;
        background-size: contain; opacity: 0.05; z-index: -1;
    }
    table { width: 100% !important; border: 1.5px solid #000 !important; border-collapse: collapse !important; font-size: 11px !important; }
    thead { display: table-header-group !important; }
    th, td { border: 1px solid #000 !important; padding: 8px !important; }
    th { background: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    
    .report-official-header {
        width: 100%; border-bottom: 3.5px solid #000; padding-bottom: 12px; margin-bottom: 20px;
        display: flex !important; align-items: center; justify-content: space-between;
    }
    .header-logo-box {
        width: 60px; height: 60px; border: 2px solid #000; border-radius: 12px;
        display: flex; align-items: center; justify-content: center; padding: 5px; margin-bottom: 5px;
    }
}
.report-official-header {
    width: 100%; border-bottom: 3.5px solid #334155; padding-bottom: 15px; margin-bottom: 25px;
    display: flex; align-items: center; justify-content: space-between;
}
.header-logo-box {
    width: 60px; height: 60px; border: 2px solid #000; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; padding: 5px; margin-bottom: 5px;
}
</style>

<div class="max-w-7xl mx-auto space-y-6 mt-6 print-container">
    <?= $message ?>

    <!-- الهيدر بتاع التحكم -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white p-6 rounded-3xl shadow-sm border border-slate-100 no-print">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-primary/10 text-primary rounded-xl flex items-center justify-center text-xl shadow-inner">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div>
                <h2 class="text-2xl font-black text-slate-800">قائمة تسجيل الطلاب</h2>
                <p class="text-sm text-slate-500 mt-0.5"><?= $is_scoped ? 'إدارة طلاب كليتك' : 'إدارة ومتابعة طلاب جميع الكليات' ?></p>
            </div>
        </div>
        <div class="flex gap-2">
            <button onclick="window.print()" class="bg-primary text-white px-6 py-3 rounded-2xl font-black shadow-lg hover:bg-slate-800 transition-all flex items-center gap-2">
                <i class="fas fa-print"></i> طباعة القائمة
            </button>
            <button onclick="openAddModal()" class="bg-indigo-50 text-primary border border-primary/20 px-6 py-3 rounded-2xl font-black hover:bg-white transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> إضافة طالب
            </button>
        </div>
    </div>

    <!-- الفلاتر والبحث -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-5 no-print">
        <form method="GET" class="flex flex-col sm:flex-row gap-3 items-end">
            <div class="flex-1 relative">
                <label class="block text-[10px] font-black text-slate-400 mb-1 uppercase tracking-widest">بحث بالاسم أو الرقم</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold focus:ring-4 focus:ring-primary/5 outline-none">
            </div>
            <?php if (!$is_scoped): ?>
            <div class="min-w-[200px]">
                <label class="block text-[10px] font-black text-slate-400 mb-1 uppercase tracking-widest">الكلية</label>
                <select name="college" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold outline-none">
                    <option value="0">جميع الكليات</option>
                    <?php foreach ($colleges as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filter_college === $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <button type="submit" class="bg-slate-800 text-white px-8 py-3 rounded-xl font-black text-sm whitespace-nowrap hover:bg-primary transition-all">تفعيل</button>
            <a href="all_students.php" class="bg-slate-100 text-slate-600 px-4 py-3 rounded-xl font-bold transition-colors text-sm whitespace-nowrap border border-slate-200">مسح</a>
        </form>
    </div>

    <!-- جدول البيانات -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-right text-sm">
            <thead>
                <tr><th colspan="6" class="p-0 border-none"><?php require_once 'includes/report_header_print.php'; ?></th></tr>
                <tr class="bg-slate-50 text-slate-400 text-xs font-black uppercase tracking-widest">
                    <th class="p-4 text-center w-12">#</th>
                    <th class="p-4 w-32">رقم القيد</th>
                    <th class="p-4">الاسم الكامل</th>
                    <th class="p-4 text-center">الكلية</th>
                    <th class="p-4 text-center">الحالة</th>
                    <th class="p-4 text-center no-print w-24">إجراء</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($all_students)): ?>
                <tr><td colspan="6" class="text-center py-20 text-slate-300 font-black tracking-widest uppercase">لا توجد بيانات متاحة</td></tr>
                <?php else: ?>
                <?php $counter = 1; foreach ($all_students as $s): ?>
                <tr class="hover:bg-slate-50/50 transition-all">
                    <td class="p-4 text-center text-slate-300 font-mono"><?= $counter++ ?></td>
                    <td class="p-4 font-black font-mono text-primary"><?= htmlspecialchars($s['username']) ?></td>
                    <td class="p-4 font-bold text-slate-800"><?= htmlspecialchars($s['full_name']) ?></td>
                    <td class="p-4 text-center">
                        <span class="inline-flex items-center gap-1.5 text-[10px] px-3 py-1 rounded-lg font-black bg-slate-50 text-slate-600 border border-slate-100">
                             كلية <?= htmlspecialchars($s['_college_name']) ?>
                        </span>
                    </td>
                    <td class="p-4 text-center">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-black border <?= $s['status']==='active' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-rose-50 text-rose-600 border-rose-100' ?>">
                            <?= $s['status']==='active' ? 'نشط' : 'موقوف' ?>
                        </span>
                    </td>
                    <td class="p-4 text-center no-print">
                         <a href="?delete=<?= $s['id'] ?>&college=<?= $filter_college ?>" class="text-rose-400 hover:text-rose-600 p-2 transition-all" onclick="return confirm('حذف نهائي؟');" title="حذف الطالب"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- الجنب بتاع إضافة طالب (مودال) -->
<div id="addStudentModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-md z-50 flex items-center justify-center p-4 transition-all opacity-0">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden transform scale-95 transition-all" id="addModalContent">
        <div class="bg-primary p-6 flex justify-between items-center text-white">
            <h3 class="text-xl font-black">إضافة طالب جديد</h3>
            <button onclick="closeAddModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-8 space-y-6">
            <input type="hidden" name="action" value="add_student">
            <?php if ($is_scoped): ?>
                <div class="bg-indigo-50 p-4 rounded-xl border border-primary/20 text-primary font-black text-sm flex items-center gap-3">
                    <i class="fas fa-university"></i> سيتم إضافة المحتوى لكلية: <?= htmlspecialchars($colleges_map[$session_college_id]['name']) ?>
                </div>
            <?php else: ?>
            <div>
                <label class="block text-xs font-black text-slate-400 mb-2 uppercase tracking-widest">الكلية</label>
                <select name="college_id" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 font-black">
                    <option value="">-- اختر الكلية --</option>
                    <?php foreach ($colleges as $c): ?>
                    <option value="<?= $c['id'] ?>">كلية <?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs font-black text-slate-400 mb-2">رقم القيد</label><input type="text" name="username" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 font-mono font-bold"></div>
                <div><label class="block text-xs font-black text-slate-400 mb-2">كلمة المرور</label><input type="text" name="password" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 font-bold"></div>
            </div>
            <div><label class="block text-xs font-black text-slate-400 mb-2">الاسم الكامل</label><input type="text" name="full_name" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 font-bold"></div>
            <button type="submit" class="w-full bg-primary text-white py-5 rounded-3xl font-black shadow-xl shadow-primary/20 hover:scale-[1.02] transition-all">حفظ البيانات</button>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('addStudentModal');
const modalContent = document.getElementById('addModalContent');
function openAddModal() { modal.classList.remove('hidden'); void modal.offsetWidth; modal.classList.remove('opacity-0'); modalContent.classList.remove('scale-95'); }
function closeAddModal() { modal.classList.add('opacity-0'); modalContent.classList.add('scale-95'); setTimeout(() => modal.classList.add('hidden'), 300); }
modal.addEventListener('click', e => { if (e.target === modal) closeAddModal(); });
</script>

<?php require_once 'includes/footer.php'; ?>
