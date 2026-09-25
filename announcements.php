<?php
require_once 'includes/header.php';

require_once __DIR__ . '/controllers/AnnouncementController.php';
$announcementController = new AnnouncementController();

if (!in_array($role, ['admin', 'dean', 'affairs'])) {
 header("Location: dashboard.php");
 exit;
}
require_permission('supervision');


require_once __DIR__ . '/models/College.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $res = $announcementController->handlePost($_POST);
 if ($res) $message = $res;
}

if (isset($_GET['delete'])) {
 $res = $announcementController->handleDelete((int)$_GET['delete']);
 if ($res) $message = $res;
}

$collegeModel = new College();
$colleges = $collegeModel->findAll();
$all_announcements = $announcementController->getAllAnnouncements();
?>

<div class="max-w-4xl mx-auto space-y-6 animate-fade-in-up">
 <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200">
 <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">نشر الإعلانات والأخبار</h2>

 <?php echo $message; ?>

 <form method="POST" class="space-y-6">
 <div>
 <label class="block font-bold text-gray-700 mb-2">عنوان الإعلان</label>
 <input type="text" name="title" required
 class="w-full border border-gray-300 rounded p-3 focus:ring-2 focus:ring-primary"
 placeholder="مثال: فتح باب التسجيل للفصل الصيفي">
 </div>

 <div>
 <label class="block font-bold text-gray-700 mb-2">نص الإعلان</label>
 <textarea name="content" required
 class="w-full border border-gray-300 rounded p-3 h-32 focus:ring-2 focus:ring-primary"></textarea>
 </div>

 <?php if ($role !== 'dean'): ?>
 <div class="space-y-4">
 <label class="block font-bold text-gray-700">الجمهور المستهدف:</label>
 <div class="flex flex-wrap items-center gap-6 bg-bg p-4 rounded-lg border border-slate-100">
 <label class="flex items-center gap-2 cursor-pointer group">
 <input type="checkbox" name="roles[]" value="student" class="w-5 h-5 text-primary rounded focus:ring-primary" checked>
 <span class="group-hover:text-primary transition-colors">الطلاب</span>
 </label>
 <label class="flex items-center gap-2 cursor-pointer group">
 <input type="checkbox" name="roles[]" value="instructor" class="w-5 h-5 text-primary rounded focus:ring-primary" checked>
 <span class="group-hover:text-primary transition-colors">أعضاء هيئة التدريس</span>
 </label>
 <label class="flex items-center gap-2 cursor-pointer group">
 <input type="checkbox" name="roles[]" value="affairs" class="w-5 h-5 text-primary rounded focus:ring-primary">
 <span class="group-hover:text-primary transition-colors">الموظفين</span>
 </label>
 <label class="flex items-center gap-2 cursor-pointer group">
 <input type="checkbox" name="roles[]" value="dean" class="w-5 h-5 text-primary rounded focus:ring-primary">
 <span class="group-hover:text-primary transition-colors">العمداء</span>
 </label>
 </div>
 </div>

 <div>
 <label class="block font-bold text-gray-700 mb-2">النطاق الجغرافي (الكلية):</label>
 <select name="college_id" class="w-full border border-gray-300 rounded p-3 focus:ring-2 focus:ring-primary appearance-none bg-white">
 <option value="">كل الجامعة (إعلان عام)</option>
 <?php foreach ($colleges as $c): ?>
 <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
 <?php endforeach; ?>
 </select>
 <p class="text-xs text-gray-400 mt-1">* اتركه "كل الجامعة" ليظهر للجميع بحسب تخصصاتهم.</p>
 </div>
 <?php else: ?>
 <div class="bg-bg border-r-4 border-primary p-4 mb-4">
 <p class="text-primary text-sm">
 <i class="fas fa-info-circle ml-2"></i> بصفتك <strong>عميد الكلية</strong>، سيتم نشر هذا الإعلان تلقائياً لطلاب ومدرسي كليتك فقط.
 </p>
 </div>
 <?php endif; ?>

 <button type="submit"
 class="bg-primary text-white font-bold py-3 px-6 rounded-lg hover:bg-primary transition shadow w-full md:w-auto">
 <i class="fas fa-paper-plane ml-2"></i> نشر الإعلان
 </button>
 </form>
 </div>

 <!-- Existing Announcements List -->
 <?php if (!empty($all_announcements)): ?>
 <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 mt-8">
 <h2 class="text-xl font-bold text-gray-800 mb-6 border-b pb-4">الإعلانات المنشورة</h2>
 <div class="space-y-4">
 <?php foreach ($all_announcements as $ann): ?>
 <div class="border border-gray-100 rounded-lg p-5 hover:shadow-md hover:border-primary transition-all group">
 <div class="flex justify-between items-start mb-2">
 <h3 class="font-bold text-lg text-primary"><?php echo htmlspecialchars($ann['title'] ?? 'بدون عنوان'); ?></h3>
 <div class="flex items-center gap-3">
 <span class="text-xs font-medium text-slate-400 bg-bg px-2.5 py-1 rounded-full"><i class="far fa-clock ml-1"></i><?php echo htmlspecialchars($ann['date'] ?? ''); ?></span>
 <a href="?delete=<?php echo $ann['id']; ?>" 
 onclick="return confirm('هل أنت متأكد من حذف هذا الإعلان بشكل نهائي؟');" 
 class="w-8 h-8 flex items-center justify-center text-white hover:text-white bg-bg hover:bg-secondary rounded-lg transition-colors" title="حذف الإعلان">
 <i class="fas fa-trash-alt"></i>
 </a>
 </div>
 </div>
 <p class="text-gray-600 text-sm whitespace-pre-wrap mt-3"><?php echo htmlspecialchars($ann['content'] ?? ''); ?></p>
 </div>
 <?php endforeach; ?>
 </div>
 </div>
 <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>