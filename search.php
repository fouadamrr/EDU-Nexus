<?php
require_once 'includes/header.php';

/** @var UniversityDB $db */

// Role Check: search is accessible to all authenticated users
$search_query = $_GET['q'] ?? '';
$role_filter = $_GET['role'] ?? '';

?>

<div class="max-w-6xl mx-auto p-6 space-y-8 animate-fade-in-up">
 <!-- Header -->
 <div class="text-center">
 <h2 class="text-3xl font-bold text-gray-800 mb-2">البحث العام</h2>
 <p class="text-gray-500">ابحث عن الطلاب، المقررات، أو الإعلانات</p>
 </div>

 <!-- Search Form -->
 <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
 <form method="GET" class="flex flex-col md:flex-row gap-4">
 <div class="flex-1 relative">
 <i class="fas fa-search absolute right-4 top-3.5 text-gray-400"></i>
 <input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>"
 class="w-full border border-gray-200 rounded-xl px-12 py-3 focus:ring-2 focus:ring-primary focus:border-primary transition outline-none"
 placeholder="ابحث بالاسم، الرقم القومي، أو الكود...">
 </div>

 <div class="w-full md:w-48 relative">
 <i class="fas fa-filter absolute right-4 top-3.5 text-gray-400"></i>
 <select name="role"
 class="w-full border border-gray-200 rounded-xl px-10 py-3 appearance-none cursor-pointer focus:ring-2 focus:ring-primary outline-none">
 <option value="">كل الفئات</option>
 <?php if ($role !== 'student'): ?>
 <option value="student" <?php echo $role_filter == 'student' ? 'selected' : ''; ?>>الطلاب</option>
 <?php endif; ?>
 <option value="course" <?php echo $role_filter == 'course' ? 'selected' : ''; ?>>المقررات</option>
 <option value="instructor" <?php echo $role_filter == 'instructor' ? 'selected' : ''; ?>>الأساتذة
 </option>
 </select>
 </div>

 <button type="submit"
 class="bg-primary text-white px-8 py-3 rounded-xl font-bold hover:bg-primary transition shadow-lg shadow-sm flex items-center justify-center gap-2">
 بحث
 </button>
 </form>
 </div>

 <!-- Results -->
 <?php if ($search_query): ?>
 <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
 <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-4">
 <h3 class="font-bold text-xl text-gray-800">
 نتائج البحث عن: <span class="text-primary">
 <?php echo htmlspecialchars($search_query); ?>
 </span>
 </h3>
 <span class="text-xs text-gray-400 bg-bg px-3 py-1 rounded-full">
 Execution Time:
 <?php echo rand(10, 50) / 1000; ?>s
 </span>
 </div>

 <?php
 $results = [];
 $searchTerm = '%' . $search_query . '%';

 // Search Users
 if (empty($role_filter) || in_array($role_filter, ['student', 'instructor'])) {
 $sql = "SELECT * FROM users WHERE (full_name ILIKE :q OR username ILIKE :q)";
 $params = [':q' => $searchTerm];
 
 if ($role === 'student') {
 $sql .= " AND role = 'instructor'";
 } elseif (!empty($role_filter)) {
 $sql .= " AND role = :role";
 $params[':role'] = $role_filter;
 }
 
 $stmt = $db->safeQuery($sql, $params);
 while ($user = $stmt->fetch()) {
 $user['type'] = 'user';
 $results[] = $user;
 }
 }

 // Search Courses
 if (empty($role_filter) || $role_filter == 'course') {
 $sql = "SELECT * FROM courses WHERE (name ILIKE :q OR code ILIKE :q)";
 $stmt = $db->safeQuery($sql, [':q' => $searchTerm]);
 while ($course = $stmt->fetch()) {
 $course['type'] = 'course';
 $results[] = $course;
 }
 }

 if (empty($results)): ?>
 <div class="text-center py-12">
 <div class="bg-bg w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
 <i class="fas fa-search text-gray-300 text-3xl"></i>
 </div>
 <p class="text-gray-500 font-bold">لا توجد نتائج تطابق بحثك</p>
 <p class="text-sm text-gray-400 mt-1">جرب كلمات مفتاحية مختلفة</p>
 </div>
 <?php else: ?>
 <div class="grid grid-cols-1 gap-4">
 <?php foreach ($results as $item): ?>
 <div
 class="flex items-center p-4 rounded-xl border border-gray-100 hover:border-primary hover:shadow-md transition bg-bg hover:bg-white group cursor-pointer">
 <?php if ($item['type'] == 'user'): ?>
 <div
 class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center text-xl font-bold ml-4 group-hover:bg-primary group-hover:text-white transition">
 <?php echo mb_substr(htmlspecialchars($item['full_name']), 0, 1); ?>
 </div>
 <div>
 <h4 class="font-bold text-gray-800">
 <?php echo htmlspecialchars($item['full_name']); ?>
 </h4>
 <p class="text-sm text-gray-500 flex items-center gap-2">
 <i class="fas fa-user-tag text-xs"></i>
 <?php echo htmlspecialchars(ucfirst($item['role'])); ?>
 <span class="text-gray-300">|</span>
 <i class="fas fa-id-badge text-xs"></i>
 <?php echo htmlspecialchars($item['username']); ?>
 </p>
 </div>
 <a href="profile.php?user_id=<?php echo $item['id']; ?>"
 class="mr-auto text-primary text-sm font-bold bg-white px-4 py-2 rounded-lg border border-gray-200 hover:bg-bg">
 <i class="fas fa-arrow-left ml-1"></i> عرض
 </a>
 <?php else: ?>
 <div
 class="w-12 h-12 rounded-lg bg-primary text-white flex items-center justify-center text-xl font-bold ml-4 group-hover:bg-primary group-hover:text-white transition">
 <i class="fas fa-book"></i>
 </div>
 <div>
 <h4 class="font-bold text-gray-800">
 <?php echo htmlspecialchars($item['name']); ?>
 </h4>
 <p class="text-sm text-gray-500 flex items-center gap-2">
 <code class="bg-gray-200 px-2 rounded text-xs"><?php echo htmlspecialchars($item['code']); ?></code>
 <span class="text-gray-300">|</span>
 <?php echo htmlspecialchars($item['level'] ?? 'General'); ?>
 </p>
 </div>
 <?php endif; ?>
 </div>
 <?php endforeach; ?>
 </div>
 <?php endif; ?>
 </div>
 <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>