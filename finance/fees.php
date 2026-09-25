<?php
require_once 'includes/header.php';
/** @var PDO $pdo */
/** @var string $role */
/** @var int $user_id */
/** @var string $full_name */
require_once __DIR__ . '/../controllers/ManageFeesController.php';

$isAdmin = in_array($role, ['super_admin', 'admin', 'dean', 'affairs']);
if ($isAdmin) {
    require_permission('financial');
}

$feesController = new ManageFeesController();

if (!$isAdmin) {
    // الجزء الخاص برؤية الطالب لمصروفاته
    $my_fees = $feesController->getStudentFees($user_id);
    
    $total_required = 0;
    $total_paid = 0;
    foreach ($my_fees as $f) {
        $total_required += (float)$f['amount'];
        if ($f['status'] === 'paid') $total_paid += (float)$f['amount'];
    }
    $total_remaining = $total_required - $total_paid;
?>

<div class="max-w-5xl mx-auto space-y-8 animate-fade-in">
    <!-- عنوان الصفحة للطلاب والترحيب -->
    <div class="relative overflow-hidden bg-gradient-to-r from-[#002d56] to-indigo-900 rounded-3xl shadow-xl p-10 text-white">
        <div class="absolute top-0 right-0 -mt-20 -mr-20 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
        <div class="relative z-10">
            <h1 class="text-4xl font-black mb-3 text-white">الرسوم الدراسية الخاصة بك</h1>
            <p class="text-slate-200 text-lg font-bold">مرحباً <?php echo explode(' ', $full_name)[0]; ?>، هنا يمكنك متابعة حالة سداد المصروفات الدراسية الخاصة بك.</p>
        </div>
    </div>

    <!-- كروت ملخص المصاريف -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center text-primary text-xl font-bold">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">إجمالي الرسوم</p>
                <p class="text-2xl font-bold text-slate-800"><?php echo number_format($total_required); ?> <span class="text-sm font-medium">ج.م</span></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-4 border-b-4 border-b-emerald-500">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 text-xl font-bold">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">المبلغ المسدد</p>
                <p class="text-2xl font-bold text-emerald-600"><?php echo number_format($total_paid); ?> <span class="text-sm font-medium">ج.م</span></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-4 border-b-4 border-b-orange-500">
            <div class="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center text-orange-600 text-xl font-bold">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">المبلغ المتبقي</p>
                <p class="text-2xl font-bold text-orange-600"><?php echo number_format($total_remaining); ?> <span class="text-sm font-medium">ج.م</span></p>
            </div>
        </div>
    </div>

    <!-- جدول تاريخ الرسوم والمطالبات -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 class="font-bold text-slate-700 flex items-center gap-2">
                <i class="fas fa-history text-primary"></i>
                سجل الرسوم والمطالبات
            </h3>
            <span class="text-xs font-bold text-slate-400 uppercase"><?php echo count($my_fees); ?> بند مسجل</span>
        </div>
        
        <?php if (empty($my_fees)): ?>
        <div class="p-16 text-center">
            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-receipt text-slate-200 text-3xl"></i>
            </div>
            <p class="text-slate-400 font-bold italic">لا توجد رسوم دراسية مسجلة في حسابك حالياً.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-right">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[11px] font-black uppercase tracking-widest border-b border-slate-100">
                        <th class="px-8 py-4 text-white">نوع الرسوم</th>
                        <th class="px-8 py-4 text-center">المبلغ</th>
                        <th class="px-8 py-4 text-center">تاريخ الاستحقاق</th>
                        <th class="px-8 py-4 text-center">الحالة</th>
                        <th class="px-8 py-4 text-center">الإجراء</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($my_fees as $fee): ?>
                    <tr class="hover:bg-slate-50/80 transition-colors group">
                        <td class="px-8 py-5">
                            <div class="font-bold text-slate-700 group-hover:text-primary transition-colors">
                                <?php echo htmlspecialchars($fee['description']); ?>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-0.5">سجل رقم #<?php echo $fee['id']; ?></div>
                        </td>
                        <td class="px-8 py-5 text-center font-black text-slate-800">
                            <?php echo number_format($fee['amount']); ?> ج.م
                        </td>
                        <td class="px-8 py-5 text-center text-slate-500 font-medium text-xs">
                            <i class="far fa-calendar-alt ml-1 opacity-70"></i>
                            <?php echo $fee['due_date']; ?>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <?php if ($fee['status'] === 'paid'): ?>
                            <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-600 px-4 py-1.5 rounded-full text-[10px] font-black border border-emerald-100">
                                <i class="fas fa-check-circle"></i> تم السداد
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-600 px-4 py-1.5 rounded-full text-[10px] font-black border border-amber-100">
                                <i class="fas fa-clock"></i> بانتظار السداد
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <?php if ($fee['status'] !== 'paid'): ?>
                            <a href="fawry_payment.php?fee_id=<?php echo $fee['id']; ?>" 
                               class="inline-flex items-center justify-center bg-white border border-slate-200 hover:border-orange-500 hover:shadow-lg px-4 py-2 rounded-xl transition-all active:scale-95 group/btn h-10 w-32">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/d/d4/Fawry_logo.png" alt="Fawry" class="h-4 group-hover/btn:scale-110 transition-transform">
                            </a>
                            <?php else: ?>
                            <span class="text-slate-300 text-[10px] font-bold italic">مكتمل</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ملاحظات المساعدة والتعليمات -->
    <div class="bg-indigo-50/50 border border-indigo-100 rounded-3xl p-8 flex items-start gap-6 shadow-sm">
        <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
            <i class="fas fa-info-circle text-xl"></i>
        </div>
        <div>
            <h4 class="font-black text-indigo-900 text-base mb-2">تعليمات سداد المصروفات</h4>
            <p class="text-indigo-700/70 text-xs leading-relaxed font-bold">
                يمكنك الآن السداد بسهولة عبر الضغط على زر <span class="text-orange-600">"ادفع فوري"</span> للحصول على كود الدفع، أو التوجه لمكتب الخزينة بالكلية للسداد اليدوي. يتم تحديث حالة السداد تلقائياً فور تأكيد العملية. في حال وجود أي استفسار، يرجى مراجعة إدارة شؤون الطلاب بكليتك.
            </p>
        </div>
    </div>
</div>

<?php 
    require_once 'includes/footer.php';
    exit;
}

