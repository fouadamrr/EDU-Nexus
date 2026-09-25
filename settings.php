<?php
require_once 'includes/header.php';

require_once __DIR__ . '/models/Setting.php';

if (!in_array($role, ['super_admin', 'admin'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}

$message = '';
$settingModel = new Setting();

// بننفذ عملية الحفظ لما الفورمة تتبعت
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
 $settings_to_save = [
 'system_name' => $_POST['system_name'] ?? 'EDU Nexus',
 'academic_year' => $_POST['academic_year'] ?? '2025/2026',
 'current_semester' => $_POST['current_semester'] ?? 'الخريف',
 'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
 'registration_open'=> isset($_POST['registration_open'])? '1' : '0',
 'results_locked' => isset($_POST['results_locked'])? '1' : '0',
 ];

 $db = Database::getConnection();
 // بنلف على كل إعداد ونحفظه في الداتا بيز
 foreach ($settings_to_save as $key => $value) {
 $stmt = $db->prepare("SELECT id FROM settings WHERE key = ? AND (college_id IS NULL OR college_id = 0)");
 $stmt->execute([$key]);
 $existing = $stmt->fetch();
 
 if ($existing) {
 $settingModel->update($existing['id'], ['value' => $value]);
 } else {
 $settingModel->insert(['key' => $key, 'value' => $value, 'college_id' => null]);
 }
 }
  $message = '<div class="bg-primary text-white p-4 rounded-xl mb-6 shadow-md flex items-center gap-3"><i class="fas fa-check-circle"></i><span>تم حفظ الإعدادات بنجاح</span></div>';
}

// بنعمل نسخة احتياطية (Backup) للداتا بيز لو الأدمن طلب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
 $filename = basename($_POST['backup_name']);
  $cmd = sprintf(
      'PGHOST=127.0.0.1 PGPORT=5432 PGPASSWORD=%s pg_dump -U %s -d %s -F c -f %s 2>&1',
      escapeshellarg(DB_PASS),
      escapeshellarg(DB_USER),
      escapeshellarg(DB_NAME),
      escapeshellarg(__DIR__ . '/' . $filename)
  );
  $output = [];
  $return_code = 0;
  exec($cmd, $output, $return_code);
 $cmd_output = htmlspecialchars(implode("\n", $output));
  if ($return_code === 0) {
  $message = "<div class='bg-primary text-white p-4 rounded-xl mb-6 shadow-md'><div class='flex items-center gap-3 mb-2'><i class='fas fa-check-circle'></i><strong>تم إنشاء النسخة الاحتياطية بنجاح:</strong> " . htmlspecialchars($filename) . "</div><pre class='mt-2 bg-white/10 p-3 rounded-lg text-xs font-mono overflow-auto'>{$cmd_output}</pre></div>";
  } else {
  $message = "<div class='bg-rose-600 text-white p-4 rounded-xl mb-6 shadow-md'><div class='flex items-center gap-3 mb-2'><i class='fas fa-exclamation-circle'></i><strong>فشل النسخ الاحتياطي!</strong></div><pre class='mt-2 bg-white/10 p-3 rounded-lg text-xs font-mono overflow-auto'>{$cmd_output}</pre></div>";
  }
}

// الإعدادات الافتراضية للسيستم
$keys = ['system_name','academic_year','current_semester','maintenance_mode','registration_open','results_locked'];
$config = [
 'system_name' => 'EDU Nexus',
 'academic_year' => '2025/2026',
 'current_semester' => 'الخريف',
 'maintenance_mode' => '0',
 'registration_open' => '0',
 'results_locked' => '0',
];

// بنجيب القيم الحقيقية من الداتا بيز ونحطها مكان الافتراضية
$allSettings = $settingModel->findAll();
foreach ($keys as $key) {
 foreach ($allSettings as $s) {
 if ($s['key'] === $key && (empty($s['college_id']) || $s['college_id'] == 0)) {
 $config[$key] = $s['value'];
 break;
 }
 }
}
?>

