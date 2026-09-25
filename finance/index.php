<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    header('Location: fees.php');
    exit;
}
require_once 'includes/header.php';
/** @var PDO $pdo */
/** @var string $role */
/** @var int $user_id */
/** @var string $full_name */
/** @var UniversityDB $db */

// Access Control: Only authorized roles can see the dashboard
$is_admin_level = in_array($role, ['super_admin', 'admin', 'dean', 'affairs']);

// Stats fetching
$stats = [
    'total_collected' => 0,
    'total_unpaid' => 0,
    'pending_docs' => 0,
    'student_count' => 0
];

try {
    $cid = $_SESSION['college_id'] ?? null;
    $college_condition = "";
    $params = [];
    if ($role !== 'super_admin' && $cid) {
        $college_condition = " AND u.college_id = :cid";
        $params[':cid'] = $cid;
    }

    // 1. Total Collected
    $sql = "SELECT SUM(amount) FROM fees f JOIN users u ON f.user_id = u.id WHERE f.status = 'paid' $college_condition";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats['total_collected'] = (float)$stmt->fetchColumn();

    // 2. Total Unpaid
    $sql = "SELECT SUM(amount) FROM fees f JOIN users u ON f.user_id = u.id WHERE f.status != 'paid' $college_condition";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats['total_unpaid'] = (float)$stmt->fetchColumn();

    // 3. Pending Grad Docs
    $doc_cond = ($role !== 'super_admin' && $cid) ? " AND college_id = :cid" : "";
    $sql = "SELECT COUNT(*) FROM graduation_certificate_requests WHERE status = 'pending' $doc_cond";
    $stmt = $pdo->prepare($sql);
    if ($doc_cond) $stmt->execute([':cid' => $cid]);
    else $stmt->execute();
    $stats['pending_docs'] = (int)$stmt->fetchColumn();

    // 4. Student Count
    $sql = "SELECT COUNT(*) FROM users u WHERE role = 'student' $college_condition";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats['student_count'] = (int)$stmt->fetchColumn();

} catch (Exception $e) {
    // Silently fail or log
}


?>

