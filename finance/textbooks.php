<?php
// ???? ????? ???????? ??????
// ??? ?????? ????? ????? ??????? ???? ????? ??? ????? ???? ???? ??????
require_once 'includes/header.php';
require_once __DIR__ . '/../controllers/TextbookController.php';

// ????? ?? ???? ???? ?? ????
if (!isset($_SESSION['user_id']) || ($role ?? '') !== 'student') {
    header('Location: index.php');
    exit;
}

$student_id = (int)$_SESSION['user_id'];
$college_id = (int)($_SESSION['college_id'] ?? 0);

// ????? ?????? ???????? ????? ?????? ?? ?????? ???
$pdo_local = get_pdo();
$stRow = $pdo_local->prepare("SELECT level FROM students WHERE user_id = :uid LIMIT 1");
$stRow->execute([':uid' => $student_id]);
$studentLevel = (int)($stRow->fetchColumn() ?: 1);

$ctrl    = new TextbookController();
$message = '';

// ??????? ?? ????? ????? (??? ?????? ???? ??? ???? ??? ?????)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_book_id'])) {
    $book_id = (int)$_POST['request_book_id'];
    $result  = $ctrl->submitPaymentRequest($book_id, $student_id, $college_id);
    $isOk    = $result['success'];
    $message = "<div class='alert-msg " . ($isOk ? 'alert-ok' : 'alert-err') . "'>
                    <i class='fas " . ($isOk ? 'fa-check-circle' : 'fa-times-circle') . "'></i>
                    " . htmlspecialchars($result['message']) . "
                </div>";
}

// ????? ???? ????? ??????? ?????? ?? ??? ????? ??????
$books = $ctrl->getStudentTextbooks($student_id, $college_id, $studentLevel);

$levelNames = [0=>'???? ?????',1=>'?????? ??????',2=>'?????? ???????',
               3=>'?????? ???????',4=>'?????? ???????'];
?>

<style>
.book-grid        { display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:1.5rem; }
.book-card        { background:#fff; border-radius:1.25rem; border:1px solid #e2e8f0;
                    box-shadow:0 1px 4px rgba(0,0,0,.06); overflow:hidden;
                    display:flex; flex-direction:column; transition:all .3s; }
