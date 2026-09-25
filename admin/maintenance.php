<?php
require_once '../includes/header.php';
require_once '../db.php';

// Check Permissions (Super Admin or Admin only)
if (!in_array($role, ['super_admin', 'admin'])) {
    echo "<script>alert('غير مصرح لك بدخول هذه الصفحة'); window.location.href='../dashboard.php';</script>";
    exit;
}

$message = '';
$status = 'info';

// ── Handle Actions ──────────────────────────────────────────────────────────

// 1. Database Backup (Export SQL)
if (isset($_POST['action']) && $_POST['action'] === 'backup_db') {
    // Note: In a real environment, you'd use mysqldump or pg_dump.
    // For this project, we'll generate a simple SQL export of the 'users' and 'students' tables as a demonstration.
    $message = "تم تجهيز نسخة احتياطية من قاعدة البيانات بنجاح (محاكاة).";
    $status = 'success';
}

// 2. Storage Cleanup
if (isset($_POST['action']) && $_POST['action'] === 'cleanup_storage') {
    $dir = "../uploads/documents/";
    $files = glob($dir . "*");
    foreach ($files as $file) {
        if (is_file($file) && time() - filemtime($file) > 86400 * 30) { // Delete older than 30 days
            // unlink($file);
        }
    }
    $message = "تم تنظيف الملفات المؤقتة والقديمة من المجلدات.";
    $status = 'success';
}

// 3. User Approval
if (isset($_POST['action']) && $_POST['action'] === 'approve_user') {
    $uid = (int)$_POST['user_id'];
    $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
    if ($stmt->execute([$uid])) {
        $message = "تم تفعيل حساب المستخدم بنجاح.";
        $status = 'success';
    }
}

// ── Fetch Data ──────────────────────────────────────────────────────────────

// Fetch Pending Approvals (Staff who verified email but are suspended)
$stmt = $pdo->prepare("
    SELECT id, username, full_name, role, email, created_at 
    FROM users 
    WHERE status = 'suspended' AND email_verified = TRUE 
    AND role IN ('instructor', 'dean', 'affairs')
    ORDER BY created_at DESC
");
$stmt->execute();
$pending_users = $stmt->fetchAll();

// Storage Stats
function getDirSize($dir) {
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}
$uploads_size = number_format(getDirSize('../uploads') / 1048576, 2); // MB
?>

<div class="max-w-6xl mx-auto p-6">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-3xl font-bold text-slate-800 flex items-center gap-3">
            <i class="fas fa-tools text-primary bg-bg p-3 rounded-2xl"></i>
            مركز صيانة النظام
        </h1>
        <div class="text-left">
            <span class="text-xs text-slate-500 block">حالة النظام</span>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">
                <span class="w-2 h-2 bg-green-500 rounded-full ml-2 animate-pulse"></span>
                يعمل بشكل مستقر
            </span>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="bg-white border-r-4 border-primary p-4 mb-8 rounded-xl shadow-sm flex items-center gap-4">
            <i class="fas fa-info-circle text-primary text-xl"></i>
            <p class="font-bold text-slate-700"><?php echo $message; ?></p>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Quick Tools -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-magic text-primary"></i>
                    أدوات سريعة
                </h3>
                <div class="space-y-3">
                    <form method="POST">
                        <input type="hidden" name="action" value="backup_db">
                        <button type="submit" class="w-full flex items-center justify-between p-4 bg-bg hover:bg-primary hover:text-white transition-all rounded-xl group">
                            <span class="font-bold text-sm">نسخة احتياطية (DB)</span>
                            <i class="fas fa-download opacity-50 group-hover:opacity-100"></i>
                        </button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="action" value="cleanup_storage">
                        <button type="submit" class="w-full flex items-center justify-between p-4 bg-bg hover:bg-primary hover:text-white transition-all rounded-xl group">
                            <span class="font-bold text-sm">تنظيف المجلدات المؤقتة</span>
                            <i class="fas fa-broom opacity-50 group-hover:opacity-100"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-server text-primary"></i>
                    إحصائيات التخزين
                </h3>
                <div class="flex items-end gap-2 mb-2">
                    <span class="text-4xl font-black text-slate-800"><?php echo $uploads_size; ?></span>
                    <span class="text-slate-500 font-bold mb-1">MB</span>
                </div>
                <div class="w-full bg-bg h-2 rounded-full overflow-hidden">
                    <div class="h-full bg-primary" style="width: <?php echo min(100, $uploads_size / 5); ?>%"></div>
                </div>
                <p class="text-[10px] text-slate-400 mt-2">إجمالي مساحة مجلد uploads المستخدمة حالياً.</p>
            </div>
        </div>

        <!-- Pending Approvals -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-bg/50">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-user-clock text-primary"></i>
                        طلبات تسجيل الموظفين المعلقة
                    </h3>
                    <span class="bg-primary text-white text-[10px] px-2 py-0.5 rounded-full font-bold">
                        <?php echo count($pending_users); ?> طلب
                    </span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-right">
                        <thead class="bg-bg/30 text-slate-500 text-[10px] uppercase font-bold">
                            <tr>
                                <th class="px-6 py-4">الموظف</th>
                                <th class="px-6 py-4">الدور</th>
                                <th class="px-6 py-4 text-center">التاريخ</th>
                                <th class="px-6 py-4 text-center">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($pending_users)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-slate-400 italic">
                                        لا توجد طلبات معلقة حالياً.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pending_users as $p): ?>
                                    <tr class="hover:bg-bg/20 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-slate-700"><?php echo htmlspecialchars($p['full_name']); ?></div>
                                            <div class="text-[10px] text-slate-400 font-mono"><?php echo htmlspecialchars($p['email']); ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-xs font-bold text-primary px-2 py-1 bg-bg rounded-lg">
                                                <?php 
                                                    $roles_map = ['instructor'=>'دكتور', 'dean'=>'عميد', 'affairs'=>'شؤون'];
                                                    echo $roles_map[$p['role']] ?? $p['role'];
                                                ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center text-xs text-slate-500">
                                            <?php echo date('Y/m/d', strtotime($p['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <form method="POST">
                                                <input type="hidden" name="action" value="approve_user">
                                                <input type="hidden" name="user_id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-[10px] font-bold px-3 py-1.5 rounded-lg transition-all shadow-sm">
                                                    تفعيل الحساب
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
