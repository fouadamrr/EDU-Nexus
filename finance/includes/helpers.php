<?php
// دوال مساعدة عشان نتأكد من نوع الحساب (الرتبة)

if (!function_exists('hasRole')) {
 // اتأكد هل اليوزر دا تبع الرتبة دي ولا لاء
 function hasRole(string|array $roles): bool
 {
 $current = $_SESSION['role'] ?? null;
 if ($current === null) {
 return false;
 }
 if (is_string($roles)) {
 $roles = [$roles];
 }
 return in_array($current, $roles, true);
 }
}

if (!function_exists('requireRole')) {
 // اطرده لو داخل في حتة مش بتاعته وحوله لمكان تانى
 function requireRole(string|array $roles, ?string $redirectTo = null): void
 {
 if (hasRole($roles)) {
 return; // Access granted — nothing to do.
 }

 // هنوديه فين لو ملوش حق يدخل؟
 if ($redirectTo === null) {
 $currentRole = $_SESSION['role'] ?? 'student';
 $redirectTo = match ($currentRole) {
 'super_admin' => 'super_admin_dashboard.php',
 'admin', 'dean',
 'affairs' => 'admin_dashboard.php',
 'instructor' => 'instructor_dashboard.php',
 default => 'dashboard.php',
 };
 }

 // رسالة بالعربي كدا عشان يفهم فيه إيه
 $safeUrl = htmlspecialchars($redirectTo, ENT_QUOTES, 'UTF-8');

 if (headers_sent()) {
 // HTML already started — use JS redirect
 echo "<script>
 alert('عذراً، ليس لديك صلاحية الوصول إلى هذه الصفحة.');
 window.location.href = '{$safeUrl}';
 </script>";
 } else {
 // No output yet — use HTTP redirect
 header("Location: {$redirectTo}");
 }
 exit;
 }
}

if (!function_exists('currentRole')) {
 // هات رتبة اليوزر اللي داخل دلوقتي
 function currentRole(string $default = 'student'): string
 {
 return $_SESSION['role'] ?? $default;
 }
}

if (!function_exists('isStudent')) {
 // للتحقق السريع من نوع الحساب
 function isStudent(): bool { return hasRole('student'); }
}

if (!function_exists('isInstructor')) {
 // للتحقق السريع من نوع الحساب
 function isInstructor(): bool { return hasRole('instructor'); }
}

if (!function_exists('isAdmin')) {
 // للتحقق السريع من نوع الحساب
 function isAdmin(): bool { return hasRole(['admin', 'super_admin']); }
}

if (!function_exists('isSuperAdmin')) {
 // للتحقق السريع من نوع الحساب
 function isSuperAdmin(): bool { return hasRole('super_admin'); }
}

if (!function_exists('url')) {
 // بنعمل الـ URL بشكل نظيف
 function url(string $path = '', array $params = []): string
 {
 // Remove .php extension if provided
 $path = preg_replace('/\.php$/', '', $path);
 
 // Handle path that already contains query string
 if (strpos($path, '?') !== false) {
 list($path, $queryStr) = explode('?', $path, 2);
 parse_str($queryStr, $existingParams);
 $params = array_merge($existingParams, $params);
 }

 $path = trim($path, '/');
 
 // لو عايز يروح للصفحة الرئيسية
 if ($path === 'index' || $path === '') return '/';

 // Extract cleanable params (action, type, tab) to make them path segments
 $cleanKeys = ['action', 'type', 'tab'];
 foreach ($cleanKeys as $key) {
 if (isset($params[$key])) {
 $path .= '/' . $params[$key];
 unset($params[$key]);
 break; // Only clean one parameter as path segment
 }
 }

 // Calculate actual web root
 $scriptName = $_SERVER['SCRIPT_NAME'];
 $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
 
 $url = $baseDir . '/' . $path;
 
 // Append remaining query params if any
 if (!empty($params)) {
 $url .= '?' . http_build_query($params);
 }
 
 return rtrim($url, '/') ?: '/';
 }
}

if (!function_exists('redirect')) {
 // حوله لصفحة تانية خالص
 function redirect(string $path)
 {
 $url = url($path);
 if (headers_sent()) {
 echo "<script>window.location.href='{$url}';</script>";
 } else {
 header("Location: {$url}");
 }
 exit;
 }
}
