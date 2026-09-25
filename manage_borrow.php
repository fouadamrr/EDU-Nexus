<?php
require_once 'includes/header.php';

// الصلاحيات: أدمن أو عميد أو شؤون طلاب بس اللي يقدروا يدخلوا هنا
if (!in_array($role, ['admin', 'dean', 'affairs', 'super_admin'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('library');


require_once __DIR__ . '/config/database.php';
$db = Database::getConnection();

// التأكد إن جدول الاستعارات موجود في الداتا بيز
$db->exec("
 CREATE TABLE IF NOT EXISTS borrowed_books (
 id SERIAL PRIMARY KEY,
 book_id INT NOT NULL,
 student_id INT NOT NULL,
 request_date TIMESTAMPTZ DEFAULT NOW(),
 borrow_date TIMESTAMPTZ,
 return_date TIMESTAMPTZ,
 status VARCHAR(20) DEFAULT 'pending'
 );
");

$message = '';

// التعامل مع الإجراءات (موافقة، رفض، أو إرجاع الكتاب)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
 $req_id = (int)$_POST['request_id'];
 $action = $_POST['action'];
 
 // بنجيب تفاصيل الطلب اللي هيتعدل
 $stmtReq = $db->prepare("SELECT * FROM borrowed_books WHERE id = ?");
 $stmtReq->execute([$req_id]);
 $req = $stmtReq->fetch(PDO::FETCH_ASSOC);
 if ($req) {
 $book_id = $req['book_id'];
 
 if ($action === 'approve' && $req['status'] === 'pending') {
 // بنتأكد إن لسه فيه نسخ متاحة قبل ما نوافق
 $stmtBook = $db->prepare("SELECT available_copies FROM resources WHERE id = ?");
 $stmtBook->execute([$book_id]);
 $book = $stmtBook->fetch();
 if ($book && $book['available_copies'] > 0) {
 $db->beginTransaction();
 try {
 $stmtUp1 = $db->prepare("UPDATE resources SET available_copies = available_copies - 1 WHERE id = ?");
 $stmtUp1->execute([$book_id]);
 $stmtUp2 = $db->prepare("UPDATE borrowed_books SET status = 'approved', borrow_date = NOW() WHERE id = ?");
 $stmtUp2->execute([$req_id]);
 $db->commit();
 $message = '<div class="bg-primary text-white p-3 rounded-lg mb-4 font-bold">✅ تمت الموافقة على الاستعارة. (تم خصم نسخة من الرصيد المتاح)</div>';
 } catch(Exception $e) {
 $db->rollBack();
 }
 } else {
 $message = '<div class="bg-primary text-white p-3 rounded-lg mb-4 font-bold">❌ لا توجد نسخ متاحة من هذا الكتاب حالياً.</div>';
 }
 } 
 elseif ($action === 'reject' && $req['status'] === 'pending') {
 $stmtRej = $db->prepare("UPDATE borrowed_books SET status = 'rejected' WHERE id = ?");
 $stmtRej->execute([$req_id]);
 $message = '<div class="bg-secondary text-white p-3 rounded-lg mb-4 font-bold">❌ تم رفض الطلب.</div>';
 }
 elseif ($action === 'return' && $req['status'] === 'approved') {
 $db->beginTransaction();
 try {
 $stmtRet1 = $db->prepare("UPDATE resources SET available_copies = available_copies + 1 WHERE id = ?");
 $stmtRet1->execute([$book_id]);
 $stmtRet2 = $db->prepare("UPDATE borrowed_books SET status = 'returned', return_date = NOW() WHERE id = ?");
 $stmtRet2->execute([$req_id]);
 $db->commit();
 $message = '<div class="bg-primary text-white p-3 rounded-lg mb-4 font-bold">🔄 تم استلام الكتاب من الطالب. (تمت إعادة النسخة للرصيد المتاح)</div>';
 } catch(Exception $e) {
 $db->rollBack();
 }
 }
 }
}

// بنجيب كل الطلبات من الداتا بيز عشان نعرضهم في الصفحة
$query = "
 SELECT bb.*, 
 b.title as book_title, b.author,
 u.full_name as student_name, u.username as student_id_code
 FROM borrowed_books bb
 JOIN resources b ON bb.book_id = b.id
 JOIN users u ON bb.student_id = u.id
 ORDER BY CASE bb.status
 WHEN 'pending' THEN 1
 WHEN 'approved' THEN 2
 ELSE 3
 END, bb.request_date DESC
";
$requests = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="max-w-6xl mx-auto space-y-6">

 <div class="flex items-center justify-between bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
 <div>
 <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
 <i class="fas fa-exchange-alt text-primary bg-bg p-3 rounded-xl"></i>
 إدارة استعارة الكتب
 </h2>
 <p class="text-gray-500 mt-1 font-medium">متابعة طلبات الطلاب واستلام الكتب المُعادة لتحديث الأرصدة التلقائي.</p>
 </div>
 <a href="manage_books.php" class="bg-bg text-gray-700 font-bold px-5 py-2.5 rounded-xl hover:bg-gray-200 transition flex items-center gap-2">
 <i class="fas fa-book"></i> رصيد المكتبة
 </a>
 </div>

 <?php echo $message; ?>

 <!-- جدول عرض طلبات الاستعارة -->
 <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden text-sm">
 <?php if (empty($requests)): ?>
 <div class="py-20 text-center text-gray-400">
 <i class="fas fa-clipboard-list text-5xl mb-4 block"></i>
 <p class="text-xl font-bold">لا توجد حركات استعارة مسجلة بعد</p>
 </div>
 <?php else: ?>
 <table class="w-full text-right">
 <thead class="bg-bg border-b border-gray-100 text-gray-500 uppercase font-bold text-xs tracking-wider">
 <tr>
 <th class="p-4 w-1/4">الكتاب</th>
 <th class="p-4 w-1/4">الطالب</th>
 <th class="p-4 text-center">تاريخ الطلب</th>
 <th class="p-4 text-center">الحالة</th>
 <th class="p-4 text-center">إجراءات</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-50">
 <?php foreach ($requests as $r): ?>
 <tr class="hover:bg-bg transition">
 <td class="p-4">
 <div class="font-bold text-gray-800 line-clamp-1 truncate" title="<?php echo htmlspecialchars($r['book_title']); ?>">
 <?php echo htmlspecialchars($r['book_title']); ?>
 </div>
 <div class="text-xs text-gray-400 mt-1"><i class="fas fa-pen-nib mr-1"></i> <?php echo htmlspecialchars($r['author']); ?></div>
 </td>
 <td class="p-4">
 <div class="font-bold text-gray-700"><?php echo htmlspecialchars($r['student_name']); ?></div>
 <div class="text-xs text-primary font-mono mt-1 font-bold"><i class="fas fa-id-card mr-1"></i> <?php echo htmlspecialchars($r['student_id_code']); ?></div>
 </td>
 <td class="p-4 text-center text-gray-500 font-mono text-xs">
 <?php echo date('Y-m-d H:i', strtotime($r['request_date'])); ?>
 </td>
 <td class="p-4 text-center">
 <?php if ($r['status'] === 'pending'): ?>
 <span class="bg-bg text-primary border border-primary px-3 py-1 rounded-lg text-xs font-bold inline-flex items-center gap-1"><i class="fas fa-clock"></i> بانتظار الموافقة</span>
 <?php elseif ($r['status'] === 'approved'): ?>
 <span class="bg-bg text-primary border border-primary px-3 py-1 rounded-lg text-xs font-bold inline-flex items-center gap-1"><i class="fas fa-book-reader"></i> في حوزة الطالب</span>
 <?php elseif ($r['status'] === 'returned'): ?>
 <span class="bg-bg text-gray-500 border border-gray-200 px-3 py-1 rounded-lg text-xs font-bold inline-flex items-center gap-1"><i class="fas fa-undo"></i> مُعاد</span>
 <?php elseif ($r['status'] === 'rejected'): ?>
 <span class="bg-bg text-primary border border-primary px-3 py-1 rounded-lg text-xs font-bold inline-flex items-center gap-1"><i class="fas fa-times"></i> مرفوض</span>
 <?php endif; ?>
 </td>
 <td class="p-4 text-center">
 <?php if ($r['status'] === 'pending'): ?>
 <form method="POST" class="inline-flex gap-2">
 <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
 <button type="submit" name="action" value="approve" class="bg-primary hover:bg-primary text-white w-8 h-8 rounded-lg flex items-center justify-center transition shadow-sm" title="موافقة وتسليم الطالب">
 <i class="fas fa-check"></i>
 </button>
 <button type="submit" name="action" value="reject" class="bg-secondary text-white hover:bg-secondary hover:text-white w-8 h-8 rounded-lg flex items-center justify-center transition" title="رفض الطلب" onclick="return confirm('تأكيد رفض الطلب؟');">
 <i class="fas fa-times"></i>
 </button>
 </form>
 <?php elseif ($r['status'] === 'approved'): ?>
 <form method="POST" class="inline">
 <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
 <button type="submit" name="action" value="return" class="bg-primary hover:bg-primary text-white px-3 py-1.5 rounded-lg text-xs font-bold flex items-center justify-center gap-2 mx-auto transition shadow-sm" onclick="return confirm('تأكيد استلام الكتاب من الطالب وإرجاعه لأرصدة المكتبة؟');">
 <i class="fas fa-hand-holding-box"></i> استلام الإرجاع
 </button>
 </form>
 <?php else: ?>
 <span class="text-gray-300">-</span>
 <?php endif; ?>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 <?php endif; ?>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>
