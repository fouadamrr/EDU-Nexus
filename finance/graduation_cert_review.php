<?php
require_once 'includes/header.php';
/** @var PDO $pdo */
/** @var string $role */
/** @var UniversityDB $db */

// الشؤون والعميد والأدمن فقط
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

// إنشاء الجدول لو مش موجود وتحديثه بالأعمدة الجديدة
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS graduation_certificate_requests (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL,
        college_id INT,
        status VARCHAR(20) DEFAULT 'pending',
        is_reissue BOOLEAN DEFAULT FALSE,
        photo_path VARCHAR(500),
        national_id_path VARCHAR(500),
        birth_cert_path VARCHAR(500),
        affairs_review VARCHAR(20) DEFAULT 'pending',
        affairs_note TEXT,
        affairs_reviewed_at TIMESTAMP,
        dean_review VARCHAR(20) DEFAULT 'pending',
        dean_note TEXT,
        dean_reviewed_at TIMESTAMP,
        fee_paid BOOLEAN DEFAULT FALSE,
        fee_amount INT DEFAULT 800,
        clearance_receipt VARCHAR(100),
        certificate_receipt VARCHAR(100),
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP DEFAULT NOW()
    )");
    // التأكد من وجود الأعمدة الجديدة لو الجدول قديم
    $pdo->exec("ALTER TABLE graduation_certificate_requests ADD COLUMN IF NOT EXISTS clearance_receipt VARCHAR(100)");
    $pdo->exec("ALTER TABLE graduation_certificate_requests ADD COLUMN IF NOT EXISTS certificate_receipt VARCHAR(100)");
} catch (Exception $e) {}

$msg = '';
$msg_type = '';

// معالجة المراجعة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_action'])) {
    $req_id  = (int)($_POST['req_id'] ?? 0);
    $action  = $_POST['review_action'] ?? '';
    $note    = trim($_POST['note'] ?? '');
    $clearance = trim($_POST['clearance_receipt'] ?? '');
    $cert_rec  = trim($_POST['certificate_receipt'] ?? '');

    if ($req_id && in_array($action, ['approved','rejected'])) {
        try {
            // جلب حالة الطلب الحالية
            $stmt = $pdo->prepare("SELECT affairs_review, dean_review FROM graduation_certificate_requests WHERE id = ?");
            $stmt->execute([$req_id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) throw new Exception("الطلب غير موجود.");

            // هل الشخص هو الشؤون أو أدمن والطلب لسه عند الشؤون؟
            if ($role === 'affairs' || ($role === 'admin' && $current['affairs_review'] === 'pending')) {
                $pdo->prepare("UPDATE graduation_certificate_requests SET affairs_review=:a, affairs_note=:n, clearance_receipt=:c1, certificate_receipt=:c2, affairs_reviewed_at=NOW(), updated_at=NOW() WHERE id=:id")
                    ->execute([':a'=>$action,':n'=>$note,':c1'=>$clearance,':c2'=>$cert_rec,':id'=>$req_id]);
                
                if ($action === 'rejected') {
                    $pdo->prepare("UPDATE graduation_certificate_requests SET status='rejected', updated_at=NOW() WHERE id=:id")->execute([':id'=>$req_id]);
                }
                $msg = $action === 'approved' ? 'تمت موافقة الشؤون بنجاح ✓' : 'تم رفض الطلب من الشؤون ✗';
                $msg_type = $action === 'approved' ? 'success' : 'danger';
            } 
            // هل الشخص هو العميد أو أدمن والطلب مستني العميد؟ (مسموح بالتجاوز حتى لو الشؤون لسه)
            elseif ($role === 'dean' || ($role === 'admin' && $current['dean_review'] === 'pending')) {
                $pdo->prepare("UPDATE graduation_certificate_requests SET dean_review=:a, dean_note=:n, dean_reviewed_at=NOW(), updated_at=NOW() WHERE id=:id")
                    ->execute([':a'=>$action,':n'=>$note,':id'=>$req_id]);
                
                $final_status = $action === 'approved' ? 'approved' : 'rejected';
                $pdo->prepare("UPDATE graduation_certificate_requests SET status=:s, updated_at=NOW() WHERE id=:id")->execute([':s'=>$final_status,':id'=>$req_id]);
                
                $msg = $action === 'approved' ? 'تم الاعتماد النهائي من العميد ✓' : 'تم الرفض النهائي من العميد ✗';
                $msg_type = $action === 'approved' ? 'success' : 'danger';
            }
        } catch (Exception $e) {
            $msg = 'حدث خطأ: ' . $e->getMessage();
            $msg_type = 'danger';
        }
    }
}

