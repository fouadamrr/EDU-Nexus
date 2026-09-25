<?php
require_once 'includes/header.php';
?>
<!-- مكتبة تحويل صور الأيفون (HEIC) -->
<script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
<script>
async function handleImageUpload(input) {
    const file = input.files[0];
    if (!file) return;

    const fileName = file.name.toLowerCase();
    // دعم كل أنواع الامتدادات بتاعة الأيفون
    const isHeic = fileName.endsWith('.heic') || fileName.endsWith('.heif') || fileName.endsWith('.heif-sequence') || fileName.endsWith('.heic-sequence');

    if (isHeic) {
        // بنوريله إننا بنحمل الصورة
        const overlay = document.getElementById('upload-overlay');
        const text = document.getElementById('upload-text');
        
        // بنجيب أقرب حاوية عشان نظهر اللودر
        const group = input.closest('.group');
        group.classList.add('opacity-100'); // Force overlay visibility
        
        text.innerHTML = '<i class="fas fa-circle-notch fa-spin mb-1 block text-lg"></i>يتم التحويل...';
        
        try {
            // Lazy load the library if needed (it's already in the head but just in case)
            if (typeof heic2any === 'undefined') {
                throw new Error("Library not loaded");
            }

            // تحويل الصورة لصيغة JPG
            const blob = await heic2any({
                blob: file,
                toType: "image/jpeg",
                quality: 0.7
            });
            
            // بنجهز البيانات و الصورة اللي اتحولت عشان تتبعث
            const formData = new FormData();
            formData.append('profile_pic', blob, 'profile_camera.jpg');
            
            // نبعت للموقع
            const response = await fetch('profile.php' + window.location.search, {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                window.location.reload();
            } else {
                alert('عذراً، حدث خطأ أثناء رفع الصورة بعد التحويل.');
                window.location.reload();
            }
        } catch (error) {
            console.error("HEIC Conversion error:", error);
            alert('فشل تحويل صورة الأيفون تلقائياً. يرجى محاولة أخذ لقطة شاشة للصورة ورفعها أو تجربة متصفح آخر.');
            window.location.reload();
        }
    } else {
        // لو صورة عادية بنرفعها علطول
        input.form.submit();
    }
}
</script>
<?php

require_once __DIR__ . '/controllers/ProfileController.php';
$profileController = new ProfileController();

$target_id = (int)($_GET['user_id'] ?? $user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
 $res = $profileController->handleProfileUpload($_FILES, $target_id);
 if ($res) echo $res;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['address'])) {
 echo $profileController->handleAddressUpdate($_POST['address'], $target_id);
}

$user_data = $profileController->getProfileData($target_id, (int)$user_id);

if (!$user_data) {
 echo "<div class='p-4 bg-primary text-white font-bold text-center'>المستخدم غير موجود</div>";
 require_once 'includes/footer.php';
 exit;
}

// حماية الخصوصية: الطالب ميقدرش يدخل يشوف بروفايل حد تاني
if ($role === 'student' && $target_id != $user_id) {
 if (($user_data['role'] ?? '') !== 'instructor') {
 echo "<script>alert('خصوصية الطلاب: لا يسمح للطلاب بالدخول على ملفات زملائهم.'); window.location.href='dashboard.php';</script>";
 exit;
 }
}
?>

