<?php
require_once 'includes/header.php';

// اتأكد إن اللي داخل دا طالب عشان دي مكتبة الطلاب
if ($role !== 'student') {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}

require_once __DIR__ . '/config/database.php';
$db = Database::getConnection();

// التأكد إن جدول الاستعارات موجود
$db->exec("
 CREATE TABLE IF NOT EXISTS borrowed_books (
 id SERIAL PRIMARY KEY,
 book_id INT NOT NULL,
 student_id INT NOT NULL,
 request_date TIMESTAMPTZ DEFAULT NOW(),
 borrow_date TIMESTAMPTZ,
 return_date TIMESTAMPTZ,
 status VARCHAR(20) DEFAULT 'pending' -- pending, approved, returned, rejected
 );
");

$student_id = (int)$_SESSION['user_id'];
$message = '';

// التعامل مع الطلبات (لو الطالب حب يستعير كتاب)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'borrow') {
 $book_id = (int)$_POST['book_id'];
 
 // بنشوف لو فيه نسخ باقية من الكتاب دا
 $stmtBook = $db->prepare("SELECT title, available_copies FROM resources WHERE id = ?");
 $stmtBook->execute([$book_id]);
 $book = $stmtBook->fetch();
 if ($book && $book['available_copies'] > 0) {
 // بنشوف لو الطالب طلب الكتاب دا قبل كدا ولسه الطلب شغال
 $stmtExist = $db->prepare("SELECT id FROM borrowed_books WHERE book_id = ? AND student_id = ? AND status IN ('pending', 'approved')");
 $stmtExist->execute([$book_id, $student_id]);
 $existing = $stmtExist->fetch();
 if ($existing) {
 $message = '<div class="bg-primary text-white p-4 rounded-xl mb-6 font-bold flex items-center gap-2"><i class="fas fa-exclamation-triangle"></i> لقد قمت بطلب أو استعارة هذا الكتاب بالفعل.</div>';
 } else {
 $stmt = $db->prepare("INSERT INTO borrowed_books (book_id, student_id, status) VALUES (?, ?, 'pending')");
 $stmt->execute([$book_id, $student_id]);
 $message = '<div class="bg-primary text-white p-4 rounded-xl mb-6 font-bold flex items-center gap-2"><i class="fas fa-check-circle"></i> تم إرسال طلب الاستعارة بنجاح. يرجى التوجه للمكتبة بعد قبول الطلب لاستلام الكتاب.</div>';
 }
 } else {
 $message = '<div class="bg-primary text-white p-4 rounded-xl mb-6 font-bold flex items-center gap-2"><i class="fas fa-times-circle"></i> نعتذر، لا توجد نسخ متاحة من هذا الكتاب حالياً.</div>';
 }
}