// جلب الطلبات
try {
    $college_filter = '';
    $params = [];
    if (in_array($role, ['dean','affairs']) && isset($_SESSION['college_id'])) {
        $college_filter = ' AND r.college_id = :cid';
        $params[':cid'] = $_SESSION['college_id'];
    }

    $status_filter = $_GET['status'] ?? 'all';
    $status_sql = '';
    if ($status_filter !== 'all') {
        $status_sql = " AND r.status = :st";
        $params[':st'] = $status_filter;
    }

    // للشؤون: يشوف الطلبات اللي لسه مستنياه (affairs_review=pending)
    $role_filter = '';
    if ($role === 'affairs') {
        // يشوف كل الطلبات مش بس اللي عنده
    } elseif ($role === 'dean') {
        // العميد يشوف اللي اتوافق عليها من الشؤون
    }

    $sql = "SELECT r.*, u.full_name, u.username, u.college_id as ucid,
                   c.name as college_name
            FROM graduation_certificate_requests r
            JOIN users u ON u.id = r.user_id
            LEFT JOIN colleges c ON c.id = r.college_id
            WHERE 1=1 {$college_filter} {$status_sql}
            ORDER BY r.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $requests = [];
}

$status_labels = [
    'pending'  => ['label'=>'قيد المراجعة','class'=>'status-pending','icon'=>'fa-clock'],
    'approved' => ['label'=>'مقبول',        'class'=>'status-passed','icon'=>'fa-check-circle'],
    'rejected' => ['label'=>'مرفوض',        'class'=>'status-failed','icon'=>'fa-times-circle'],
];

$counts = ['all'=>count($requests),'pending'=>0,'approved'=>0,'rejected'=>0];
foreach ($requests as $r) {
    if (isset($counts[$r['status']])) $counts[$r['status']]++;
}
?>