<div class="bg-white rounded-xl shadow-lg p-8 max-w-4xl mx-auto">
 <!-- IDOR Warning for Educational Purpose (Optional, usually hidden) -->
 <?php if ($target_id != $user_id): ?>
 <!--<div class="bg-bg border-r-4 border-primary p-4 mb-6">
 <p class="font-bold text-primary">⚠️ تنبيه أمني:</p>
 <p class="text-sm text-primary">أنت تشاهد ملف مستخدم آخر (ID: <?php echo $target_id; ?>). هذا يمثل ثغرة IDOR.
 </p>
 </div>-->
 <?php endif; ?>

 <div class="border-b border-gray-100 pb-6 mb-6 flex items-center justify-between">
 <div class="flex items-center gap-4">
 <!-- شاشة رفع الصورة الشخصية -->
 <div class="relative group">
 <img src="<?php echo $user_data['profile_pic'] ?? 'assets/avatar.png'; ?>"
 class="w-20 h-20 rounded-full object-cover border-2 border-primary">

  <!-- فورم رفع الصورة الشخصية -->
  <form method="POST" enctype="multipart/form-data" id="upload-form"
  class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 rounded-full opacity-0 group-hover:opacity-100 transition cursor-pointer">
  <label class="cursor-pointer text-white text-xs text-center" id="upload-overlay">
  <span id="upload-text">
  <i class="fas fa-camera mb-1 block"></i>
  تغيير
  </span>
  <input type="file" name="profile_pic" class="hidden" onchange="handleImageUpload(this)">
  </label>
  </form>
 </div>

 <div>
 <h2 class="text-2xl font-bold text-primary">
 <?php echo htmlspecialchars($user_data['full_name']); ?>
 </h2>
 <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($user_data['username']); ?></p>
 </div>
 </div>

 <span class="bg-primary text-white px-3 py-1 rounded-full text-sm font-bold">
 <?php 
 $status_text = ($user_data['enrollment_status'] == 'enrolled' ? 'مقيد' : 'غير مقيد');
 if (!empty($user_data['college_name'])) {
 $status_text .= ' في ' . $user_data['college_name'];
 }
 echo htmlspecialchars($status_text); 
 ?>
 </span>
 </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Basic Info -->
        <div class="space-y-4">
            <h3 class="font-bold text-gray-700 border-b pb-2">البيانات الأساسية</h3>
            <div>
                <label class="block text-gray-400 text-xs">الرقم القومي</label>
                <p class="font-bold text-gray-800"><?php echo htmlspecialchars($user_data['national_id']); ?></p>
            </div>
            <div>
                <label class="block text-gray-400 text-xs">رقم الهاتف</label>
                <p class="font-bold text-gray-800"><?php echo htmlspecialchars($user_data['phone'] ?? 'غير مسجل'); ?></p>
            </div>
            <div>
                <label class="block text-gray-400 text-xs">تاريخ الميلاد</label>
                <p class="font-bold text-gray-800">
                    <?php echo htmlspecialchars($user_data['birth_date'] ?? 'غير مسجل'); ?></p>
            </div>
            <div>
                <label class="block text-gray-400 text-xs">العنوان (قابل للتعديل)</label>
                <form method="POST" class="flex gap-2">
                    <input type="text" name="address" value="<?php echo htmlspecialchars($user_data['address'] ?? ''); ?>"
                        class="border-b border-gray-300 focus:border-primary outline-none py-1 w-full bg-transparent">
                    <button class="text-primary text-xs">حفظ</button>
                </form>
            </div>
        </div>

        <!-- Academic / Roles Info -->
        <div class="space-y-4">
            <h3 class="font-bold text-gray-700 border-b pb-2">البيانات الإدارية / الأكاديمية</h3>
            <div>
                <label class="block text-gray-400 text-xs">الكلية</label>
                <p class="font-bold text-gray-800"><?php echo htmlspecialchars($user_data['college_name'] ?? 'غير محدد'); ?></p>
            </div>
            
            <?php if (($user_data['role'] ?? '') === 'student'): ?>
            <div>
                <label class="block text-gray-400 text-xs">التخصص</label>
                <p class="font-bold text-gray-800"><?php echo htmlspecialchars($user_data['major'] ?? 'غير محدد'); ?></p>
            </div>
            <div>
                <label class="block text-gray-400 text-xs">المستوى الدراسي</label>
                <p class="font-bold text-gray-800">المستوى <?php echo htmlspecialchars($user_data['level'] ?? 'غير محدد'); ?></p>
            </div>
            <div>
                <label class="block text-gray-400 text-xs">المعدل التراكمي (GPA)</label>
                <p class="font-bold text-primary text-xl"><?php echo htmlspecialchars($user_data['gpa'] ?? '0.00'); ?></p>
            </div>
            
            <?php if (!empty($user_data['nomination_card'])): ?>
            <div class="mt-4">
                <label class="block text-gray-400 text-xs mb-2">بطاقة الترشيح</label>
                <a href="<?php echo htmlspecialchars($user_data['nomination_card']); ?>" target="_blank" class="inline-block">
                    <img src="<?php echo htmlspecialchars($user_data['nomination_card']); ?>" class="w-32 h-20 object-cover rounded-lg border hover:scale-105 transition-transform">
                </a>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div>
                <label class="block text-gray-400 text-xs">الدور الوظيفي</label>
                <p class="font-bold text-primary">
                    <?php 
                        $roles_map = ['instructor'=>'عضو هيئة تدريس', 'dean'=>'عميد الكلية', 'affairs'=>'شؤون الطلاب', 'admin'=>'مدير النظام', 'super_admin'=>'رئيس الجامعة'];
                        echo $roles_map[$user_data['role']] ?? $user_data['role'];
                    ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>