<div class="space-y-8 animate-fade-in-up">
    <!-- Welcome Section -->
    <div class="bg-white rounded-[2.5rem] p-8 md:p-12 shadow-sm border border-slate-100 relative overflow-hidden">
        <div class="absolute -right-20 -top-20 w-80 h-80 bg-primary/5 rounded-full blur-3xl"></div>
        <div class="relative z-10">
            <h2 class="text-3xl md:text-4xl font-black text-primary-dark mb-4">أهلاً بك في البوابة المالية ✨</h2>
            <p class="text-slate-500 text-lg max-w-2xl leading-relaxed">
                هذه هي لوحة التحكم الخاصة بالخدمات المالية والمستندات. يمكنك من هنا متابعة التحصيلات، مراجعة طلبات التخرج، وإصدار الوثائق الرسمية للطلاب.
            </p>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Collected -->
        <div class="bg-emerald-50 border border-emerald-100 p-6 rounded-[2rem] shadow-sm hover:shadow-md transition-all group">
            <div class="w-12 h-12 bg-emerald-600 rounded-2xl flex items-center justify-center text-white mb-4 shadow-lg shadow-emerald-200 group-hover:scale-110 transition-transform">
                <i class="fas fa-hand-holding-usd text-xl"></i>
            </div>
            <p class="text-xs font-black text-emerald-700/60 uppercase tracking-widest mb-1">إجمالي المحصل</p>
            <h3 class="text-3xl font-black text-emerald-900"><?php echo number_format($stats['total_collected']); ?> <span class="text-sm">ج.م</span></h3>
        </div>

        <!-- Unpaid -->
        <div class="bg-rose-50 border border-rose-100 p-6 rounded-[2rem] shadow-sm hover:shadow-md transition-all group">
            <div class="w-12 h-12 bg-rose-600 rounded-2xl flex items-center justify-center text-white mb-4 shadow-lg shadow-rose-200 group-hover:scale-110 transition-transform">
                <i class="fas fa-exclamation-triangle text-xl"></i>
            </div>
            <p class="text-xs font-black text-rose-700/60 uppercase tracking-widest mb-1">مستحقات متأخرة</p>
            <h3 class="text-3xl font-black text-rose-900"><?php echo number_format($stats['total_unpaid']); ?> <span class="text-sm">ج.م</span></h3>
        </div>

        <!-- Pending Docs -->
        <div class="bg-indigo-50 border border-indigo-100 p-6 rounded-[2rem] shadow-sm hover:shadow-md transition-all group">
            <div class="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center text-white mb-4 shadow-lg shadow-indigo-200 group-hover:scale-110 transition-transform">
                <i class="fas fa-graduation-cap text-xl"></i>
            </div>
            <p class="text-xs font-black text-indigo-700/60 uppercase tracking-widest mb-1">طلبات تخرج معلقة</p>
            <h3 class="text-3xl font-black text-indigo-900"><?php echo $stats['pending_docs']; ?> <span class="text-sm">طلب</span></h3>
        </div>

        <!-- Students -->
        <div class="bg-amber-50 border border-amber-100 p-6 rounded-[2rem] shadow-sm hover:shadow-md transition-all group">
            <div class="w-12 h-12 bg-amber-600 rounded-2xl flex items-center justify-center text-white mb-4 shadow-lg shadow-amber-200 group-hover:scale-110 transition-transform">
                <i class="fas fa-users text-xl"></i>
            </div>
            <p class="text-xs font-black text-amber-700/60 uppercase tracking-widest mb-1">إجمالي الطلاب</p>
            <h3 class="text-3xl font-black text-amber-900"><?php echo number_format($stats['student_count']); ?> <span class="text-sm">طالب</span></h3>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 pt-4">
        <div class="md:col-span-2 space-y-6">
            <h3 class="text-xl font-bold text-slate-800 flex items-center gap-3">
                <i class="fas fa-bolt text-gold"></i> إجراءات سريعة
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <a href="manage_fees.php" class="flex items-center gap-4 p-5 bg-white border border-slate-100 rounded-2xl hover:border-primary/30 hover:bg-slate-50 transition-all">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                        <i class="fas fa-plus-circle text-xl"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-800">إضافة رسوم دراسية</p>
                        <p class="text-[11px] text-slate-400">تطبيق مصروفات جديدة على الطلاب</p>
                    </div>
                </a>
                <a href="official_documents.php" class="flex items-center gap-4 p-5 bg-white border border-slate-100 rounded-2xl hover:border-primary/30 hover:bg-slate-50 transition-all">
                    <div class="w-12 h-12 bg-gold/10 rounded-xl flex items-center justify-center text-gold">
                        <i class="fas fa-file-invoice text-xl"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-800">توثيق المستندات</p>
                        <p class="text-[11px] text-slate-400">إصدار شهادات إثبات القيد والتخرج</p>
                    </div>
                </a>
                <a href="graduation_cert_review.php" class="flex items-center gap-4 p-5 bg-white border border-slate-100 rounded-2xl hover:border-primary/30 hover:bg-slate-50 transition-all">
                    <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center text-indigo-600">
                        <i class="fas fa-check-double text-xl"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-800">مراجعة طلبات التخرج</p>
                        <p class="text-[11px] text-slate-400">اعتماد طلبات الطلاب النهائية</p>
                    </div>
                </a>
                <a href="fees.php" class="flex items-center gap-4 p-5 bg-white border border-slate-100 rounded-2xl hover:border-primary/30 hover:bg-slate-50 transition-all">
                    <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center text-emerald-600">
                        <i class="fas fa-search-dollar text-xl"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-800">سدادات الطلاب</p>
                        <p class="text-[11px] text-slate-400">عرض وتصفية عمليات الدفع</p>
                    </div>
                </a>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] p-6 border border-slate-100 shadow-sm">
            <h3 class="font-bold text-slate-800 mb-6 flex items-center gap-2">
                <i class="fas fa-history text-slate-400"></i> آخر الحركات
            </h3>
            <div class="space-y-4">
                <?php
                // Get last 5 fee activities
                try {
                    $sql = "SELECT f.*, u.full_name FROM fees f JOIN users u ON f.user_id = u.id $college_condition ORDER BY f.id DESC LIMIT 5";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $recent = $stmt->fetchAll();
                    
                    if (empty($recent)): ?>
                        <div class="text-center py-8 text-slate-400 text-sm">لا توجد حركات مؤخراً</div>
                    <?php else: 
                        foreach ($recent as $r):
                            $is_paid = $r['status'] === 'paid';
                        ?>
                        <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <div class="w-8 h-8 rounded-lg <?php echo $is_paid ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600'; ?> flex items-center justify-center flex-shrink-0">
                                <i class="fas <?php echo $is_paid ? 'fa-check' : 'fa-clock'; ?> text-xs"></i>
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-[11px] font-bold text-slate-700 truncate"><?php echo htmlspecialchars($r['full_name']); ?></p>
                                <p class="text-[9px] text-slate-400 truncate"><?php echo htmlspecialchars($r['description']); ?></p>
                            </div>
                            <div class="mr-auto text-[10px] font-black text-primary-dark">
                                <?php echo number_format($r['amount']); ?>
                            </div>
                        </div>
                        <?php endforeach;
                    endif;
                } catch (Exception $e) {}
                ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>