<div class="max-w-7xl mx-auto space-y-6">

  <!-- Header -->
  <div class="flex items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
    <div class="w-14 h-14 rounded-2xl bg-purple-600 flex items-center justify-center shadow-lg">
      <i class="fas fa-graduation-cap text-white text-2xl"></i>
    </div>
    <div>
      <h2 class="text-2xl font-bold text-slate-800">مراجعة طلبات شهادة التخرج</h2>
      <p class="text-sm text-slate-500">
        <?php echo $role === 'affairs' ? 'شعبة شؤون الطلاب والتعليم' : 'اعتماد العميد للشهادات'; ?>
      </p>
    </div>
    <div class="mr-auto flex gap-2">
      <span class="px-4 py-2 bg-amber-100 text-amber-700 rounded-xl font-bold text-sm">
        <?php echo $counts['pending']; ?> قيد المراجعة
      </span>
      <span class="px-4 py-2 bg-emerald-100 text-emerald-700 rounded-xl font-bold text-sm">
        <?php echo $counts['approved']; ?> مقبول
      </span>
    </div>
  </div>

  <?php if ($msg): ?>
  <div class="<?php echo $msg_type==='success'?'bg-emerald-50 border-emerald-200 text-emerald-800':'bg-red-50 border-red-200 text-red-800'; ?> border rounded-2xl p-4 flex items-center gap-3">
    <i class="fas <?php echo $msg_type==='success'?'fa-check-circle text-emerald-500':'fa-exclamation-triangle text-red-500'; ?> text-xl"></i>
    <span class="font-semibold"><?php echo $msg; ?></span>
  </div>
  <?php endif; ?>

  <!-- Filter Tabs -->
  <div class="flex gap-2 flex-wrap">
    <?php foreach (['all'=>'الكل','pending'=>'قيد المراجعة','approved'=>'مقبول','rejected'=>'مرفوض'] as $k=>$v): ?>
    <a href="?status=<?php echo $k; ?>"
       class="px-5 py-2.5 rounded-xl font-bold text-sm transition-all <?php echo $status_filter===$k?'bg-indigo-600 text-white shadow-lg':'bg-white text-slate-600 border border-slate-200 hover:border-indigo-300'; ?>">
      <?php echo $v; ?>
      <span class="<?php echo $status_filter===$k?'bg-white/20':'bg-slate-100'; ?> text-xs px-2 py-0.5 rounded-full mr-1">
        <?php echo $counts[$k] ?? 0; ?>
      </span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- الجدول -->
  <?php if (empty($requests)): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-16 text-center">
    <i class="fas fa-inbox text-5xl text-slate-200 mb-4"></i>
    <h3 class="font-bold text-slate-400 text-lg">لا توجد طلبات</h3>
  </div>
  <?php else: ?>
  <div class="space-y-4">
    <?php foreach ($requests as $req): ?>
    <?php
    $st = $status_labels[$req['status']] ?? $status_labels['pending'];
    $af = $status_labels[$req['affairs_review']] ?? $status_labels['pending'];
    $dn = $status_labels[$req['dean_review']] ?? $status_labels['pending'];
    $can_affairs_review = (in_array($role, ['affairs', 'admin']) && $req['affairs_review'] === 'pending');
    $can_dean_review = (in_array($role,['dean','admin']) && $req['dean_review'] === 'pending');
    ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="flex flex-col md:flex-row">
        <!-- بيانات الطالب -->
        <div class="flex-1 p-5">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center font-black text-indigo-600">
              <?php echo mb_substr($req['full_name'], 0, 1, 'UTF-8'); ?>
            </div>
            <div>
              <div class="font-bold text-slate-800"><?php echo htmlspecialchars($req['full_name']); ?></div>
              <div class="text-xs text-slate-400 font-mono"><?php echo htmlspecialchars($req['username']); ?></div>
            </div>
            <span class="mr-auto inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?php echo $st['class']; ?>">
              <i class="fas <?php echo $st['icon']; ?>"></i> <?php echo $st['label']; ?>
            </span>
          </div>

          <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm mb-4">
            <div class="bg-slate-50 rounded-xl p-3">
              <div class="text-xs text-slate-400">الكلية</div>
              <div class="font-semibold text-slate-700 mt-0.5"><?php echo htmlspecialchars($req['college_name'] ?? '-'); ?></div>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
              <div class="text-xs text-slate-400">نوع الطلب</div>
              <div class="font-semibold text-slate-700 mt-0.5"><?php echo $req['is_reissue'] ? 'إعادة إصدار' : 'أول مرة'; ?></div>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
              <div class="text-xs text-slate-400">الرسوم</div>
              <div class="font-bold text-indigo-700 mt-0.5"><?php echo $req['fee_amount']; ?> جنيه</div>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
              <div class="text-xs text-slate-400">مراجعة شؤون</div>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold <?php echo $af['class']; ?>">
                <i class="fas <?php echo $af['icon']; ?>"></i> <?php echo $af['label']; ?>
              </span>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
              <div class="text-xs text-slate-400">اعتماد العميد</div>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold <?php echo $dn['class']; ?>">
                <i class="fas <?php echo $dn['icon']; ?>"></i> <?php echo $dn['label']; ?>
              </span>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
              <div class="text-xs text-slate-400">تاريخ التقديم</div>
              <div class="font-semibold text-slate-600 mt-0.5 text-xs"><?php echo date('d/m/Y', strtotime($req['created_at'])); ?></div>
            </div>
          </div>

          <!-- الملفات المرفقة -->
          <div class="flex flex-wrap gap-2">
            <?php if ($req['photo_path']): ?>
            <a href="<?php echo htmlspecialchars($req['photo_path']); ?>" target="_blank"
               class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 border border-indigo-200 text-indigo-700 rounded-lg text-xs font-bold hover:bg-indigo-100 transition-colors">
              <i class="fas fa-camera"></i> الصورة الشخصية
            </a>
            <?php endif; ?>
            <?php if ($req['national_id_path']): ?>
            <a href="<?php echo htmlspecialchars($req['national_id_path']); ?>" target="_blank"
               class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 border border-blue-200 text-blue-700 rounded-lg text-xs font-bold hover:bg-blue-100 transition-colors">
              <i class="fas fa-id-card"></i> البطاقة الشخصية
            </a>
            <?php endif; ?>
            <?php if ($req['birth_cert_path']): ?>
            <a href="<?php echo htmlspecialchars($req['birth_cert_path']); ?>" target="_blank"
               class="flex items-center gap-1.5 px-3 py-1.5 bg-green-50 border border-green-200 text-green-700 rounded-lg text-xs font-bold hover:bg-green-100 transition-colors">
              <i class="fas fa-file-alt"></i> شهادة الميلاد
            </a>
            <?php endif; ?>

            <!-- الزر الجديد للمراجعة الشاملة -->
            <a href="graduation_cert_print_review.php?id=<?php echo $req['id']; ?>" target="_blank"
               class="flex items-center gap-1.5 px-3 py-1.5 bg-purple-600 text-white rounded-lg text-xs font-bold hover:bg-purple-700 transition-all shadow-sm ml-auto">
              <i class="fas fa-file-pdf"></i> ملف المراجعة الشامل (PDF)
            </a>
          </div>
        </div>

        <!-- إجراء المراجعة -->
        <?php if ($can_affairs_review || $can_dean_review): ?>
        <div class="border-t md:border-t-0 md:border-r border-slate-100 p-5 md:w-72 bg-slate-50/50">
          <div class="font-bold text-slate-700 mb-3 flex items-center gap-2">
            <i class="fas fa-<?php echo $can_affairs_review?'users-cog':'user-tie'; ?> text-<?php echo $can_affairs_review?'blue':'purple'; ?>-500"></i>
            <?php echo $can_affairs_review ? 'مراجعة شؤون الطلاب' : 'اعتماد العميد'; ?>
          </div>
          <form method="POST" class="space-y-3">
            <input type="hidden" name="req_id" value="<?php echo $req['id']; ?>">
            <div>
              <label class="text-xs font-semibold text-slate-600 mb-1 block">ملاحظة (اختياري)</label>
              <textarea name="note" rows="2"
                class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm bg-white resize-none focus:ring-2 focus:ring-indigo-300"
                placeholder="أكتب ملاحظتك هنا..."></textarea>
            </div>
            
            <?php if ($can_affairs_review): ?>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="text-[10px] font-bold text-slate-500 mb-1 block">رقم قسيمة البراءة</label>
                <input type="text" name="clearance_receipt" 
                  class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-indigo-300" placeholder="000000">
              </div>
              <div>
                <label class="text-[10px] font-bold text-slate-500 mb-1 block">رقم قسيمة الشهادة</label>
                <input type="text" name="certificate_receipt" 
                  class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-indigo-300" placeholder="000000">
              </div>
            </div>
            <?php endif; ?>

            <div class="flex gap-2">
              <button type="submit" name="review_action" value="approved"
                class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 rounded-xl text-sm transition-colors flex items-center justify-center gap-1.5 shadow">
                <i class="fas fa-check"></i> قبول
              </button>
              <button type="submit" name="review_action" value="rejected"
                onclick="return confirm('هل أنت متأكد من رفض الطلب؟')"
                class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded-xl text-sm transition-colors flex items-center justify-center gap-1.5 shadow">
                <i class="fas fa-times"></i> رفض
              </button>
            </div>
          </form>
        </div>
        <?php elseif ($req['status'] === 'approved'): ?>
        <div class="border-t md:border-t-0 md:border-r border-slate-100 p-5 md:w-60 flex flex-col items-center justify-center bg-emerald-50/50">
          <i class="fas fa-check-circle text-emerald-400 text-3xl mb-2"></i>
          <div class="font-bold text-emerald-700 text-sm text-center">تم الاعتماد</div>
          <div class="text-xs text-slate-400 text-center mt-1">الشهادة جاهزة للإصدار</div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
