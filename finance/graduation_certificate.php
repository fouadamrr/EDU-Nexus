<?php
require_once 'includes/header.php';
/** @var PDO $pdo */
/** @var string $role */
/** @var UniversityDB $db */

// الصفحة دي للطلاب بس
if ($role !== 'student') {
    echo "<script>window.location.href='official_documents.php';</script>";
    exit;
}

$student = $db->find('users', 'id', $user_id);
$student_details = $db->find('student_details', 'user_id', $user_id);
$college_name = '';
if ($student && isset($student['college_id'])) {
    $col = $db->find('colleges', 'id', $student['college_id']);
    if ($col) $college_name = $col['name'];
}

// إنشاء جدول الطلبات لو مش موجود
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
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP DEFAULT NOW()
    )");
} catch (Exception $e) {}

// جيب آخر طلب للطالب
$last_request = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM graduation_certificate_requests WHERE user_id = :uid ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([':uid' => $user_id]);
    $last_request = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$success_msg = '';
$error_msg = '';

// معالجة تقديم الطلب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $upload_dir = __DIR__ . '/uploads/graduation_docs/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $allowed_types = ['image/jpeg','image/png','image/jpg','image/heic','image/heif','application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $allowed_exts  = ['jpg', 'jpeg', 'png', 'heic', 'heif', 'pdf', 'doc', 'docx'];
    $max_size = 6 * 1024 * 1024;

    $files_ok = true;
    $paths = [];

    foreach (['photo' => 'photo_path', 'national_id' => 'national_id_path', 'birth_cert' => 'birth_cert_path'] as $field => $col) {
        if (empty($_FILES[$field]['name'])) {
            $error_msg = 'يجب رفع جميع الملفات المطلوبة';
            $files_ok = false;
            break;
        }

        $file_ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        $file_type = $_FILES[$field]['type'];

        if ($_FILES[$field]['size'] > $max_size) {
            $error_msg = 'حجم الملف يجب أن لا يتجاوز 6 ميجابايت';
            $files_ok = false;
            break;
        }

        // التحقق من الامتداد أو نوع الملف لضمان أقصى توافق
        if (!in_array($file_type, $allowed_types) && !in_array($file_ext, $allowed_exts)) {
            $error_msg = 'نوع الملف غير مسموح به (' . $file_ext . ')، يرجى رفع صورة (JPG, PNG) أو ملف (PDF, DOCX)';
            $files_ok = false;
            break;
        }
        $fname = $field . '_' . $user_id . '_' . time() . '.' . $file_ext;
        $dest = $upload_dir . $fname;
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
            $err_code = $_FILES[$field]['error'];
            $field_names = ['photo' => 'الصورة الشخصية', 'national_id' => 'صورة البطاقة', 'birth_cert' => 'شهادة الميلاد'];
            $error_msg = 'فشل في رفع (' . $field_names[$field] . ') - كود الخطأ: ' . $err_code . '. تأكد من حجم الملف وحاول مرة أخرى';
            $files_ok = false;
            break;
        }
        $paths[$col] = 'uploads/graduation_docs/' . $fname;
    }

    if ($files_ok) {
        $is_reissue = ($last_request && $last_request['status'] === 'approved') ? true : false;
        $fee = $is_reissue ? 300 : 800;
        try {
            $stmt = $pdo->prepare("INSERT INTO graduation_certificate_requests 
                (user_id, college_id, status, is_reissue, photo_path, national_id_path, birth_cert_path, fee_amount, affairs_review, dean_review)
                VALUES (:uid, :cid, 'pending', :reissue, :photo, :nid, :birth, :fee, 'pending', 'pending')");
            $stmt->execute([
                ':uid' => $user_id,
                ':cid' => $student['college_id'] ?? null,
                ':reissue' => $is_reissue ? 'true' : 'false',
                ':photo' => $paths['photo_path'],
                ':nid' => $paths['national_id_path'],
                ':birth' => $paths['birth_cert_path'],
                ':fee' => $fee,
            ]);
            $success_msg = 'تم تقديم طلبك بنجاح! سيتم مراجعته من قبل شؤون الطلاب ثم العميد.';
            $stmt2 = $pdo->prepare("SELECT * FROM graduation_certificate_requests WHERE user_id = :uid ORDER BY created_at DESC LIMIT 1");
            $stmt2->execute([':uid' => $user_id]);
            $last_request = $stmt2->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error_msg = 'حدث خطأ أثناء تسجيل الطلب: ' . $e->getMessage();
        }
    }
}

