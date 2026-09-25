<?php
require_once 'includes/header.php';

require_once __DIR__ . '/controllers/ManageSurveysController.php';

if (!in_array($role, ['admin', 'dean'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}

$manageSurveysController = new ManageSurveysController();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
 $res = $manageSurveysController->handleAddRequest($_POST);
 if ($res) $message = $res;
}

if (isset($_GET['delete'])) {
 $res = $manageSurveysController->handleDeleteRequest((int)$_GET['delete']);
 if ($res) $message = $res;
}

$surveys = $manageSurveysController->getAllSurveys();
?>

<div class="max-w-6xl mx-auto space-y-8 animate-fade-in-up">

 <div class="flex items-center justify-between">
 <h2 class="text-2xl font-bold text-gray-800">إدارة الاستبيانات</h2>
 <button onclick="document.getElementById('surveyModal').classList.toggle('hidden')"
 class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary transition shadow text-shadow-sm font-bold">
 <i class="fas fa-plus ml-2"></i> إضافة استبيان جديد
 </button>
 </div>

 <?php echo $message; ?>

 <!-- Surveys List -->
 <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
 <table class="w-full text-right">
 <thead class="bg-bg border-b border-gray-200 text-gray-500 font-bold text-sm">
 <tr>
 <th class="p-4">#</th>
 <th class="p-4">عنوان الاستبيان</th>
 <th class="p-4">الرابط</th>
 <th class="p-4">تاريخ الإضافة</th>
 <th class="p-4 text-center">إجراءات</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-100">
 <?php foreach ($surveys as $s): ?>
 <tr class="hover:bg-bg transition">
 <td class="p-4 text-gray-400">
 <?php echo $s['id']; ?>
 </td>
 <td class="p-4 font-bold text-gray-800">
 <?php echo htmlspecialchars($s['title']); ?>
 <div class="text-xs text-gray-400 font-normal mt-1">
 <?php echo htmlspecialchars($s['description']); ?>
 </div>
 </td>
 <td class="p-4">
 <a href="<?php echo htmlspecialchars($s['link']); ?>" target="_blank"
 class="text-primary hover:underline text-sm">
 <i class="fas fa-external-link-alt"></i> فتح الرابط
 </a>
 </td>
 <td class="p-4 text-gray-500">
 <?php echo $s['created_at']; ?>
 </td>
 <td class="p-4 text-center">
 <a href="?delete=<?php echo $s['id']; ?>"
 class="text-xs bg-white border border-primary text-primary hover:bg-bg px-3 py-1 rounded transition"
 onclick="return confirm('حذف الاستبيان؟')">
 <i class="fas fa-trash"></i> حذف
 </a>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
</div>

<!-- Add Modal -->
<div id="surveyModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
 <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 animate-scale-in">
 <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
 <h3 class="text-xl font-bold">إضافة استبيان جديد</h3>
 <button onclick="document.getElementById('surveyModal').classList.toggle('hidden')"
 class="text-gray-400 hover:text-primary transition">
 <i class="fas fa-times text-xl"></i>
 </button>
 </div>

 <form method="POST" class="space-y-4">
 <input type="hidden" name="action" value="add">

 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">عنوان الاستبيان</label>
 <input type="text" name="title" required
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-blue-500">
 </div>

 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">وصف قصير</label>
 <input type="text" name="description" required
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-blue-500">
 </div>

 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">رابط الاستبيان (Google Forms / Microsoft
 Forms)</label>
 <input type="url" name="link" required placeholder="https://..."
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-blue-500">
 </div>

 <div class="pt-4 flex gap-3">
 <button type="button" onclick="document.getElementById('surveyModal').classList.toggle('hidden')"
 class="flex-1 bg-bg text-gray-700 py-2 rounded font-bold hover:bg-gray-200 transition">إلغاء</button>
 <button type="submit"
 class="flex-1 bg-primary text-white py-2 rounded font-bold hover:bg-primary transition shadow">حفظ</button>
 </div>
 </form>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>