// الجزء الخاص برؤية الإدارة (البحث والتحكم)
// Determine college scope
$session_college_id = (int)($_SESSION['college_id'] ?? 0);
$is_scoped = in_array($role, ['dean', 'affairs']); // locked to their college

// Filters
$search_name = trim($_GET['q'] ?? '');
$filter_college = isset($_GET['college_id']) ? (int)$_GET['college_id'] : 0;
$filter_level = isset($_GET['level']) ? (int)$_GET['level'] : 0;
$filter_status = $_GET['status'] ?? ''; // 'paid', 'pending', ''

// Fetch colleges for admin filter
$all_colleges = [];
if (!$is_scoped) {
    try {
        $stmt = $pdo->query("SELECT id, name FROM colleges ORDER BY name");
        $all_colleges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// بنجهز الاستعلام بتاع الداتا بيز حسب الفلاتر اللي اليوزر اختارها
$params = ['student'];
$where = "WHERE u.role = ?";

// College scope
$effective_college = $is_scoped ? $session_college_id : $filter_college;
if ($effective_college > 0) {
    $where .= " AND u.college_id = ?";
    $params[] = $effective_college;
}

// Level filter
if ($filter_level > 0) {
    $where .= " AND s.level = ?";
    $params[] = $filter_level;
}

// Name/username search
if ($search_name !== '') {
    $where .= " AND (u.full_name ILIKE ? OR u.username ILIKE ?)";
    $params[] = "%{$search_name}%";
    $params[] = "%{$search_name}%";
}

// Status filter (paid = students with all fees paid, pending = students with any unpaid)
$having = '';
if ($filter_status === 'paid') {
    $having = "HAVING SUM(CASE WHEN f.status != 'paid' THEN 1 ELSE 0 END) = 0 AND COUNT(f.id) > 0";
} elseif ($filter_status === 'pending') {
    $having = "HAVING SUM(CASE WHEN f.status != 'paid' THEN 1 ELSE 0 END) > 0";
}

$sql = "
    SELECT 
        u.id, 
        u.full_name, 
        u.username, 
        c.name AS college_name, 
        s.level,
        COALESCE(SUM(f.amount), 0) AS total_fees,
        COALESCE(SUM(CASE WHEN f.status = 'paid' THEN f.amount ELSE 0 END), 0) AS total_paid,
        COALESCE(SUM(CASE WHEN f.status != 'paid' THEN f.amount ELSE 0 END), 0) AS total_remaining,
        COUNT(f.id) AS fee_count,
        SUM(CASE WHEN f.status != 'paid' THEN 1 ELSE 0 END) AS unpaid_count
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN colleges c ON u.college_id = c.id
    LEFT JOIN fees f ON u.id = f.user_id
    {$where}
    GROUP BY u.id, u.full_name, u.username, c.name, s.level
    {$having}
    ORDER BY u.full_name
";

$students = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $students = [];
}

$level_names = [
    0 => 'الفرقة الإعدادية',
    1 => 'الفرقة الأولى',
    2 => 'الفرقة الثانية',
    3 => 'الفرقة الثالثة',
    4 => 'الفرقة الرابعة'
];

// Summary stats
$stats_total = count($students);
$stats_paid_all = 0;
$stats_pending = 0;
$stats_total_remaining = 0;
foreach ($students as $s) {
    if ((int)$s['unpaid_count'] === 0 && (int)$s['fee_count'] > 0) $stats_paid_all++;
    if ((int)$s['unpaid_count'] > 0) $stats_pending++;
    $stats_total_remaining += (float)$s['total_remaining'];
}
?>

<div class="max-w-7xl mx-auto space-y-6">

    <!-- عنوان الصفحة للإدارة -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-secondary flex items-center gap-2">
                <i class="fas fa-search-dollar text-primary bg-bg p-2 rounded-xl"></i>
                بحث في سدادات الطلاب
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                البحث والاستعلام عن حالة السداد لكل طالب
                <?php if ($is_scoped && isset($_SESSION['college_name'])): ?>
                — <span class="font-bold text-primary"><?php echo htmlspecialchars($_SESSION['college_name']); ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- منطقة الفلاتر والبحث -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <input type="hidden" name="tab" value="financial">

            <!-- Search -->
            <div class="flex flex-col gap-1.5 flex-1 min-w-[200px]">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">بحث بالاسم أو الرقم</label>
                <div class="relative">
                    <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search_name); ?>" 
                           placeholder="اسم الطالب أو الرقم الجامعي..."
                           class="w-full bg-bg border border-slate-200 rounded-xl pr-9 pl-4 py-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
            </div>

            <?php if (!$is_scoped): ?>
            <!-- College -->
            <div class="flex flex-col gap-1.5 min-w-[180px]">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">الكلية</label>
                <select name="college_id" class="bg-bg border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-primary outline-none">
                    <option value="0">— جميع الكليات —</option>
                    <?php foreach ($all_colleges as $col): ?>
                    <option value="<?php echo $col['id']; ?>" <?php echo $filter_college == $col['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($col['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Level -->
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">الفرقة</label>
                <select name="level" class="bg-bg border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-primary outline-none">
                    <option value="0">— جميع الفرق —</option>
                    <?php foreach ($level_names as $lv => $lname): ?>
                    <option value="<?php echo $lv; ?>" <?php echo $filter_level == $lv ? 'selected' : ''; ?>><?php echo $lname; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status -->
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">حالة السداد</label>
                <select name="status" class="bg-bg border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-primary outline-none">
                    <option value="">— الجميع —</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>لديهم رسوم متأخرة</option>
                    <option value="paid" <?php echo $filter_status === 'paid' ? 'selected' : ''; ?>>اكتمل السداد</option>
                </select>
            </div>

            <button type="submit" class="bg-primary text-white px-6 py-2.5 rounded-xl font-bold hover:bg-accent hover:text-white transition flex items-center gap-2 shadow-sm">
                <i class="fas fa-search"></i> بحث
            </button>
            <?php if ($search_name || $filter_college || $filter_level || $filter_status): ?>
            <a href="fees.php?tab=financial" class="bg-bg text-slate-600 px-4 py-2.5 rounded-xl font-bold hover:bg-slate-200 transition text-sm">
                <i class="fas fa-times ml-1"></i> مسح
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ملخص إحصائي عن نتائج البحث -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-bg flex items-center justify-center text-primary">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 font-bold">إجمالي الطلاب</p>
                <p class="text-2xl font-bold text-slate-800"><?php echo $stats_total; ?></p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-bg flex items-center justify-center text-primary">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 font-bold">مكتمل السداد</p>
                <p class="text-2xl font-bold text-primary"><?php echo $stats_paid_all; ?></p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-bg flex items-center justify-center text-primary">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 font-bold">لديهم متأخرات</p>
                <p class="text-2xl font-bold text-primary"><?php echo $stats_pending; ?></p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-bg flex items-center justify-center text-primary">
                <i class="fas fa-coins"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 font-bold">إجمالي المتبقي</p>
                <p class="text-lg font-bold text-primary"><?php echo number_format($stats_total_remaining); ?> ج.م</p>
            </div>
        </div>
    </div>

    <!-- جدول عرض النتائج -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-700 flex items-center gap-2">
                <i class="fas fa-list text-primary"></i>
                نتائج البحث
                <span class="bg-primary text-white text-xs font-bold px-2 py-0.5 rounded-full"><?php echo count($students); ?> طالب</span>
            </h2>
        </div>

        <?php if (empty($students)): ?>
        <div class="p-14 text-center text-slate-400 font-bold">
            <i class="fas fa-search text-4xl mb-3 block text-slate-200"></i>
            لا يوجد طلاب مطابقون لمعايير البحث.
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="bg-bg text-slate-500 font-bold uppercase text-xs border-b border-slate-100">
                    <tr>
                        <th class="px-5 py-3">الطالب</th>
                        <?php if (!$is_scoped && !$filter_college): ?>
                        <th class="px-5 py-3">الكلية</th>
                        <?php endif; ?>
                        <th class="px-5 py-3 text-center">الفرقة</th>
                        <th class="px-5 py-3 text-center">إجمالي الرسوم</th>
                        <th class="px-5 py-3 text-center">المسدد</th>
                        <th class="px-5 py-3 text-center">المتبقي</th>
                        <th class="px-5 py-3 text-center">الحالة</th>
                        <th class="px-5 py-3 text-center">تفاصيل</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($students as $s): 
                        $all_paid = ((int)$s['unpaid_count'] === 0 && (int)$s['fee_count'] > 0);
                        $no_fees = ((int)$s['fee_count'] === 0);
                    ?>
                    <tr class="hover:bg-bg transition group">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white font-bold text-sm shrink-0 
                                    <?php echo $no_fees ? 'bg-slate-300' : ($all_paid ? 'bg-emerald-500' : 'bg-primary'); ?>">
                                    <?php echo mb_substr($s['full_name'], 0, 1, 'UTF-8'); ?>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-800"><?php echo htmlspecialchars($s['full_name']); ?></div>
                                    <div class="text-xs text-slate-400 font-mono"><?php echo htmlspecialchars($s['username'] ?? ''); ?></div>
                                </div>
                            </div>
                        </td>
                        <?php if (!$is_scoped && !$filter_college): ?>
                        <td class="px-5 py-3 text-xs text-slate-600 font-medium"><?php echo htmlspecialchars($s['college_name'] ?? '—'); ?></td>
                        <?php endif; ?>
                        <td class="px-5 py-3 text-center">
                            <span class="bg-bg text-slate-600 px-2.5 py-1 rounded-lg text-xs font-bold">
                                <?php echo $level_names[$s['level'] ?? 0] ?? '—'; ?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center font-bold text-slate-700"><?php echo number_format($s['total_fees']); ?> ج.م</td>
                        <td class="px-5 py-3 text-center font-bold text-emerald-600"><?php echo number_format($s['total_paid']); ?> ج.م</td>
                        <td class="px-5 py-3 text-center font-bold <?php echo $s['total_remaining'] > 0 ? 'text-orange-600' : 'text-slate-400'; ?>">
                            <?php echo number_format($s['total_remaining']); ?> ج.م
                        </td>
                        <td class="px-5 py-3 text-center">
                            <?php if ($no_fees): ?>
                            <span class="bg-slate-100 text-slate-500 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-tight">لا رسوم</span>
                            <?php elseif ($all_paid): ?>
                            <span class="bg-emerald-50 text-emerald-600 px-3 py-1 rounded-full text-[10px] font-black flex items-center gap-1 justify-center border border-emerald-100">
                                <i class="fas fa-check-circle"></i> مكتمل
                            </span>
                            <?php else: ?>
                            <span class="bg-orange-50 text-orange-600 px-3 py-1 rounded-full text-[10px] font-black flex items-center gap-1 justify-center border border-orange-100">
                                <i class="fas fa-clock"></i> <?php echo $s['unpaid_count']; ?> متأخرة
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <a href="manage_fees.php?student_id=<?php echo $s['id']; ?>&tab=financial" 
                               class="inline-flex items-center gap-1 bg-primary text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-accent transition opacity-0 group-hover:opacity-100">
                                <i class="fas fa-eye"></i> عرض
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
