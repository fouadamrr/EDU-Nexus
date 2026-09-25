<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['affairs', 'admin', 'dean', 'super_admin'])) {
    header('Location: index.php');
    exit;
}
/*
 * صفحة إدارة مدفوعات الكتب - خاصة بشئون الطلاب والإدارة
 */
require_once 'includes/header.php';
/** @var PDO $pdo */
/** @var string $role */
require_once __DIR__ . '/../controllers/TextbookController.php';


$ctrl           = new TextbookController();
$confirmed_by   = (int)$_SESSION['user_id'];
$college_id_ses = (int)($_SESSION['college_id'] ?? 0);
$message        = '';

// تنفيذ الأكشن (تأكيد دفع، رفض، أو تراجع)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action']     ?? '';
    $request_id = (int)($_POST['request_id'] ?? 0);
    $notes      = trim($_POST['notes'] ?? '');

    if ($action === 'confirm' && $request_id) {
        $ok = $ctrl->confirmPayment($request_id, $confirmed_by);
        $message = $ok
            ? "<div class='msg-ok'><i class='fas fa-check-circle'></i> تم تأكيد الدفع بنجاح.</div>"
            : "<div class='msg-err'><i class='fas fa-times-circle'></i> حدث خطأ أثناء التأكيد.</div>";
    } elseif ($action === 'reject' && $request_id) {
        $ok = $ctrl->rejectPayment($request_id, $confirmed_by, $notes);
        $message = $ok
            ? "<div class='msg-warn'><i class='fas fa-ban'></i> تم رفض الطلب.</div>"
            : "<div class='msg-err'><i class='fas fa-times-circle'></i> حدث خطأ.</div>";
    } elseif ($action === 'reset' && $request_id) {
        $ctrl->resetRequest($request_id);
        $message = "<div class='msg-warn'><i class='fas fa-undo'></i> تم إعادة الطلب لحالة الانتظار.</div>";
    }
}

// تصدير البيانات لملف إكسيل لو اليوزر طلب كدا
if (isset($_GET['export'])) {
    $exportCollege = ($role === 'affairs' || $role === 'dean') ? $college_id_ses : (int)($_GET['college_id'] ?? 0);
    $exportStatus  = $_GET['status'] ?? '';
    $exportLevel   = (int)($_GET['level'] ?? 0);
    $allRows       = $ctrl->getPaymentRequests($exportCollege, $exportLevel, $exportStatus);
    $ctrl->exportToCSV($allRows);
    exit;
}

// تطبيق الفلاتر المختارة
$filterCollege = ($role === 'affairs' || $role === 'dean')
    ? $college_id_ses
    : (int)($_GET['college_id'] ?? 0);
$filterLevel   = (int)($_GET['level']  ?? 0);
$filterStatus  = $_GET['status'] ?? '';

$requests    = $ctrl->getPaymentRequests($filterCollege, $filterLevel, $filterStatus);
$stats       = $ctrl->getAffairsStats($filterCollege);
$allColleges = in_array($role, ['super_admin', 'admin']) ? $ctrl->getAllColleges() : [];

$levelNames = [0=>'كل الفرق',1=>'الفرقة الأولى',2=>'الفرقة الثانية',
               3=>'الفرقة الثالثة',4=>'الفرقة الرابعة'];
$statusMap  = [''  =>'كل الحالات','pending'=>'بانتظار التأكيد',
               'paid'=>'تم الدفع','rejected'=>'مرفوض'];

// تظبيط اللينك بتاع التصدير عشان يحافظ على الفلاتر اللي شغالين بيها
$exportParams = http_build_query([
    'export'     => 1,
    'college_id' => $filterCollege,
    'level'      => $filterLevel,
    'status'     => $filterStatus,
]);
?>

<style>
.stats-grid    { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1rem; }
.stat-card     { background:#fff; border-radius:1.25rem; border:1px solid #e2e8f0;
                 box-shadow:0 1px 4px rgba(0,0,0,.06); padding:1.25rem; text-align:center;
                 transition:all .25s; }
.stat-card:hover{ box-shadow:0 6px 20px rgba(30,58,138,.1); transform:translateY(-2px); }
.stat-num      { font-size:2rem; font-weight:900; line-height:1; }
.stat-label    { font-size:.72rem; color:#64748b; font-weight:700; margin-top:.35rem;
                 text-transform:uppercase; letter-spacing:.05em; }
