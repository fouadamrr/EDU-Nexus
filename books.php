<?php
require_once 'includes/header.php';

require_once __DIR__ . '/controllers/BooksController.php';

if (!isset($_SESSION['user_id'])) {
 header("Location: index.php");
 exit;
}

$student_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';

$booksController = new BooksController();
$books = $booksController->getAvailableBooks($student_id, $username);
?>

<div class="max-w-7xl mx-auto space-y-8 animate-fade-in-up pb-12">
 
 <!-- Header -->
 <div class="flex flex-col md:flex-row justify-between items-end gap-4 border-b border-gray-200 pb-6">
 <div>
 <h1 class="text-3xl font-bold text-gray-800 tracking-tight">الكتب والمراجع الدراسية</h1>
 <p class="text-gray-500 mt-2 text-lg">استعرض وحمل المواد العلمية الخاصة بمقرراتك.</p>
 </div>
 
 <!-- Search Bar (Visual Only) -->
 <div class="w-full md:w-1/3 relative">
 <i class="fas fa-search absolute right-4 top-3.5 text-gray-400"></i>
 <input type="text" placeholder="بحث في المكتبة..." class="w-full bg-white border border-gray-300 rounded-full py-3 pr-11 pl-4 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-primary transition-all shadow-sm">
 </div>
 </div>

 <!-- Books Grid -->
 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
 <?php if (empty($books)): ?>
 <div class="col-span-full py-16 text-center">
 <div class="w-24 h-24 bg-bg rounded-full flex items-center justify-center mx-auto mb-6 text-gray-400">
 <i class="fas fa-book-open text-4xl"></i>
 </div>
 <h3 class="text-xl font-bold text-gray-700">لا توجد كتب متاحة</h3>
 <p class="text-gray-500 mt-2">لم يقم الدكتور برفع أي مواد علمية بعد.</p>
 </div>
 <?php else: ?>
 <?php foreach ($books as $b): 
 // Robust Fallbacks for all Variables
 $bTitle = $b['book_title'] ?? 'بدون عنوان';
 $bFile = $b['file_name'] ?? '';
 $bDate = $b['upload_date'] ?? date('Y-m-d');
 $bCategory = $b['category'] ?? 'مقرر دراسي';
 
 $fileUrl = 'uploads/books/' . htmlspecialchars($bFile);
 ?>
 <!-- Book Card -->
 <div class="bg-white rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 border border-gray-100 overflow-hidden group flex flex-col h-full transform hover:-translate-y-1">
 <!-- Icon Area -->
 <div class="h-40 bg-bg flex items-center justify-center relative border-b border-gray-50">
 <i class="fas fa-file-pdf text-6xl text-gray-300 group-hover:text-primary transition-colors duration-300 transform group-hover:scale-110"></i>
 <span class="absolute top-3 right-3 bg-white/80 backdrop-blur text-gray-600 text-xs px-2.5 py-1 rounded-lg font-bold shadow-sm border border-gray-100">
 <?php echo htmlspecialchars($bCategory); ?>
 </span>
 </div>

 <!-- Content -->
 <div class="p-6 flex-grow flex flex-col">
 <h3 class="font-bold text-lg text-gray-800 mb-2 leading-tight line-clamp-2" title="<?php echo htmlspecialchars($bTitle); ?>">
 <?php echo htmlspecialchars($bTitle); ?>
 </h3>
 
 <div class="mt-auto pt-4 space-y-4">
 <div class="flex items-center justify-between text-xs text-gray-400">
 <span class="flex items-center gap-1"><i class="far fa-clock"></i> <?php echo htmlspecialchars($bDate); ?></span>
 <span class="flex items-center gap-1"><i class="fas fa-file-alt"></i> PDF</span>
 </div>

 <a href="<?php echo $fileUrl; ?>" download class="block w-full text-center bg-bg text-primary hover:bg-primary hover:text-white py-2.5 rounded-xl transition-all duration-300 font-bold text-sm shadow-sm hover:shadow-md">
 <i class="fas fa-download mr-1"></i>
 تحميل الملف
 </a>
 </div>
 </div>
 </div>
 <?php endforeach; ?>
 <?php endif; ?>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>