// بنجيب لستة المراجع والطلبات اللي الطالب قدمها
$books = $db->query("SELECT * FROM resources ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$my_requests = [];
$stmtReqs = $db->prepare("SELECT book_id, status, request_date, return_date FROM borrowed_books WHERE student_id = ?");
$stmtReqs->execute([$student_id]);
$reqs = $stmtReqs->fetchAll(PDO::FETCH_ASSOC);
foreach($reqs as $r) {
 if (!isset($my_requests[$r['book_id']]) || $r['status'] === 'approved' || $r['status'] === 'pending') {
 $my_requests[$r['book_id']] = $r;
 }
}

$status_ar = [
 'pending' => '<span class="px-2 py-1 bg-primary text-white rounded-lg text-xs font-bold">قيد المراجعة ⏳</span>',
 'approved' => '<span class="px-2 py-1 bg-primary text-white rounded-lg text-xs font-bold">تمت الموافقة ✅</span>',
 'returned' => '<span class="px-2 py-1 bg-bg text-gray-700 rounded-lg text-xs font-bold">تم الإرجاع 🔄</span>',
 'rejected' => '<span class="px-2 py-1 bg-secondary text-white rounded-lg text-xs font-bold">مرفوض ❌</span>',
];
?>

<div class="max-w-6xl mx-auto space-y-8 pb-12">
 
 <!-- عنوان الصفحة والترحيب -->
 <div class="bg-bg rounded-2xl p-8 text-white shadow-lg relative overflow-hidden">
 <div class="absolute -right-10 -top-10 w-40 h-40 bg-white opacity-10 rounded-full blur-2xl"></div>
 <div class="relative z-10 flex items-center gap-4">
 <div class="w-16 h-16 bg-white/20 backdrop-blur rounded-2xl flex items-center justify-center">
 <i class="fas fa-book-reader text-3xl"></i>
 </div>
 <div>
 <h1 class="text-3xl font-bold">المكتبة المركزية</h1>
 <p class="text-primary mt-2 font-medium">تصفح واطلب استعارة الكتب والمراجع الورقية من مكتبة الجامعة.</p>
 </div>
 </div>
 </div>

 <?php echo $message; ?>

 <!-- عرض الكتب في كروت -->
 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
 <?php if (empty($books)): ?>
 <div class="col-span-full py-16 text-center text-gray-400">
 <i class="fas fa-books text-5xl mb-4 opacity-50 block"></i>
 <p class="text-xl font-bold">لا توجد كتب في المكتبة حالياً</p>
 </div>
 <?php else: ?>
 <?php foreach ($books as $b): 
 $req = $my_requests[$b['id']] ?? null;
 ?>
 <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition-all flex flex-col h-full group">
 <div class="h-40 bg-bg flex items-center justify-center relative border-b border-gray-100 pt-4">
 <i class="fas fa-book text-6xl text-gray-300 group-hover:text-primary transition-colors"></i>
 <span class="absolute top-3 right-3 bg-bg border border-primary text-primary text-xs px-2.5 py-1 rounded-lg font-bold">
 <?php echo htmlspecialchars($b['category'] ?? 'عام'); ?>
 </span>
 </div>
 <div class="p-5 flex-grow flex flex-col">
 <h3 class="font-bold text-gray-800 text-lg mb-1 line-clamp-2" title="<?php echo htmlspecialchars($b['title']); ?>">
 <?php echo htmlspecialchars($b['title']); ?>
 </h3>
 <p class="text-sm text-gray-500 mb-4 font-medium"><i class="fas fa-user-edit text-xs mr-1 opacity-70"></i> <?php echo htmlspecialchars($b['author']); ?></p>

 <div class="mt-auto space-y-4">
 <div class="flex justify-between items-center bg-bg p-3 rounded-xl border border-gray-100">
 <div class="text-center">
 <span class="block text-xs text-gray-400 font-bold mb-0.5">متاح للاستعارة</span>
 <span class="font-mono font-bold <?php echo $b['available_copies'] > 0 ? 'text-primary' : 'text-primary'; ?> text-base">
 <?php echo $b['available_copies']; ?>
 </span>
 </div>
 <div class="w-px h-8 bg-gray-200"></div>
 <div class="text-center">
 <span class="block text-xs text-gray-400 font-bold mb-0.5">إجمالي النسخ</span>
 <span class="font-mono font-bold text-gray-600 text-base">
 <?php echo $b['total_copies']; ?>
 </span>
 </div>
 </div>

 <?php if ($req): ?>
 <div class="text-center p-2.5 bg-bg border border-gray-200 rounded-xl">
 <div class="text-xs text-gray-500 font-bold mb-1">حالة طلبك</div>
 <?php echo $status_ar[$req['status']] ?? $req['status']; ?>
 </div>
 <?php elseif ($b['available_copies'] > 0): ?>
 <form method="POST">
 <input type="hidden" name="action" value="borrow">
 <input type="hidden" name="book_id" value="<?php echo $b['id']; ?>">
 <button type="submit" class="w-full bg-primary hover:bg-primary text-white font-bold py-2.5 rounded-xl transition shadow-sm flex items-center justify-center gap-2">
 <i class="fas fa-hand-holding-heart"></i> طلب استعارة
 </button>
 </form>
 <?php else: ?>
 <button disabled class="w-full bg-bg text-gray-400 font-bold py-2.5 rounded-xl cursor-not-allowed flex items-center justify-center gap-2 border border-gray-200">
 <i class="fas fa-times-circle"></i> غير متاح حالياً
 </button>
 <?php endif; ?>
 </div>
 </div>
 </div>
 <?php endforeach; ?>
 <?php endif; ?>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>