$status_labels = [
    'pending'  => ['label' => 'قيد المراجعة',  'class' => 'status-pending',  'icon' => 'fa-clock'],
    'approved' => ['label' => 'مقبول',          'class' => 'status-passed',   'icon' => 'fa-check-circle'],
    'rejected' => ['label' => 'مرفوض',          'class' => 'status-failed',   'icon' => 'fa-times-circle'],
];
$can_apply = !$last_request || in_array($last_request['status'], ['rejected','approved']);
$fee_amount = ($last_request && $last_request['status'] === 'approved') ? 300 : 800;
?>

<div class="max-w-5xl mx-auto space-y-6">

  <!-- Page Header -->
  <div class="flex items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
    <div class="w-14 h-14 rounded-2xl bg-indigo-600 flex items-center justify-center shadow-lg">
      <i class="fas fa-graduation-cap text-white text-2xl"></i>
    </div>
    <div>
      <h2 class="text-2xl font-bold text-slate-800">طلب شهادة التخرج</h2>
      <p class="text-sm text-slate-500 mt-0.5">تقدم بطلبك واتابع حالته خطوة بخطوة</p>
    </div>
    <a href="official_documents.php" class="mr-auto flex items-center gap-2 text-sm text-slate-500 hover:text-indigo-600 transition-colors">
      <i class="fas fa-arrow-right"></i> الوثائق الرسمية
    </a>
  </div>

  <?php if ($success_msg): ?>
  <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl p-4 flex items-center gap-3">
    <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
    <span class="font-semibold"><?php echo $success_msg; ?></span>
  </div>
  <?php endif; ?>

  <?php if ($error_msg): ?>
  <div class="bg-red-50 border border-red-200 text-red-800 rounded-2xl p-4 flex items-center gap-3">
    <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
    <span class="font-semibold"><?php echo $error_msg; ?></span>
  </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- معلومات الطلب والتعليمات -->
    <div class="space-y-5">

      <!-- الرسوم -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <h3 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
          <i class="fas fa-money-bill-wave text-amber-500"></i> رسوم الشهادة
        </h3>
        <div class="space-y-3">
          <div class="flex items-center justify-between p-3 rounded-xl bg-indigo-50 border border-indigo-100">
            <span class="text-sm font-semibold text-slate-700">أول مرة</span>
            <span class="font-black text-indigo-700 text-lg">800 جنيه</span>
          </div>
          <div class="flex items-center justify-between p-3 rounded-xl bg-amber-50 border border-amber-100">
            <span class="text-sm font-semibold text-slate-700">إعادة إصدار</span>
            <span class="font-black text-amber-700 text-lg">300 جنيه</span>
          </div>
        </div>
        <p class="text-xs text-slate-400 mt-3"><i class="fas fa-info-circle mr-1"></i>يتم سداد الرسوم عند استلام الشهادة</p>
      </div>

      <!-- الأوراق المطلوبة -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <h3 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
          <i class="fas fa-paperclip text-indigo-500"></i> الأوراق المطلوبة
        </h3>
        <ul class="space-y-3">
          <li class="flex items-start gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
            <i class="fas fa-camera text-indigo-500 mt-0.5"></i>
            <div>
              <div class="font-bold text-sm text-slate-700">صورة شخصية</div>
              <div class="text-xs text-slate-400">مقاس 4×6 سم، خلفية بيضاء</div>
            </div>
          </li>
          <li class="flex items-start gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
            <i class="fas fa-id-card text-blue-500 mt-0.5"></i>
            <div>
              <div class="font-bold text-sm text-slate-700">صورة البطاقة الشخصية</div>
              <div class="text-xs text-slate-400">الرقم القومي – صورة واضحة من الجهتين</div>
            </div>
          </li>
          <li class="flex items-start gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
            <i class="fas fa-file-alt text-green-500 mt-0.5"></i>
            <div>
              <div class="font-bold text-sm text-slate-700">صورة شهادة الميلاد</div>
              <div class="text-xs text-slate-400">نسخة واضحة ومقروءة</div>
            </div>
          </li>
        </ul>
      </div>

      <!-- مسار الموافقة -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <h3 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
          <i class="fas fa-route text-purple-500"></i> مسار الموافقة
        </h3>
        <div class="space-y-0">
          <?php
          $steps = [
            ['icon'=>'fa-user-graduate','title'=>'تقديم الطالب','desc'=>'رفع الأوراق المطلوبة','color'=>'indigo'],
            ['icon'=>'fa-users-cog','title'=>'مراجعة شؤون الطلاب','desc'=>'فحص الأوراق والبيانات','color'=>'blue'],
            ['icon'=>'fa-user-tie','title'=>'اعتماد العميد','desc'=>'الموافقة النهائية','color'=>'purple'],
            ['icon'=>'fa-certificate','title'=>'إصدار الشهادة','desc'=>'جاهزة للاستلام','color'=>'green'],
          ];
          foreach ($steps as $i => $step):
          ?>
          <div class="flex gap-3">
            <div class="flex flex-col items-center">
              <div class="w-9 h-9 rounded-full bg-<?php echo $step['color']; ?>-100 flex items-center justify-center flex-shrink-0">
                <i class="fas <?php echo $step['icon']; ?> text-<?php echo $step['color']; ?>-600 text-sm"></i>
              </div>
              <?php if ($i < count($steps)-1): ?>
              <div class="w-0.5 h-6 bg-slate-200 my-1"></div>
              <?php endif; ?>
            </div>
            <div class="pb-2">
              <div class="font-bold text-sm text-slate-700"><?php echo $step['title']; ?></div>
              <div class="text-xs text-slate-400"><?php echo $step['desc']; ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- النموذج أو حالة الطلب -->
    <div class="lg:col-span-2 space-y-5">

      <!-- حالة الطلب الحالي -->
      <?php if ($last_request): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <h3 class="font-bold text-slate-700 mb-5 flex items-center gap-2 text-lg">
          <i class="fas fa-tasks text-indigo-500"></i> حالة طلبك الأخير
        </h3>

        <!-- شريط التقدم -->
        <div class="relative mb-8">
          <?php
          $affairs_s = $last_request['affairs_review'] ?? 'pending';
          $dean_s    = $last_request['dean_review'] ?? 'pending';
          $overall   = $last_request['status'] ?? 'pending';
          $step1 = true;
          $step2 = in_array($affairs_s, ['approved','rejected']);
          $step3 = ($affairs_s === 'approved' && in_array($dean_s, ['approved','rejected']));
          $step4 = ($overall === 'approved');
          ?>
          <div class="flex items-center justify-between relative">
            <div class="absolute top-4 right-4 left-4 h-1 bg-slate-200 z-0">
              <div class="h-full bg-indigo-500 transition-all duration-700"
                   style="width: <?php echo $step4 ? '100%' : ($step3 ? '66%' : ($step2 ? '33%' : '0%')); ?>"></div>
            </div>
            <?php
            $prog_steps = [
              ['label'=>'تم التقديم','done'=>$step1,'icon'=>'fa-paper-plane'],
              ['label'=>'شؤون الطلاب','done'=>$step2,'icon'=>'fa-users-cog'],
              ['label'=>'العميد','done'=>$step3,'icon'=>'fa-user-tie'],
              ['label'=>'مكتمل','done'=>$step4,'icon'=>'fa-check-circle'],
            ];
            foreach ($prog_steps as $ps):
            ?>
            <div class="flex flex-col items-center z-10 w-1/4">
              <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm border-2
                <?php echo $ps['done'] ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-slate-300 text-slate-400'; ?>">
                <i class="fas <?php echo $ps['icon']; ?> text-xs"></i>
              </div>
              <span class="text-xs font-semibold mt-2 text-center <?php echo $ps['done'] ? 'text-indigo-700' : 'text-slate-400'; ?>"><?php echo $ps['label']; ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- تفاصيل الحالة -->
        <div class="grid grid-cols-2 gap-3 mb-4">
          <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
            <div class="text-xs text-slate-400 mb-1">حالة الطلب</div>
            <?php $st = $status_labels[$overall] ?? $status_labels['pending']; ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?php echo $st['class']; ?>">
              <i class="fas <?php echo $st['icon']; ?>"></i> <?php echo $st['label']; ?>
            </span>
          </div>
          <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
            <div class="text-xs text-slate-400 mb-1">نوع الطلب</div>
            <span class="font-bold text-sm text-slate-700">
              <?php echo $last_request['is_reissue'] ? '🔄 إعادة إصدار (300 ج)' : '🎓 إصدار أول (800 ج)'; ?>
            </span>
          </div>
          <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
            <div class="text-xs text-slate-400 mb-1">مراجعة شؤون الطلاب</div>
            <?php $as = $status_labels[$affairs_s] ?? $status_labels['pending']; ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?php echo $as['class']; ?>">
              <i class="fas <?php echo $as['icon']; ?>"></i> <?php echo $as['label']; ?>
            </span>
          </div>
          <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
            <div class="text-xs text-slate-400 mb-1">اعتماد العميد</div>
            <?php $ds = $status_labels[$dean_s] ?? $status_labels['pending']; ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?php echo $ds['class']; ?>">
              <i class="fas <?php echo $ds['icon']; ?>"></i> <?php echo $ds['label']; ?>
            </span>
          </div>
        </div>

        <?php if (!empty($last_request['affairs_note']) || !empty($last_request['dean_note'])): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm">
          <div class="font-bold text-amber-800 mb-2"><i class="fas fa-comment-alt mr-1"></i> ملاحظات المراجعة</div>
          <?php if (!empty($last_request['affairs_note'])): ?>
          <p class="text-amber-700"><strong>شؤون الطلاب:</strong> <?php echo htmlspecialchars($last_request['affairs_note']); ?></p>
          <?php endif; ?>
          <?php if (!empty($last_request['dean_note'])): ?>
          <p class="text-amber-700 mt-1"><strong>العميد:</strong> <?php echo htmlspecialchars($last_request['dean_note']); ?></p>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="text-xs text-slate-400 mt-3 flex items-center justify-between">
          <div class="flex items-center gap-1">
            <i class="fas fa-calendar-alt"></i>
            تاريخ التقديم: <?php echo date('d/m/Y H:i', strtotime($last_request['created_at'])); ?>
          </div>
          
          <?php if ($overall === 'approved'): ?>
          <a href="print_graduation_cert.php?id=<?php echo $last_request['id']; ?>" target="_blank"
             class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 text-white rounded-xl font-black text-sm shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all">
            <i class="fas fa-print"></i> استخراج الشهادة
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- نموذج تقديم الطلب -->
      <?php if ($can_apply): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <h3 class="font-bold text-slate-700 mb-2 flex items-center gap-2 text-lg">
          <i class="fas fa-file-upload text-indigo-500"></i>
          <?php echo ($last_request && $last_request['status'] === 'approved') ? 'طلب إعادة إصدار الشهادة' : 'تقديم طلب شهادة التخرج'; ?>
        </h3>
        <p class="text-sm text-slate-400 mb-6">
          الرسوم المستحقة:
          <strong class="text-indigo-700 text-base"><?php echo $fee_amount; ?> جنيه</strong>
          <?php echo ($last_request && $last_request['status'] === 'approved') ? '(إعادة إصدار)' : '(أول مرة)'; ?>
        </p>

        <form method="POST" enctype="multipart/form-data" class="space-y-5">
          <!-- صورة شخصية -->
          <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">
              <i class="fas fa-camera text-indigo-500 mr-1"></i> صورة شخصية <span class="text-red-500">*</span>
              <span class="font-normal text-slate-400">(مقاس 4×6)</span>
            </label>
            <input type="file" name="photo" id="photo" accept="image/*,.heic,.heif,.pdf,.doc,.docx" required
              class="w-full border-2 border-dashed border-indigo-200 rounded-xl px-4 py-3 text-sm text-slate-600 bg-indigo-50 hover:border-indigo-400 cursor-pointer transition-colors file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
          </div>

          <!-- البطاقة الشخصية -->
          <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">
              <i class="fas fa-id-card text-blue-500 mr-1"></i> صورة البطاقة الشخصية (الرقم القومي) <span class="text-red-500">*</span>
            </label>
            <input type="file" name="national_id" id="national_id" accept="image/*,.heic,.heif,.pdf,.doc,.docx" required
              class="w-full border-2 border-dashed border-blue-200 rounded-xl px-4 py-3 text-sm text-slate-600 bg-blue-50 hover:border-blue-400 cursor-pointer transition-colors file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700">
          </div>

          <!-- شهادة الميلاد -->
          <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">
              <i class="fas fa-file-alt text-green-500 mr-1"></i> صورة شهادة الميلاد <span class="text-red-500">*</span>
            </label>
            <input type="file" name="birth_cert" id="birth_cert" accept="image/*,.heic,.heif,.pdf,.doc,.docx" required
              class="w-full border-2 border-dashed border-green-200 rounded-xl px-4 py-3 text-sm text-slate-600 bg-green-50 hover:border-green-400 cursor-pointer transition-colors file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-green-600 file:text-white hover:file:bg-green-700">
          </div>

          <!-- إقرار -->
          <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
            <label class="flex items-start gap-3 cursor-pointer">
              <input type="checkbox" name="confirm" required class="mt-1 w-4 h-4 accent-indigo-600">
              <span class="text-sm text-slate-600">
                أقر بأن جميع البيانات والمستندات المرفقة صحيحة وأنا مسؤول عن صحتها، وأن سداد الرسوم 
                (<strong><?php echo $fee_amount; ?> جنيه</strong>) يتم عند الاستلام.
              </span>
            </label>
          </div>

          <button type="submit" name="submit_request"
            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl flex items-center justify-center gap-2 text-base">
            <i class="fas fa-paper-plane"></i> تقديم الطلب
          </button>
        </form>
      </div>
      <?php elseif ($last_request && $last_request['status'] === 'pending'): ?>
      <div class="bg-amber-50 border border-amber-200 rounded-2xl p-8 text-center">
        <i class="fas fa-hourglass-half text-amber-400 text-4xl mb-4"></i>
        <h3 class="font-bold text-amber-800 text-lg mb-2">طلبك قيد المراجعة</h3>
        <p class="text-amber-700 text-sm">يتم حاليًا مراجعة طلبك من قِبل شؤون الطلاب والعميد. يرجى الانتظار.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
