<?php
require_once 'lang.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang['code']; ?>" dir="<?php echo $lang['dir']; ?>">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>وضع الصيانة - <?php echo $lang['title']; ?></title>
 <script src="https://cdn.tailwindcss.com"></script>
 <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
 <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
 <style>
 body { font-family: 'Cairo', sans-serif; background-color: #f8fafc; color: #334155; }
 </style>
</head>
<body class="flex flex-col h-screen overflow-hidden bg-bg items-center justify-center p-4">
 <div class="max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-100 text-center p-8">
 <div class="mb-6 flex justify-center">
 <div class="w-20 h-20 bg-primary rounded-full flex items-center justify-center text-primary shadow-inner">
 <i class="fas fa-tools text-4xl"></i>
 </div>
 </div>
 
 <h1 class="text-3xl font-bold text-slate-800 mb-4 tracking-tight">النظام في وضع الصيانة</h1>
 <p class="text-slate-500 mb-8 leading-relaxed">
 عذراً، نظام EDU Nexus يخضع حالياً لبعض أعمال الصيانة والتحديثات لتحسين الخدمة. يرجى المحاولة لاحقاً.
 </p>

 <div class="space-y-4">
 <a href="index.php" class="block w-full bg-primary hover:bg-primary text-white font-bold py-3 px-4 rounded-xl transition-all duration-200 shadow-md hover:shadow-lg focus:ring-4 focus:ring-blue-100">
 <i class="fas fa-redo ml-2"></i> تحديث الصفحة
 </a>
 
 <a href="logout.php" class="block w-full bg-bg hover:bg-slate-200 text-slate-600 font-bold py-3 px-4 rounded-xl transition-colors duration-200">
 تسجيل الخروج
 </a>
 </div>
 
 <div class="mt-8 pt-6 border-t border-slate-100">
 <p class="text-xs text-slate-400">إذا كنت مدير النظام، يمكنك تسجيل الدخول والوصول للنظام لتغيير هذه الإعدادات.</p>
 </div>
 </div>
</body>
</html>