.book-card:hover  { box-shadow:0 8px 24px rgba(30,58,138,.12); transform:translateY(-3px); }
.book-cover       { height:160px; background:linear-gradient(135deg,#1e3a8a 0%,#2563eb 100%);
                    display:flex; align-items:center; justify-content:center; position:relative; }
.book-cover i     { font-size:4rem; color:rgba(255,255,255,.2); }
.level-badge      { position:absolute; top:.65rem; right:.65rem; background:rgba(255,255,255,.15);
                    backdrop-filter:blur(6px); color:#fff; font-size:.65rem; font-weight:900;
                    padding:.25rem .65rem; border-radius:999px; border:1px solid rgba(255,255,255,.25);
                    letter-spacing:.04em; }
.price-badge      { position:absolute; bottom:.65rem; left:.65rem; background:#f59e0b;
                    color:#fff; font-size:.7rem; font-weight:900;
                    padding:.25rem .65rem; border-radius:999px; }
.book-body        { padding:1.25rem; flex:1; display:flex; flex-direction:column; gap:.75rem; }
.book-title       { font-weight:900; color:#1e293b; font-size:.95rem; line-height:1.35; }
.book-instructor  { font-size:.75rem; color:#64748b; display:flex; align-items:center; gap:.4rem; }
.status-pending   { background:#fef9c3; color:#92400e; border:1px solid #fde68a; }
.status-paid      { background:#f0fdf4; color:#15803d; border:1px solid #86efac; }
.status-rejected  { background:#fff1f2; color:#9f1239; border:1px solid #fecdd3; }
.status-none      { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }
.status-badge     { border-radius:.5rem; padding:.35rem .75rem; font-size:.73rem; font-weight:900;
                    display:inline-flex; align-items:center; gap:.4rem; width:100%; justify-content:center; }
.btn-request      { background:linear-gradient(135deg,#1e3a8a,#2563eb); color:#fff; border:none;
                    padding:.7rem 1rem; border-radius:.75rem; font-weight:900; font-size:.82rem;
                    cursor:pointer; width:100%; transition:all .25s; display:flex; align-items:center;
                    justify-content:center; gap:.5rem; }
.btn-request:hover{ opacity:.9; transform:scale(1.02); }
.btn-download     { background:linear-gradient(135deg,#059669,#10b981); color:#fff; }
.btn-retry        { background:linear-gradient(135deg,#dc2626,#ef4444); color:#fff; }
.alert-msg        { border-radius:1rem; padding:1rem 1.5rem; font-weight:700;
                    display:flex; align-items:center; gap:.75rem; margin-bottom:1.5rem;
                    font-size:.9rem; }
.alert-ok         { background:#f0fdf4; color:#166534; border:1px solid #86efac; }
.alert-err        { background:#fff1f2; color:#9f1239; border:1px solid #fecdd3; }
.empty-state      { text-align:center; padding:5rem 2rem; color:#94a3b8; }
.empty-state i    { font-size:4rem; margin-bottom:1rem; display:block; }
</style>

<div class="max-w-7xl mx-auto pb-12 animate-fade-in-up space-y-8">

    <!-- ????? ?????? ???????? ??????? -->
    <div class="relative overflow-hidden bg-gradient-to-r from-primary to-accent rounded-2xl p-8 text-white shadow-card border border-white/10">
        <div class="absolute -right-16 -top-16 w-56 h-56 bg-white opacity-10 rounded-full blur-3xl"></div>
        <div class="absolute -left-8 -bottom-8 w-40 h-40 bg-white opacity-10 rounded-full blur-2xl"></div>
        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
            <div>
                <h1 class="text-3xl font-black text-white mb-2 flex items-center gap-3">
                    <i class="fas fa-book-open"></i> ????? ????????
                </h1>
                <p class="text-white/80 text-base font-medium">
                    ????? ??????? ??
                    <span class="font-black bg-white/20 px-3 py-0.5 rounded-lg">
                        <?php echo $levelNames[$studentLevel] ?? '?????'; ?>
                    </span>
                    — ???? ????? ?? ???? ?????? ?????? ?? ???????.
                </p>
            </div>
            <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl px-6 py-4 text-center min-w-[160px]">
                <p class="text-white/70 text-xs font-black uppercase tracking-widest mb-1">????? ???????</p>
                <p class="text-4xl font-black text-white"><?php echo count($books); ?></p>
            </div>
        </div>
    </div>


    <?php echo $message; ?>

    <!-- ??? ????? ?? ??? ???? (Grid) -->
    <?php if (empty($books)): ?>
    <div class="empty-state bg-white rounded-2xl border border-slate-100 shadow-sm">
        <i class="fas fa-book-open opacity-30"></i>
        <h3 class="text-xl font-black text-slate-500 mb-2">?? ???? ??? ????? ??????</h3>
        <p class="text-slate-400 text-sm max-w-xs mx-auto">?? ??? ??????? ???? ??? ?????? ???? ???? ??????.</p>
    </div>
    <?php else: ?>
    <div class="book-grid">
        <?php foreach ($books as $b):
            $bTitle      = $b['book_title'] ?? $b['title'] ?? '???? ?????';
            $bInstructor = $b['instructor_name'] ?? '—';
            $bLevel      = $levelNames[(int)($b['level'] ?? 0)] ?? '—';
            $bPrice      = (float)($b['price'] ?? 0);
            $pStatus     = $b['payment_status'] ?? null;  // null | pending | paid | rejected
            $bFile       = $b['file_name'] ?? basename($b['file_path'] ?? '');
            $bId         = (int)$b['id'];
        ?>
        <div class="book-card">
            <div class="book-cover">
                <i class="fas fa-book"></i>
                <span class="level-badge"><i class="fas fa-layer-group mr-1"></i><?php echo $bLevel; ?></span>
                <?php if ($bPrice > 0): ?>
                <span class="price-badge"><?php echo number_format($bPrice, 0); ?> ?.?</span>
                <?php endif; ?>
            </div>
            <div class="book-body">
                <div>
                    <h3 class="book-title"><?php echo htmlspecialchars($bTitle); ?></h3>
                    <p class="book-instructor">
                        <i class="fas fa-chalkboard-teacher text-primary"></i>
                        <?php echo htmlspecialchars($bInstructor); ?>
                    </p>
                </div>

                <!-- ???? ??????: ?????? ??????? ???????? ?? ??? ?????? -->
                <?php if ($pStatus === 'paid'): ?>
                    <div class="status-badge status-paid">
                        <i class="fas fa-check-circle"></i> ?? ????? ?????
                    </div>
                    <?php if ($bFile): ?>
                    <a href="uploads/books/<?php echo htmlspecialchars($b['file_name'] ?? basename($b['file_path'] ?? '')); ?>"
                       download class="btn-request btn-download">
                        <i class="fas fa-download"></i> ????? ??????
                    </a>
                    <?php endif; ?>

                <?php elseif ($pStatus === 'pending'): ?>
                    <div class="status-badge status-pending">
                        <i class="fas fa-clock"></i> ?? ?????? ????? ???? ??????
                    </div>

                <?php elseif ($pStatus === 'rejected'): ?>
                    <div class="status-badge status-rejected">
                        <i class="fas fa-times-circle"></i> ?? ??? ????? — ???? ??????
                    </div>
                    <form method="POST">
                        <input type="hidden" name="request_book_id" value="<?php echo $bId; ?>">
                        <button type="submit" class="btn-request btn-retry">
                            <i class="fas fa-redo"></i> ????? ?????
                        </button>
                    </form>

                <?php else: ?>
                    <div class="status-badge status-none">
                        <i class="fas fa-lock"></i> ???? — ??????? ?????
                    </div>
                    <form method="POST">
                        <input type="hidden" name="request_book_id" value="<?php echo $bId; ?>">
                        <button type="submit" class="btn-request"
                                onclick="return confirm('?? ???? ????? ??? ????? ???? ???????')">
                            <i class="fas fa-paper-plane"></i> ??? ????? ?? ??????
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>