<div class="max-w-4xl mx-auto space-y-6 animate-fade-in-up">
  <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 transition-all">
  <div class="flex items-center gap-4 mb-8 border-b border-slate-100 pb-5">
  <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
  <i class="fas fa-cogs text-xl"></i>
  </div>
  <h2 class="text-2xl font-bold text-slate-800">إعدادات النظام العامة</h2>
  </div>

 <?php echo $message; ?>

 <form method="POST" class="space-y-6">
 <input type="hidden" name="action" value="save">

 <!-- تعديل اسم السيستم -->
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">اسم النظام / الجامعة</label>
 <input type="text" name="system_name" value="<?php echo htmlspecialchars($config['system_name']); ?>"
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-blue-500">
 </div>

 <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
 <!-- تعديل السنة الدراسية -->
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">العام الجامعي</label>
 <input type="text" name="academic_year"
 value="<?php echo htmlspecialchars($config['academic_year']); ?>"
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-blue-500">
 </div>
 <!-- اختيار الترم الحالي -->
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">الفصل الدراسي الحالي</label>
 <select name="current_semester"
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-blue-500">
 <option value="الخريف" <?php echo $config['current_semester'] == 'الخريف' ? 'selected' : ''; ?>>
 الخريف</option>
 <option value="الربيع" <?php echo $config['current_semester'] == 'الربيع' ? 'selected' : ''; ?>>
 الربيع</option>
 <option value="الصيف" <?php echo $config['current_semester'] == 'الصيف' ? 'selected' : ''; ?>>
 الصيف</option>
 </select>
 </div>
 </div>

 <!-- تفعيل وضع الصيانة (Maintenance) -->
 <div
 class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-bg transition">
 <div>
 <h3 class="font-bold text-gray-800">وضع الصيانة</h3>
 <p class="text-sm text-gray-500">إغلاق الموقع أمام الزوار والطلاب</p>
 </div>
 <label class="relative inline-flex items-center cursor-pointer">
 <input type="checkbox" name="maintenance_mode" value="1" class="sr-only peer" <?php echo $config['maintenance_mode'] == '1' ? 'checked' : ''; ?>>
 <div
 class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary">
 </div>
 </label>
 </div>

 <!-- فتح أو قفل تسجيل المواد للطلبة -->
 <div
 class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-bg transition">
 <div>
 <h3 class="font-bold text-gray-800">فتح تسجيل المواد</h3>
 <p class="text-sm text-gray-500">السماح للطلاب بتسجيل المواد للفصل الدراسي الحالي</p>
 </div>
 <label class="relative inline-flex items-center cursor-pointer">
 <input type="checkbox" name="registration_open" value="1" class="sr-only peer" <?php echo $config['registration_open'] == '1' ? 'checked' : ''; ?>>
 <div
 class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary">
 </div>
 </label>
 </div>

 <!-- قفل رصد الدرجات (محدش يقدر يغيرها) -->
 <div
 class="flex items-center justify-between p-4 border border-primary bg-bg rounded-lg hover:bg-primary transition">
 <div>
 <h3 class="font-bold text-gray-800">إغلاق رصد الدرجات (Lock Results)</h3>
 <p class="text-sm text-gray-500">عند تفعيل هذا الخيار، لن يتمكن العمداء أو الأساتذة من تعديل درجات الطلاب.</p>
 </div>
 <label class="relative inline-flex items-center cursor-pointer">
 <input type="checkbox" name="results_locked" value="1" class="sr-only peer" <?php echo $config['results_locked'] == '1' ? 'checked' : ''; ?>>
 <div
 class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary">
 </div>
 </label>
 </div>

  <div class="border-t border-slate-100 pt-8">
  <button type="submit"
  class="bg-primary text-white font-bold py-3 px-10 rounded-xl hover:bg-indigo-700 transition-all shadow-md shadow-primary/20 hover:shadow-lg flex items-center gap-2">
  <i class="fas fa-save"></i>
  حفظ الإعدادات
  </button>
  </div>
 </form>
 </div>

  <!-- جزء النسخ الاحتياطي (Backup) -->
  <div class="bg-white rounded-2xl shadow-sm p-8 border border-slate-100">
  <h3 class="text-xl font-bold mb-6 border-b border-slate-100 pb-4 flex items-center gap-3">
  <i class="fas fa-database text-primary bg-primary/10 p-2.5 rounded-xl"></i> 
  النسخ الاحتياطي
  </h3>
 <p class="text-gray-500 mb-4 text-sm">قم بإنشاء نسخة احتياطية من قاعدة البيانات. سيتم حفظ الملف في المجلد
 الرئيسي.</p>

 <form method="POST" class="flex gap-4 items-end">
 <input type="hidden" name="action" value="backup">
 <div class="flex-1">
 <label class="block text-gray-700 font-bold mb-2 text-sm">اسم ملف النسخة الاحتياطية</label>
 <div class="relative">
 <input type="text" name="backup_name" value="backup_<?php echo date('Ymd'); ?>.zip"
 class="w-full border rounded-lg px-4 py-2 font-mono text-sm text-gray-600" dir="ltr">
 <i class="fas fa-file-archive absolute right-3 top-3 text-gray-400"></i>
 </div>
 </div>
  <button type="submit"
  class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-indigo-700 hover:scale-105 active:scale-95 transition-all flex items-center gap-2 shadow-md shadow-indigo-200">
  <i class="fas fa-download"></i> إنشاء نسخة
  </button>
 </form>
  <div class="mt-6 bg-slate-50 p-4 rounded-xl text-xs text-slate-500 border border-slate-200 flex items-start gap-3">
  <i class="fas fa-info-circle text-primary mt-0.5"></i>
  <div>
  <strong>ملاحظة للمدير:</strong> يتم استخدام أمر النظام لضغط الملفات. تأكد من توفر المساحة الكافية على القرص قبل البدء.
  </div>
  </div>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>