<?php
require_once 'includes/header.php';
/** @var PDO $pdo */
/** @var string $role */
require_once __DIR__ . '/../controllers/ManageFeesController.php';

if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
require_permission('financial');

$controller = new ManageFeesController();
$message = '';

// ---- التعامل مع عمليات الإضافة والتحصيل والحذف ----
if ($role !== 'affairs' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $message = $controller->handleAddRequest($_POST);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_add') {
    $message = $controller->handleBulkAddRequest($_POST);
}
if ($role !== 'affairs' && isset($_GET['delete'])) {
    $message = $controller->handleDeleteRequest((int)$_GET['delete']);
}
if ($role !== 'affairs' && isset($_GET['pay'])) {
    $message = $controller->handlePayRequest((int)$_GET['pay']);
}

// ---- الفلاتر (التصفية) ----
$filter_college = isset($_GET['college_id']) ? (int)$_GET['college_id'] : 0;
$filter_level = isset($_GET['level']) ? (int)$_GET['level'] : 0;
$view_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

$all_colleges = [];
if (in_array($role, ['super_admin', 'admin'])) {
    $all_colleges = $controller->getAllColleges();
}

// بنشوف إيه الكلية اللي المفروض نعرض بياناتها (العميد وشؤون الطلاب بيشوفوا كليتهم بس)
$effective_college = 0;
if (in_array($role, ['dean', 'affairs'])) {
    $effective_college = (int)($_SESSION['college_id'] ?? 0);
} else {
    $effective_college = $filter_college;
}

$students_list = $controller->getStudentsFeeList($filter_college, $filter_level);
$students_for_modal = $controller->getStudents();

$level_names = [
    0 => 'الفرقة الإعدادية/عام',
    1 => 'الفرقة الأولى',
    2 => 'الفرقة الثانية',
    3 => 'الفرقة الثالثة',
    4 => 'الفرقة الرابعة'
];

// لو الأدمن بيشوف كشف حساب طالب واحد
$student_fees = [];
$student_info = null;
if ($view_student > 0) {
    $student_fees = $controller->getStudentFees($view_student);
    foreach ($students_list as $s) {
        if ($s['id'] == $view_student) { $student_info = $s; break; }
    }
    if (!$student_info) {
        $tmp = $controller->getStudentsFeeList(0, 0);
        foreach ($tmp as $s) {
            if ($s['id'] == $view_student) { $student_info = $s; break; }
        }
    }
}
?>

