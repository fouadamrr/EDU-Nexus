<?php
require_once 'includes/header.php';

require_once __DIR__ . '/controllers/ManageBooksController.php';

require_once __DIR__ . '/models/Department.php';

// الصلاحيات: أدمن أو عميد بس اللي يقدروا يدخلو الصفحة دي
if (!in_array($role, ['admin', 'dean'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('library');

$manageBooksController = new ManageBooksController();
$deptModel = new Department();
$departments = $deptModel->findByCollege($_SESSION['college_id'] ?? 0);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
 $res = $manageBooksController->handleAddRequest($_POST, $_FILES);
 if ($res) $message = $res;
}

if (isset($_GET['delete'])) {
 $res = $manageBooksController->handleDeleteRequest((int)$_GET['delete']);
 if ($res) $message = $res;
}

$books = $manageBooksController->getAllBooks();
?>

<div class="max-w-6xl mx-auto space-y-8 animate-fade-in-up">

 <div class="flex items-center justify-between">
 <h2 class="text-2xl font-bold text-gray-800">إدارة المكتبة الجامعية</h2>
 <button onclick="document.getElementById('bookModal').classList.toggle('hidden')"
 class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary transition shadow text-shadow-sm font-bold">
 <i class="fas fa-plus ml-2"></i> إضافة كتاب جديد
 </button>
 </div>

 <?php echo $message; ?>

 <!-- لستة الكتب المعروضة -->
 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
 <?php foreach ($books as $b): ?>
 <div
 class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition group">
 <div class="h-48 bg-bg flex items-center justify-center relative">
 <i class="fas fa-book-open text-6xl text-gray-300 group-hover:text-primary transition"></i>
 <div class="absolute top-2 left-2">
 <span class="bg-primary text-white text-xs px-2 py-1 rounded font-bold">
 <?php echo htmlspecialchars($b['category'] ?? 'عام'); ?>
 </span>
 </div>
 </div>
 <div class="p-4">
 <h3 class="font-bold text-gray-800 mb-1 truncate">
 <?php echo htmlspecialchars($b['title']); ?>
 </h3>
 <p class="text-sm text-gray-500 mb-3">
 <?php echo htmlspecialchars($b['author']); ?>
 </p>

 <div class="flex justify-between items-center text-sm mb-4">
 <span class="text-primary font-bold">متاح:
 <?php echo $b['available_copies'] ?? 0; ?>
 </span>
 <?php if (!empty($b['file_path'])): ?>
 <a href="<?php echo htmlspecialchars($b['file_path']); ?>" target="_blank" class="text-primary hover:text-primary font-bold flex items-center gap-1">
 <i class="fas fa-file-download"></i>
 تحميل
 </a>
 <?php else: ?>
 <span class="text-gray-400">كلي:
 <?php echo $b['total_copies'] ?? 0; ?>
 </span>
 <?php endif; ?>
 </div>

 <div class="flex gap-2">
 <button
 class="flex-1 bg-bg text-gray-600 py-2 rounded border border-gray-200 hover:bg-bg transition text-sm">
 تعديل
 </button>
 <a href="?delete=<?php echo $b['id']; ?>"
 class="flex-1 bg-bg text-primary py-2 rounded border border-primary hover:bg-primary transition text-sm text-center"
 onclick="return confirm('حذف الكتاب؟')">
 حذف
 </a>
 </div>
 </div>
 </div>
 <?php endforeach; ?>
 </div>
</div>

<!-- مودال إضافة كتاب (بيظهر لما تدوس على الزرار) -->
<div id="bookModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
 <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 animate-scale-in">
 <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
 <h3 class="text-xl font-bold">إضافة كتاب جديد</h3>
 <button onclick="document.getElementById('bookModal').classList.toggle('hidden')"
 class="text-gray-400 hover:text-primary transition">
 <i class="fas fa-times text-xl"></i>
 </button>
 </div>

 <form method="POST" enctype="multipart/form-data" class="space-y-4">
 <input type="hidden" name="action" value="add">

 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">عنوان الكتاب / المرجع</label>
 <input type="text" name="title" required
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-purple-500">
 </div>

 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">المؤلف / الدكتور</label>
 <input type="text" name="author" required
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-purple-500">
 </div>

 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">التصنيف (القسم العلمي)</label>
 <select name="category" required
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-purple-500">
 <?php if (empty($departments)): ?>
 <option value="General">عام</option>
 <?php else: ?>
 <?php foreach ($departments as $dept): ?>
 <option value="<?php echo htmlspecialchars($dept['name']); ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
 <?php endforeach; ?>
 <?php endif; ?>
 </select>
 </div>

 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">رفع الملف (PDF, Word, PPT)</label>
 <div class="relative border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-primary transition">
 <input type="file" name="book_file" accept=".pdf,.doc,.docx,.ppt,.pptx"
 class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
 <div class="text-gray-500">
 <i class="fas fa-cloud-upload-alt text-2xl mb-2"></i>
 <p class="text-xs">اضغط لرفع الملف (بحد أقصى 70MB)</p>
 </div>
 </div>
 </div>

 <div class="grid grid-cols-2 gap-4">
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">عدد النسخ</label>
 <input type="number" name="total" value="1" min="1"
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-purple-500">
 </div>
 <div class="flex items-end">
 <p class="text-[10px] text-gray-400 mb-2 italic">* اتركه 1 للنسخ الرقمية</p>
 </div>
 </div>

 <div class="pt-4 flex gap-3">
 <button type="button" onclick="document.getElementById('bookModal').classList.toggle('hidden')"
 class="flex-1 bg-bg text-gray-700 py-2 rounded font-bold hover:bg-gray-200 transition">إلغاء</button>
 <button type="submit"
 class="flex-1 bg-primary text-white py-2 rounded font-bold hover:bg-primary transition shadow">حفظ المرجع</button>
 </div>
 </form>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>