.filter-bar    { background:#fff; border-radius:1.25rem; border:1px solid #e2e8f0;
                 padding:1.25rem 1.5rem; display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end; }
.filter-bar select, .filter-bar input
               { background:#f8fafc; border:1px solid #e2e8f0; border-radius:.75rem;
                 padding:.6rem 1rem; font-size:.85rem; font-weight:700; outline:none;
                 transition:all .2s; min-width:160px; }
.filter-bar select:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.btn-filter    { background:#1e3a8a; color:#fff; padding:.65rem 1.5rem; border-radius:.75rem;
                 font-weight:900; font-size:.85rem; border:none; cursor:pointer; transition:all .2s; }
.btn-filter:hover { background:#1d4ed8; }
.btn-export    { background:#059669; color:#fff; padding:.65rem 1.5rem; border-radius:.75rem;
                 font-weight:900; font-size:.85rem; text-decoration:none; display:inline-flex;
                 align-items:center; gap:.5rem; transition:all .2s; }
.btn-export:hover { background:#047857; }
.tbl-wrap      { background:#fff; border-radius:1.25rem; border:1px solid #e2e8f0;
                 box-shadow:0 1px 4px rgba(0,0,0,.06); overflow:hidden; }
.tbl-wrap table{ width:100%; border-collapse:collapse; font-size:.84rem; }
.tbl-wrap thead th { background:#f8fafc; color:#64748b; font-weight:900; font-size:.72rem;
                     text-transform:uppercase; letter-spacing:.05em; padding:1rem 1.25rem;
                     border-bottom:1px solid #e2e8f0; white-space:nowrap; }
.tbl-wrap tbody tr { border-bottom:1px solid #f1f5f9; transition:background .15s; }
.tbl-wrap tbody tr:hover { background:#f8fafc; }
.tbl-wrap tbody td { padding:.9rem 1.25rem; color:#1e293b; vertical-align:middle; }
.badge         { display:inline-flex; align-items:center; gap:.3rem; padding:.3rem .7rem;
                 border-radius:999px; font-size:.72rem; font-weight:900; white-space:nowrap; }
.badge-pending { background:#fef9c3; color:#92400e; border:1px solid #fde68a; }
.badge-paid    { background:#f0fdf4; color:#15803d; border:1px solid #86efac; }
.badge-rejected{ background:#fff1f2; color:#9f1239; border:1px solid #fecdd3; }
.action-btn    { width:34px; height:34px; border-radius:.6rem; border:none; cursor:pointer;
                 display:inline-flex; align-items:center; justify-content:center;
                 font-size:.8rem; transition:all .2s; }
.btn-confirm   { background:#f0fdf4; color:#15803d; }
.btn-confirm:hover{ background:#15803d; color:#fff; }
.btn-reject    { background:#fff1f2; color:#9f1239; }
.btn-reject:hover { background:#9f1239; color:#fff; }
.btn-undo      { background:#f1f5f9; color:#475569; }
.btn-undo:hover{ background:#475569; color:#fff; }
.empty-state   { text-align:center; padding:4rem 2rem; color:#94a3b8; }
.msg-ok,.msg-err,.msg-warn
               { border-radius:1rem; padding:1rem 1.5rem; font-weight:700;
                 display:flex; align-items:center; gap:.75rem; margin-bottom:1rem; }
.msg-ok   { background:#f0fdf4; color:#166534; border:1px solid #86efac; }
.msg-err  { background:#fff1f2; color:#9f1239; border:1px solid #fecdd3; }
.msg-warn { background:#fef9c3; color:#92400e; border:1px solid #fde68a; }
</style>

<div class="max-w-7xl mx-auto pb-12 animate-fade-in-up space-y-6">

    <!-- Banner -->
    <div class="relative overflow-hidden bg-gradient-to-r from-indigo-700 via-primary to-blue-600 rounded-2xl p-8 text-white shadow-card border border-white/10">
        <div class="absolute -right-16 -top-16 w-56 h-56 bg-white opacity-10 rounded-full blur-3xl"></div>
        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
            <div>
                <h1 class="text-3xl font-black text-white mb-1 flex items-center gap-3">
                    <i class="fas fa-file-invoice-dollar"></i> إدارة مدفوعات الكتب الدراسية
                </h1>
                <p class="text-white/80 font-medium">
                    تأكيد طلبات الدفع وتمكين الطلاب من تحميل كتبهم المقررة.
                </p>
                <?php if (!empty($_SESSION['college_name'])): ?>
                <div class="mt-3 inline-flex items-center gap-2 bg-white/15 border border-white/25 rounded-xl px-4 py-1.5 text-sm font-black">
                    <i class="fas fa-university"></i>
                    <?php echo htmlspecialchars($_SESSION['college_name']); ?>
                </div>
                <?php endif; ?>
            </div>
            <a href="?<?php echo $exportParams; ?>" class="btn-export text-sm">
                <i class="fas fa-file-excel"></i> تصدير Excel / CSV
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <?php
        $statCards = [
            ['num'=>$stats['total'],   'label'=>'إجمالي الطلبات','color'=>'text-primary',  'bg'=>'bg-blue-50',   'icon'=>'fa-layer-group'],
            ['num'=>$stats['pending'], 'label'=>'بانتظار التأكيد','color'=>'text-amber-600','bg'=>'bg-amber-50',  'icon'=>'fa-clock'],
            ['num'=>$stats['paid'],    'label'=>'تم الدفع',       'color'=>'text-emerald-600','bg'=>'bg-emerald-50','icon'=>'fa-check-circle'],
            ['num'=>$stats['rejected'],'label'=>'مرفوض',          'color'=>'text-rose-600', 'bg'=>'bg-rose-50',   'icon'=>'fa-times-circle'],
        ];
        foreach ($statCards as $sc): ?>
        <div class="stat-card">
            <div class="w-12 h-12 <?php echo $sc['bg']; ?> <?php echo $sc['color']; ?> rounded-2xl flex items-center justify-center mx-auto mb-3 text-xl">
                <i class="fas <?php echo $sc['icon']; ?>"></i>
            </div>
            <div class="stat-num <?php echo $sc['color']; ?>"><?php echo $sc['num']; ?></div>
            <div class="stat-label"><?php echo $sc['label']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php echo $message; ?>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <?php if (!empty($allColleges)): ?>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-black text-slate-400 uppercase tracking-widest">الكلية</label>
            <select name="college_id">
                <option value="0">— كل الكليات —</option>
                <?php foreach ($allColleges as $col): ?>
                <option value="<?php echo $col['id']; ?>" <?php echo $filterCollege == $col['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($col['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-black text-slate-400 uppercase tracking-widest">الفرقة</label>
            <select name="level">
                <?php foreach ($levelNames as $lv => $lname): ?>
                <option value="<?php echo $lv; ?>" <?php echo $filterLevel == $lv ? 'selected' : ''; ?>>
                    <?php echo $lname; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-black text-slate-400 uppercase tracking-widest">الحالة</label>
            <select name="status">
                <?php foreach ($statusMap as $sv => $sl): ?>
                <option value="<?php echo $sv; ?>" <?php echo $filterStatus === $sv ? 'selected' : ''; ?>>
                    <?php echo $sl; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn-filter">
            <i class="fas fa-search ml-2"></i> تصفية
        </button>
        <?php if ($filterCollege || $filterLevel || $filterStatus): ?>
        <a href="textbook_payments.php" class="btn-filter" style="background:#64748b;">
            <i class="fas fa-times ml-2"></i> إعادة ضبط
        </a>
        <?php endif; ?>

        <div class="mr-auto text-sm font-black text-slate-500 self-center">
            <?php echo count($requests); ?> نتيجة
        </div>
    </form>

    <!-- Requests Table -->
    <div class="tbl-wrap">
        <?php if (empty($requests)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox text-5xl block mb-4 opacity-30"></i>
            <h3 class="text-xl font-black text-slate-400 mb-2">لا توجد طلبات مطابقة</h3>
            <p class="text-slate-300 text-sm">جرّب تغيير الفلاتر أعلاه.</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الطالب</th>
                    <th>الكلية / الفرقة</th>
                    <th>الكتاب</th>
                    <th>السعر</th>
                    <th>الحالة</th>
                    <th>تاريخ الطلب</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $i => $r):
                $bookTitle  = $r['book_title'] ?? $r['book_name'] ?? '—';
                $lvlName    = $levelNames[(int)($r['book_level'] ?? 0)] ?? '—';
                $statusBadge = match($r['status'] ?? '') {
                    'paid'     => "<span class='badge badge-paid'><i class='fas fa-check-circle'></i> تم الدفع</span>",
                    'rejected' => "<span class='badge badge-rejected'><i class='fas fa-times-circle'></i> مرفوض</span>",
                    default    => "<span class='badge badge-pending'><i class='fas fa-clock'></i> انتظار</span>",
                };
            ?>
            <tr>
                <td class="text-slate-400 font-mono text-xs"><?php echo $i + 1; ?></td>
                <td>
                    <div class="font-black text-slate-800"><?php echo htmlspecialchars($r['student_name'] ?? '—'); ?></div>
                    <div class="text-xs text-slate-400 font-mono">@<?php echo htmlspecialchars($r['student_username'] ?? ''); ?></div>
                </td>
                <td>
                    <div class="text-xs font-bold text-slate-600"><?php echo htmlspecialchars($r['college_name'] ?? '—'); ?></div>
                    <div class="text-xs text-slate-400 mt-0.5"><?php echo $lvlName; ?></div>
                </td>
                <td class="font-bold text-slate-800 max-w-[200px]">
                    <div class="truncate" title="<?php echo htmlspecialchars($bookTitle); ?>">
                        <?php echo htmlspecialchars($bookTitle); ?>
                    </div>
                </td>
                <td class="font-black text-primary">
                    <?php echo $r['book_price'] > 0 ? number_format((float)$r['book_price'], 0) . ' ج.م' : '—'; ?>
                </td>
                <td><?php echo $statusBadge; ?></td>
                <td class="text-slate-500 text-xs">
                    <?php echo $r['requested_at'] ? date('Y-m-d', strtotime($r['requested_at'])) : '—'; ?>
                    <?php if ($r['paid_at']): ?>
                    <br><span class="text-emerald-600 font-bold">دُفع: <?php echo date('Y-m-d', strtotime($r['paid_at'])); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="flex items-center gap-1.5">
                        <?php if (($r['status'] ?? '') !== 'paid'): ?>
                        <!-- Confirm -->
                        <form method="POST" class="inline">
                            <input type="hidden" name="action"     value="confirm">
                            <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                            <button type="submit" class="action-btn btn-confirm"
                                    onclick="return confirm('تأكيد استلام الدفع؟')"
                                    title="تأكيد الدفع">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                        <?php endif; ?>

                        <?php if (($r['status'] ?? '') !== 'rejected'): ?>
                        <!-- Reject -->
                        <button class="action-btn btn-reject"
                                title="رفض"
                                onclick="openRejectModal(<?php echo $r['id']; ?>)">
                            <i class="fas fa-times"></i>
                        </button>
                        <?php endif; ?>

                        <?php if (($r['status'] ?? '') === 'paid'): ?>
                        <!-- Undo paid -->
                        <form method="POST" class="inline">
                            <input type="hidden" name="action"     value="reset">
                            <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                            <button type="submit" class="action-btn btn-undo"
                                    onclick="return confirm('إلغاء تأكيد الدفع وإعادته للانتظار؟')"
                                    title="تراجع">
                                <i class="fas fa-undo"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-[300] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 animate-scale-in">
        <h3 class="text-lg font-black text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-ban text-rose-600"></i> رفض طلب الدفع
        </h3>
        <form method="POST" id="rejectForm">
            <input type="hidden" name="action"     value="reject">
            <input type="hidden" name="request_id" id="rejectRequestId">
            <div class="mb-4">
                <label class="block text-xs font-black text-slate-500 mb-2 uppercase tracking-widest">سبب الرفض (اختياري)</label>
                <textarea name="notes" rows="3"
                          class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-rose-400 resize-none"
                          placeholder="أدخل سبب الرفض هنا..."></textarea>
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeRejectModal()"
                        class="flex-1 bg-slate-100 text-slate-600 py-3 rounded-xl font-black hover:bg-slate-200 transition">
                    إلغاء
                </button>
                <button type="submit"
                        class="flex-1 bg-rose-600 text-white py-3 rounded-xl font-black hover:bg-rose-700 transition shadow-lg shadow-rose-200">
                    <i class="fas fa-ban ml-1"></i> رفض الطلب
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(id) {
    document.getElementById('rejectRequestId').value = id;
    document.getElementById('rejectModal').classList.remove('hidden');
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) closeRejectModal();
});
</script>

<?php require_once 'includes/footer.php'; ?>

