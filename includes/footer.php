 <!-- الحتة اللي تحت خالص (الفوتر) -->
 <footer class="mt-auto border-t border-slate-200 pt-8 pb-4 text-center print:hidden w-full">
 <p class="text-sm text-slate-500 font-medium">
 &copy; <?php echo date('Y'); ?> EDU Nexus — البوابة الأكاديمية
 </p>
 <p class="text-xs text-slate-400 mt-1">مركز تقنية الاتصالات والمعلومات</p>
 </footer>
</div> <!-- End of .max-w-7xl mx-auto space-y-8 -->
</div> <!-- نهاية حاوية المحتوى الرئيسي -->
 
 </main>
 </div> <!-- نهاية غلاف المحتوى الرئيسي -->

 <!-- السكربتات مجمعة في ui-fix.js -->
 <script>
 // نظام التنبيهات (اللي بتطلع من تحت دي)
 function showNotification(msg, type = 'info') {
 const colors = {
 'info': 'bg-accent',
 'success': 'bg-primary',
 'error': 'bg-primary',
 'warning': 'bg-primary'
 };
 const icons = {
 'info': 'fa-info-circle',
 'success': 'fa-check-circle',
 'error': 'fa-exclamation-circle',
 'warning': 'fa-exclamation-triangle'
 };

 const toast = document.createElement('div');
 toast.className = `fixed bottom-6 left-1/2 transform -translate-x-1/2 ${colors[type]} text-white px-6 py-3.5 rounded-xl shadow-card transition-all duration-300 opacity-0 translate-y-4 z-[100] flex items-center gap-3 w-max max-w-[90vw]`;
 toast.innerHTML = `<i class="fas ${icons[type]} text-lg"></i> <span class="font-medium text-sm sm:text-base">${msg}</span>`;
 document.body.appendChild(toast);

 // طريقة دخول التنبيه للشاشة
 requestAnimationFrame(() => {
 toast.classList.remove('opacity-0', 'translate-y-4');
 });

 // نخبيه تاني بعد 3 ثواني
 setTimeout(() => {
 toast.classList.add('opacity-0', 'translate-y-4');
 setTimeout(() => toast.remove(), 300);
 }, 3000);
 }

 // اربط زرار الإشعارات بالسيستم
 document.addEventListener('DOMContentLoaded', () => {
 const notifBtn = document.getElementById('notif-btn');
 if (notifBtn) {
 notifBtn.addEventListener('click', () => {
 showNotification('لا توجد إشعارات جديدة حالياً', 'info');
 });
 }
 });
 </script>
</body>
</html>