<div class="space-y-8 animate-fade-in-up max-w-7xl mx-auto">

    <!-- بانر الصفحة والترحيب -->
    <div class="relative overflow-hidden bg-gradient-to-r from-primary via-indigo-600 to-blue-600 rounded-3xl p-8 md:p-10 text-white shadow-card mb-8 border border-white/10 no-print">
        <div class="absolute -right-20 -top-20 w-64 h-64 bg-white opacity-10 rounded-full blur-3xl"></div>
        <div class="absolute -left-10 -bottom-10 w-48 h-48 bg-white opacity-10 rounded-full blur-2xl"></div>
        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6 group">
            <div class="text-center md:text-right text-white">
                <h1 class="text-3xl font-bold mb-3 text-white flex items-center gap-3">
                    <i class="fas fa-money-check-alt"></i>
                    إدارة المصروفات والشؤون المالية
                </h1>
                <p class="text-white/80 text-lg font-medium">متابعة رسوم الطلاب، تحصيل الأقساط الدراسية، وإدارة المستحقات المالية للمؤسسة.</p>
                <?php if (in_array($role, ['dean', 'affairs']) && isset($_SESSION['college_name'])): ?>
                <div class="mt-4 flex items-center gap-2 text-sm bg-white/20 backdrop-blur-md px-3 py-1.5 rounded-xl border border-white/30 w-fit mx-auto md:mx-0">
                    <i class="fas fa-school"></i> الكلية: <span class="font-black"><?php echo htmlspecialchars($_SESSION['college_name']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($role !== 'affairs'): ?>
            <button onclick="document.getElementById('feeModal').classList.remove('hidden'); document.getElementById('feeModal').classList.add('flex');" class="bg-white text-primary px-6 py-3.5 rounded-2xl font-black hover:bg-bg transition-all flex items-center gap-3 shadow-lg active:scale-95">
                <i class="fas fa-plus"></i> إضافة رسوم لطالب
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php echo $message; ?>

    <!-- ══ بار التصفية (Filters) ══ -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-6 no-print">
        <form method="GET" class="flex flex-wrap items-end gap-6">
            <input type="hidden" name="tab" value="financial">

            <?php if (in_array($role, ['super_admin', 'admin'])): ?>
            <div class="flex flex-col gap-2 min-w-[200px]">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mr-2">الكلية المعنية</label>
                <select name="college_id" class="bg-slate-50 border border-slate-200 rounded-xl px-5 py-3.5 text-sm font-bold text-slate-700 focus:ring-4 focus:ring-primary/10 outline-none transition-all">
                    <option value="0">— جميع الكليات —</option>
                    <?php foreach ($all_colleges as $col): ?>
                    <option value="<?php echo $col['id']; ?>" <?php echo $filter_college == $col['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($col['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="flex flex-col gap-2 min-w-[150px]">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mr-2">الفرقة الدراسية</label>
                <select name="level" class="bg-slate-50 border border-slate-200 rounded-xl px-5 py-3.5 text-sm font-bold text-slate-700 focus:ring-4 focus:ring-primary/10 outline-none transition-all">
                    <option value="0">— جميع الفرق —</option>
                    <?php foreach ($level_names as $lv => $lname): ?>
                    <option value="<?php echo $lv; ?>" <?php echo $filter_level == $lv ? 'selected' : ''; ?>>
                        <?php echo $lname; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-primary text-white px-8 py-3.5 rounded-2xl font-black hover:bg-indigo-700 transition flex items-center gap-2 shadow-lg shadow-primary/20">
                    <i class="fas fa-search"></i> تطبيق التصفية
                </button>
                <a href="?tab=epayments" class="bg-amber-100 text-amber-700 px-6 py-3.5 rounded-2xl font-black hover:bg-amber-200 transition flex items-center gap-2">
                    <i class="fas fa-bolt text-xs"></i> السجل الإلكتروني
                </a>
                <?php if ($filter_college || $filter_level): ?>
                <a href="manage_fees.php?tab=financial" class="bg-slate-100 text-slate-500 px-5 py-3.5 rounded-2xl font-black hover:bg-slate-200 transition text-xs">
                    <i class="fas fa-times ml-1"></i> إعادة ضبط
                </a>
                <?php endif; ?>
            </div>

            <?php
            $can_bulk = ($filter_college > 0 || $filter_level > 0 || in_array($role, ['dean', 'affairs'])) && $role !== 'affairs';
            if ($can_bulk): ?>
            <button type="button" onclick="openBulkModal()" class="bg-indigo-600 text-white px-8 py-3.5 rounded-2xl font-black hover:bg-indigo-700 transition flex items-center gap-3 shadow-lg shadow-indigo-600/20 mr-auto">
                <i class="fas fa-layer-group"></i> إضافة رسوم جماعية
            </button>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($view_student > 0 && $student_info): ?>
    <!-- ══ عرض تفاصيل كشف حساب طالب واحد ══ -->
    <div class="bg-white rounded-[3rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="bg-gradient-to-r from-primary via-indigo-600 to-blue-600 p-10 flex flex-col md:flex-row items-center justify-between gap-8 relative overflow-hidden text-white">
            <div class="absolute -right-20 -top-20 w-80 h-80 bg-white/10 rounded-full blur-3xl"></div>
            <div class="relative z-10 flex items-center gap-6">
                <div class="w-20 h-20 rounded-[1.5rem] bg-white/20 backdrop-blur-xl border border-white/30 flex items-center justify-center text-4xl font-black text-white shadow-2xl">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-black mb-2"><?php echo htmlspecialchars($student_info['full_name']); ?></h2>
                    <div class="flex flex-wrap items-center gap-4 text-white/70 text-xs font-bold">
                        <span class="bg-indigo-900/30 px-3 py-1 rounded-lg border border-white/10"><i class="fas fa-id-card ml-2"></i><?php echo htmlspecialchars($student_info['username'] ?? ''); ?></span>
                        <span><i class="fas fa-university ml-2"></i><?php echo htmlspecialchars($student_info['college_name'] ?? ''); ?></span>
                        <span><i class="fas fa-layer-group ml-2 text-indigo-300"></i><?php echo $level_names[$student_info['level'] ?? 0] ?? '—'; ?></span>
                    </div>
                </div>
            </div>
            <div class="relative z-10 flex gap-4">
                <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-[1.5rem] px-8 py-5 text-center min-w-[140px] group hover:bg-white/20 transition-all">
                    <p class="text-[10px] text-white/60 font-black uppercase tracking-widest mb-1">إجمالي المدفوع</p>
                    <p class="text-2xl font-black text-emerald-400 group-hover:scale-110 transition-transform"><?php echo number_format($student_info['total_paid']); ?><span class="text-xs font-bold mr-1">ج.م</span></p>
                </div>
                <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-[1.5rem] px-8 py-5 text-center min-w-[140px] group hover:bg-white/20 transition-all">
                    <p class="text-[10px] text-white/60 font-black uppercase tracking-widest mb-1">المتبقي المطلوب</p>
                    <p class="text-2xl font-black text-rose-400 group-hover:scale-110 transition-transform"><?php echo number_format($student_info['total_unpaid']); ?><span class="text-xs font-bold mr-1">ج.م</span></p>
                </div>
            </div>
        </div>

        <div class="p-6 bg-slate-50/50 border-b border-slate-100 flex items-center justify-between">
            <a href="manage_fees.php?tab=financial<?php echo $filter_college ? '&college_id='.$filter_college : ''; ?><?php echo $filter_level ? '&level='.$filter_level : ''; ?>"
               class="text-sm text-primary font-black hover:underline flex items-center gap-2">
                <i class="fas fa-arrow-right"></i> العودة لقائمة الطلاب والمصروفات الرئيسية
            </a>
        </div>

        <?php if (empty($student_fees)): ?>
        <div class="p-24 text-center group">
            <i class="fas fa-receipt text-slate-100 text-7xl mb-6 group-hover:rotate-6 transition-transform"></i>
            <h3 class="text-xl font-black text-slate-400">لا توجد رسوم مقيدة لهذا الطالب</h3>
            <p class="text-slate-300 text-xs mt-2">يمكنك إضافة بنود رسوم جديدة من خلال زر "إضافة رسوم" بالأعلى</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="bg-slate-50 text-slate-500 font-bold border-y border-slate-100">
                    <tr>
                        <th class="px-8 py-4 opacity-50">المعرف</th>
                        <th class="px-8 py-4">البيان المالي</th>
                        <th class="px-8 py-4">القيمة المالية</th>
                        <th class="px-8 py-4">آخر موعد للسداد</th>
                        <th class="px-8 py-4">حالة التحصيل</th>
                        <?php if ($role !== 'affairs'): ?><th class="px-8 py-4 text-center">إجراءات</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($student_fees as $f): ?>
                    <tr class="hover:bg-primary/5 transition-all group">
                        <td class="px-8 py-5 text-slate-400 font-mono text-xs"><?php echo $f['id']; ?></td>
                        <td class="px-8 py-5 font-black text-slate-800 tracking-tight"><?php echo htmlspecialchars($f['description'] ?? 'رسوم دراسية عامة'); ?></td>
                        <td class="px-8 py-5 font-black text-primary text-base"><?php echo number_format($f['amount'] ?? 0); ?> <span class="text-[10px] text-slate-400">ج.م</span></td>
                        <td class="px-8 py-5 text-slate-500 font-bold italic"><?php echo $f['due_date'] ?? '—'; ?></td>
                        <td class="px-8 py-5">
                            <?php if (($f['status'] ?? '') === 'paid'): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[10px] font-black bg-emerald-50 text-emerald-600 border border-emerald-100 shadow-sm shadow-emerald-200/50">
                                <i class="fas fa-check-circle"></i> تم التحصيل
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[10px] font-black bg-rose-50 text-rose-600 border border-rose-100 shadow-sm shadow-rose-200/50">
                                <i class="fas fa-clock"></i> بانتظار السداد
                            </span>
                            <?php endif; ?>
                        </td>
                        <?php if ($role !== 'affairs'): ?>
                        <td class="px-8 py-5">
                            <div class="flex items-center justify-center gap-2">
                                <?php if (($f['status'] ?? '') !== 'paid'): ?>
                                <a href="fawry_payment.php?fee_id=<?php echo $f['id']; ?>"
                                   class="w-16 h-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:border-orange-500 hover:shadow-sm transition-all"
                                   title="رابط دفع فوري">
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/d/d4/Fawry_logo.png" alt="Fawry" class="h-3">
                                </a>
                                <a href="?student_id=<?php echo $view_student; ?>&pay=<?php echo $f['id']; ?>&tab=financial"
                                   onclick="return confirm('هل تؤكد استلام المبلغ وتحصيل الرسوم بنجاح؟')"
                                   class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center hover:bg-emerald-600 hover:text-white transition-all shadow-sm border border-emerald-100"
                                   title="تسجيل عملية تحصيل يدوي">
                                    <i class="fas fa-check text-xs"></i>
                                </a>
                                <?php endif; ?>
                                <a href="?student_id=<?php echo $view_student; ?>&delete=<?php echo $f['id']; ?>&tab=financial"
                                   onclick="return confirm('هل أنت متأكد من حذف هذا السجل المالي نهائياً؟')"
                                   class="w-9 h-9 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-600 hover:text-white transition-all shadow-sm border border-rose-100"
                                   title="حذف">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                </a>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php elseif (isset($_GET['tab']) && $_GET['tab'] === 'epayments'): ?>
    <!-- ══ سجل المدفوعات الإلكترونية (E-Payments Log) ══ -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden animate-fade-in-up">
        <div class="p-8 border-b border-slate-100 flex items-center justify-between bg-orange-50/30">
            <h2 class="font-black text-slate-800 flex items-center gap-3">
                <i class="fas fa-bolt text-orange-500 bg-orange-50 p-2 rounded-xl text-sm"></i>
                سجل عمليات الدفع الإلكتروني (Fawry / Visa)
            </h2>
            <div class="text-xs font-bold text-slate-400">آخر تحديث: لحظي</div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="px-8 py-5">مرجع فوري</th>
                        <th class="px-8 py-5">الطالب</th>
                        <th class="px-8 py-5">المبلغ</th>
                        <th class="px-8 py-5">البيان</th>
                        <th class="px-8 py-5">تاريخ الطلب</th>
                        <th class="px-8 py-5">الحالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr class="hover:bg-orange-50/20 transition-all group">
                        <td class="px-8 py-5 font-mono text-orange-600 font-black">9432857102</td>
                        <td class="px-8 py-5 font-bold">أحمد محمد علي</td>
                        <td class="px-8 py-5 font-black">1,500 <span class="text-[10px] opacity-50">ج.م</span></td>
                        <td class="px-8 py-5 text-slate-500">رسوم القيد 2024</td>
                        <td class="px-8 py-5 text-slate-400 text-xs italic">2026-05-01 10:30</td>
                        <td class="px-8 py-5">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[10px] font-black bg-emerald-50 text-emerald-600 border border-emerald-100 shadow-sm shadow-emerald-200/50">
                                <i class="fas fa-check-circle"></i> نجحت العملية
                            </span>
                        </td>
                    </tr>
                    <tr class="hover:bg-orange-50/20 transition-all group">
                        <td class="px-8 py-5 font-mono text-orange-600 font-black">9104728563</td>
                        <td class="px-8 py-5 font-bold">سارة يوسف كمال</td>
                        <td class="px-8 py-5 font-black">2,400 <span class="text-[10px] opacity-50">ج.م</span></td>
                        <td class="px-8 py-5 text-slate-500">رسوم الكارنيه</td>
                        <td class="px-8 py-5 text-slate-400 text-xs italic">2026-05-02 08:15</td>
                        <td class="px-8 py-5">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[10px] font-black bg-amber-50 text-amber-600 border border-amber-100 shadow-sm shadow-amber-200/50">
                                <i class="fas fa-spinner fa-spin"></i> بانتظار السداد
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="p-6 bg-slate-50 border-t border-slate-100 text-center">
            <a href="manage_fees.php?tab=financial" class="text-xs text-primary font-black hover:underline">العودة للسجل المالي العام</a>
        </div>
    </div>

    <?php else: ?>

    <!-- ══ عرض قائمة الطلاب والمصروفات التراكمية ══ -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-8 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h2 class="font-black text-slate-800 flex items-center gap-3">
                <i class="fas fa-users text-primary bg-primary/10 p-2 rounded-xl text-sm"></i>
                بيانات المصروفات التراكمية للطلاب
                <span class="bg-primary/20 text-primary text-[10px] font-black px-3 py-1 rounded-full border border-primary/20"><?php echo count($students_list); ?> طالباً</span>
            </h2>
        </div>

        <?php if (empty($students_list)): ?>
        <div class="p-32 text-center group">
            <div class="w-24 h-24 bg-slate-50 rounded-[2rem] flex items-center justify-center mx-auto mb-6 text-slate-200 group-hover:scale-110 transition-transform">
                <i class="fas fa-search-dollar text-5xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-400">لا توجد بيانات مطابقة لخيارات التصفية</h3>
            <p class="text-slate-300 text-xs mt-2">يرجى التأكد من اختيار الكلية أو الفرقة بشكل صحيح</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="px-8 py-6">البيانات الأكاديمية للطالب</th>
                        <?php if (in_array($role, ['super_admin', 'admin']) && !$filter_college): ?>
                        <th class="px-8 py-6">الكلية</th>
                        <?php endif; ?>
                        <th class="px-8 py-6">المستوى الدراسي</th>
                        <th class="px-8 py-6 text-center">المبالغ المحصلة</th>
                        <th class="px-8 py-6 text-center">المبالغ المتبقية</th>
                        <th class="px-8 py-6 text-center">عدد المطالبات</th>
                        <th class="px-8 py-6 text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($students_list as $s): ?>
                    <tr class="hover:bg-primary/5 transition-all group">
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-4">
                                <div class="w-11 h-11 rounded-[1.2rem] bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all font-black text-base shadow-inner">
                                    <?php echo mb_substr($s['full_name'], 0, 1, 'UTF-8'); ?>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-black text-slate-800 text-sm"><?php echo htmlspecialchars($s['full_name']); ?></span>
                                    <span class="text-[11px] text-slate-400 font-mono tracking-wider">@<?php echo htmlspecialchars($s['username'] ?? ''); ?></span>
                                </div>
                            </div>
                        </td>
                        <?php if (in_array($role, ['super_admin', 'admin']) && !$filter_college): ?>
                        <td class="px-8 py-5 text-slate-600 text-xs font-bold"><?php echo htmlspecialchars($s['college_name'] ?? '—'); ?></td>
                        <?php endif; ?>
                        <td class="px-8 py-5">
                            <span class="inline-flex items-center px-4 py-1.5 rounded-xl text-[10px] font-black bg-white border border-slate-200 text-slate-500 shadow-sm">
                                <?php echo $level_names[$s['level'] ?? 0] ?? '—'; ?>
                            </span>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <span class="font-black text-base <?php echo $s['total_paid'] > 0 ? 'text-emerald-600' : 'text-slate-300'; ?>">
                                <?php echo number_format($s['total_paid']); ?> <span class="text-[9px] font-bold text-slate-400">ج.م</span>
                            </span>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <span class="font-black text-base <?php echo $s['total_unpaid'] > 0 ? 'text-rose-500' : 'text-slate-300'; ?>">
                                <?php echo number_format($s['total_unpaid']); ?> <span class="text-[9px] font-bold text-slate-400">ج.م</span>
                            </span>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <span class="inline-flex items-center justify-center min-w-[28px] h-7 bg-primary/10 text-primary rounded-lg text-xs font-black ring-2 ring-primary/5">
                                <?php echo (int)$s['fee_count']; ?>
                            </span>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <a href="?student_id=<?php echo $s['id']; ?>&tab=financial<?php echo $filter_college ? '&college_id='.$filter_college : ''; ?><?php echo $filter_level ? '&level='.$filter_level : ''; ?>"
                               class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-slate-900 text-white text-[11px] font-black hover:bg-primary hover:shadow-2xl hover:shadow-primary/40 transition-all duration-500 active:scale-95 whitespace-nowrap group">
                                <div class="w-6 h-6 rounded-lg bg-white/10 flex items-center justify-center group-hover:bg-white group-hover:text-primary transition-all duration-500">
                                    <i class="fas fa-file-invoice-dollar text-[10px]"></i>
                                </div>
                                <span class="tracking-tight">كشف الحساب</span>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<!-- ══ مودال إضافة رسوم جديدة ══ -->
<?php if ($role !== 'affairs'): ?>
<div id="feeModal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[200] items-center justify-center p-4 transition-all duration-300">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto transform scale-95 transition-all duration-300" id="feeModalContent">
        <div class="bg-primary p-4 text-white flex justify-between items-center sticky top-0 z-20">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center text-lg border border-white/20">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div>
                    <h3 class="text-base font-black leading-none">إصدار مطالبة مالية</h3>
                    <p class="text-white/60 text-[10px] mt-1">إضافة مديونية جديدة لحساب الطالب</p>
                </div>
            </div>
            <button onclick="document.getElementById('feeModal').classList.add('hidden'); document.getElementById('feeModal').classList.remove('flex');" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white text-white hover:text-primary transition-all flex items-center justify-center">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-3">
            <input type="hidden" name="action" value="add">
            <div>
                <label class="block text-[10px] font-black text-slate-400 mb-1.5 uppercase tracking-widest">الطالب المستهدف</label>
                <select name="student_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary/10 outline-none transition-all font-bold text-slate-700 text-sm">
                    <option value="">-- اختر الطالب --</option>
                    <?php foreach ($students_for_modal as $s): ?>
                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 mb-1.5 uppercase tracking-widest">بيان الرسوم</label>
                <input type="text" name="description" required placeholder="مثال: رسوم القيد 2024"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary/10 outline-none transition-all font-bold text-slate-700 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 mb-1.5 uppercase tracking-widest">المبلغ (ج.م)</label>
                    <input type="number" name="amount" required placeholder="0.00"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary/10 outline-none transition-all font-black text-slate-800 text-base">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 mb-1.5 uppercase tracking-widest">التاريخ</label>
                    <input type="date" name="due_date" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary/10 outline-none transition-all font-bold text-slate-700 text-sm">
                </div>
            </div>
            <div class="pt-4 flex gap-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('feeModal').classList.add('hidden'); document.getElementById('feeModal').classList.remove('flex');" class="flex-1 bg-slate-100 text-slate-500 py-3 rounded-xl font-black text-xs hover:bg-slate-200 transition-all">إلغاء</button>
                <button type="submit" class="flex-1 bg-primary text-white py-3 rounded-xl font-black text-xs shadow-md shadow-primary/20 hover:scale-105 active:scale-95 transition-all">إصدار المطالبة</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ══ مودال إضافة رسوم جماعية (Bulk) ══ -->
<div id="bulkFeeModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[250] hidden items-center justify-center p-4 transition-all duration-300">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto transform scale-95 transition-all duration-300" id="bulkFeeModalContent">
        <div class="bg-indigo-700 p-4 text-white flex justify-between items-center group relative overflow-hidden sticky top-0 z-20">
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
            <div class="flex items-center gap-4 relative z-10">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center text-lg border border-white/20">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div>
                    <h3 class="text-base font-black leading-none">إصدار رسوم جماعية</h3>
                    <p class="text-white/60 text-[10px] mt-1">تطبيق البند المالي على قطاع كامل</p>
                </div>
            </div>
            <button onclick="closeBulkModal()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white text-white hover:text-indigo-700 transition-all flex items-center justify-center relative z-10">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="bulk_add">

            <div class="bg-indigo-50/50 border border-indigo-100 rounded-xl p-3 space-y-2">
                <div class="text-[10px] font-black text-indigo-700 flex items-center gap-2">
                    <i class="fas fa-user-tag text-xs"></i> نطاق الاستهداف (الفلتر الحالي)
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <?php if (in_array($role, ['super_admin', 'admin'])): ?>
                    <div class="col-span-2">
                        <select name="bulk_college_id" class="w-full bg-white border border-indigo-100 rounded-lg px-3 py-2 text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-indigo-300">
                            <option value="0">— جميع الكليات —</option>
                            <?php foreach ($all_colleges as $col): ?>
                            <option value="<?php echo $col['id']; ?>" <?php echo $filter_college == $col['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($col['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="bulk_college_id" value="<?php echo (int)($_SESSION['college_id'] ?? 0); ?>">
                    <div class="col-span-1">
                        <div class="bg-white/70 text-slate-800 p-2 rounded-lg border border-indigo-100 text-[11px] font-black truncate"><?php echo htmlspecialchars($_SESSION['college_name'] ?? 'كليتك'); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="<?php echo in_array($role, ['super_admin', 'admin']) ? 'col-span-2' : 'col-span-1'; ?>">
                        <select name="bulk_level" class="w-full bg-white border border-indigo-100 rounded-lg px-3 py-2 text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-indigo-300">
                            <option value="0">— جميع الفرق —</option>
                            <?php foreach ($level_names as $lv => $lname): ?>
                            <option value="<?php echo $lv; ?>" <?php echo $filter_level == $lv ? 'selected' : ''; ?>><?php echo $lname; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 mb-1.5 uppercase tracking-widest">بيان البند المالي الموحد</label>
                    <input type="text" name="bulk_description" required placeholder="مثال: رسوم القيد 2024"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 font-bold text-slate-800 text-sm outline-none focus:ring-2 focus:ring-indigo-500/10 transition-all">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 mb-1.5 uppercase tracking-widest">المبلغ (ج.م)</label>
                        <input type="number" name="bulk_amount" required min="1" step="0.01" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 font-black text-indigo-700 text-base outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 mb-1.5 uppercase tracking-widest">آخر موعد</label>
                        <input type="date" name="bulk_due_date" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 font-bold text-slate-700 text-sm outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                </div>
            </div>

            <label class="flex items-center gap-3 bg-slate-50 border border-slate-100 p-3 rounded-xl cursor-pointer group hover:bg-indigo-50/30 transition-all">
                <input type="checkbox" name="skip_existing" value="1" checked class="w-4 h-4 accent-indigo-600 rounded cursor-pointer">
                <div>
                    <div class="font-black text-slate-800 text-xs">تجنب التكرار الذكي</div>
                    <p class="text-[10px] text-slate-400 font-medium">عدم إضافة الرسوم للمطالبات المماثلة</p>
                </div>
            </label>

            <div class="grid grid-cols-2 gap-3 pt-3">
                <button type="button" onclick="closeBulkModal()" class="bg-slate-100 text-slate-500 py-3 rounded-xl font-black text-xs hover:bg-slate-200 transition-all">إلغاء</button>
                <button type="submit" class="bg-indigo-700 text-white py-3 rounded-xl font-black text-xs shadow-lg shadow-indigo-700/20 hover:scale-105 active:scale-95 transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-rocket"></i> تنفيذ الصرف الجماعي
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openBulkModal() {
        const modal = document.getElementById('bulkFeeModal');
        const content = document.getElementById('bulkFeeModalContent');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => content.classList.remove('scale-95'), 10);
    }
    function closeBulkModal() {
        const modal = document.getElementById('bulkFeeModal');
        const content = document.getElementById('bulkFeeModalContent');
        content.classList.add('scale-95');
        setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 200);
    }
    // Close on backdrop click
    document.getElementById('bulkFeeModal').addEventListener('click', function(e) { if (e.target === this) closeBulkModal(); });
</script>

<style>
.animate-fade-in-up { animation: fadeInUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php require_once 'includes/footer.php'; ?>
