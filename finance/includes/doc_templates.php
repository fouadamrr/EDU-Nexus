<?php
// قوالب الورق الرسمي (شهادة قيد، بيان حالة، الخ)
// الملف دا فيه الأشكال اللي بتطبع للطلبة

if (!$selected_student) return;

$student_name = htmlspecialchars($selected_student['full_name'] ?? '');
$student_id = htmlspecialchars($selected_student['username'] ?? '');
$doc_key = (string)($selected_doc ?? '');
?>
<style>
/* الاستايلات المشتركة لكل الورق */
.doc-wrapper {
 font-family: 'Cairo', 'Arial', sans-serif;
 direction: rtl;
 text-align: right;
 color: #1a1a2e;
 max-width: 740px;
 margin: 0 auto;
 padding: 24px;
}
.doc-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #2563eb; padding-bottom: 16px; margin-bottom: 20px; }
.doc-logo { width: 70px; height: 70px; }
.doc-title-block { text-align: center; flex: 1; }
.doc-univ { font-size: 19px; font-weight: 900; color: #2563eb; }
.doc-college { font-size: 13px; font-weight: 700; color: #475569; margin-top: 2px; }
.doc-doc-title { font-size: 22px; font-weight: 900; color: #2563eb; text-align: center;
 margin: 20px 0; padding: 10px; border: 2px solid #2563eb; border-radius: 8px; background: #eff6ff; }
.doc-section { margin-bottom: 18px; }
.doc-field { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 10px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px; }
.doc-label { font-weight: 700; color: #475569; min-width: 160px; font-size: 14px; }
.doc-value { font-weight: 600; color: #1a1a2e; font-size: 14px; flex: 1; }
.doc-footer { margin-top: 40px; display: flex; justify-content: space-between; align-items: flex-end; }
.doc-stamp { font-size: 11px; color: #94a3b8; }
.doc-sign { text-align: center; }
.doc-sign-line { border-top: 1px solid #334155; width: 180px; margin: 8px auto 0; }
.doc-serial { font-size: 11px; color: #94a3b8; font-family: monospace; }
.doc-notice { background: #fef9c3; border: 1px solid #fde047; border-radius: 8px; padding: 10px 14px; font-size: 12px; color: #713f12; margin-top: 18px; }
</style>

<?php if ($doc_key !== 'graduation_certificate'): ?>
<div class="doc-wrapper">

 <!-- بيانات الجامعة من فوق -->
 <div class="doc-header">
 <div style="text-align:center; font-size:11px; color:#94a3b8; direction:ltr;">
 س / <?php echo htmlspecialchars($doc_serial); ?><br>
 <?php echo date('Y-m-d'); ?>
 </div>
 <div class="doc-title-block">
 <div class="doc-univ"><?php echo htmlspecialchars($university_name); ?></div>
 <?php if ($college_name): ?>
 <div class="doc-college">كلية <?php echo htmlspecialchars($college_name); ?></div>
 <?php endif; ?>
 <div style="font-size:11px; color:#94a3b8; margin-top:3px;">شعبة شئون الطلاب</div>
 </div>
 <img src="assets/images/logo.png" class="doc-logo" alt="شعار الجامعة" onerror="this.style.display='none'">
 </div>

 <!-- عنوان الورقة -->
 <?php
 $titles = [
  'enrollment_proof'       => 'شهادة إثبات قيد',
  'status_statement'       => 'بيان حالة دراسية',
  'clearance'              => 'شهادة إخلاء طرف',
  'good_conduct'           => 'شهادة حسن سير وسلوك',
  'graduation_certificate' => 'شهادة التخرج',
 ];
 ?>
 <div class="doc-doc-title"><?php echo isset($titles[$doc_key]) ? $titles[$doc_key] : 'وثيقة رسمية'; ?></div>

 <!-- المقدمة بتاعة الشهادة -->
 <p style="font-size:14px; line-height:2; color:#334155; margin-bottom:20px;">
 <?php if ($selected_doc === 'enrollment_proof'): ?>
 تشهد شعبة شئون الطلاب بكلية <?php echo htmlspecialchars($college_name ?: 'غير محدده'); ?> التابعة لـ<?php echo htmlspecialchars($university_name); ?> بأن الطالب / الطالبة المبينة بياناته أدناه طالب مقيد بالكلية في العام الأكاديمي الحالي، وذلك للتقديم حيث يطلب.
 <?php elseif ($selected_doc === 'status_statement'): ?>
 يشهد مسجل كلية <?php echo htmlspecialchars($college_name ?: ''); ?> بأن الطالب / الطالبة الموضحة بياناته أدناه مسجل لدينا بالمعلومات التالية:
 <?php elseif ($selected_doc === 'clearance'): ?>
 تشهد إدارة كلية <?php echo htmlspecialchars($college_name ?: ''); ?> بأن الطالب / الطالبة المبين اسمه أدناه قد أنهى جميع التزاماته المادية والأكاديمية والإدارية المترتبة عليه، وأنه ليس عليه أي عهدة أو مستحقات للجهة.
 <?php elseif ($selected_doc === 'good_conduct'): ?>
 تشهد إدارة كلية <?php echo htmlspecialchars($college_name ?: ''); ?> بأن الطالب / الطالبة المبين اسمه أدناه لم يسبق إيقاعه أي جزاء تأديبي أو مخالفة سلوكية خلال فترة دراسته، وأنه يتمتع بسلوك حسن ومستقيم طوال مدة انتسابه.
 <?php endif; ?>
 </p>

 <!-- بيانات الطالب بالتفصيل -->
 <div class="doc-section">
 <div class="doc-field">
 <span class="doc-label">الاسم رباعياً :</span>
 <span class="doc-value" style="font-size:15px; font-weight:900;"><?php echo $student_name; ?></span>
 </div>
 <div class="doc-field">
 <span class="doc-label">رقم الطالب / القيد :</span>
 <span class="doc-value" style="font-family:monospace;"><?php echo $student_id; ?></span>
 </div>
 <div class="doc-field">
 <span class="doc-label">الرقم القومي :</span>
 <span class="doc-value" style="font-family:monospace;"><?php echo htmlspecialchars($national_id); ?></span>
 </div>
 <div class="doc-field">
 <span class="doc-label">التخصص / البرنامج :</span>
 <span class="doc-value"><?php echo htmlspecialchars($student_major); ?></span>
 </div>
 <div class="doc-field">
 <span class="doc-label">المستوى الدراسي :</span>
 <span class="doc-value">الفرقة <?php echo htmlspecialchars($student_level); ?></span>
 </div>
 <div class="doc-field">
 <span class="doc-label">سنة التحاق :</span>
 <span class="doc-value"><?php echo htmlspecialchars($enrollment_year); ?></span>
 </div>

 <?php if ($selected_doc === 'status_statement'): ?>
 <div class="doc-field">
 <span class="doc-label">المعدل التراكمي :</span>
 <span class="doc-value"><?php echo htmlspecialchars($student_gpa); ?> / 4.00</span>
 </div>
 <div class="doc-field">
 <span class="doc-label">حالة القيد :</span>
 <span class="doc-value"><?php echo htmlspecialchars($student_details['enrollment_status'] ?? 'مستمر'); ?></span>
 </div>

 <!-- جدول المواد والدرجات في بيان الحالة -->
 <?php if (!empty($enrolled_courses)): ?>
 <div style="margin-top:16px;">
 <div style="font-weight:800; font-size:13px; color:#2563eb; margin-bottom:8px; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">المقررات والدرجات المسجلة:</div>
 <table style="width:100%; border-collapse:collapse; font-size:12px;">
 <thead>
 <tr style="background:#eff6ff;">
 <th style="border:1px solid #ddd; padding:6px 10px; text-align:right;">#</th>
 <th style="border:1px solid #ddd; padding:6px 10px; text-align:right;">اسم المقرر</th>
 <th style="border:1px solid #ddd; padding:6px 10px; text-align:center;">الساعات</th>
 <th style="border:1px solid #ddd; padding:6px 10px; text-align:center;">التقدير</th>
 <th style="border:1px solid #ddd; padding:6px 10px; text-align:center;">النقاط</th>
 </tr>
 </thead>
 <tbody>
 <?php foreach ($enrolled_courses as $i => $course): ?>
 <tr style="<?php echo $i % 2 === 0 ? '' : 'background:#f8fafc'; ?>">
 <td style="border:1px solid #ddd; padding:5px 10px;"><?php echo $i + 1; ?></td>
 <td style="border:1px solid #ddd; padding:5px 10px;"><?php echo htmlspecialchars($course['name'] ?? ''); ?></td>
 <td style="border:1px solid #ddd; padding:5px 10px; text-align:center;"><?php echo htmlspecialchars($course['credit_hours'] ?? 3); ?></td>
 <td style="border:1px solid #ddd; padding:5px 10px; text-align:center; font-weight:700;"><?php echo htmlspecialchars($course['grade'] ?? '-'); ?></td>
 <td style="border:1px solid #ddd; padding:5px 10px; text-align:center;"><?php echo htmlspecialchars($course['points'] ?? '-'); ?></td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 <?php endif; ?>
 <?php endif; ?>
 </div>

 <!-- تنبيه تحت في الورقة -->
 <div class="doc-notice">
 <i class="fas fa-exclamation-circle" style="color:#ca8a04;"></i>
 هذه الشهادة صادرة بناءً على السجلات الأكاديمية المتاحة • صالحة لمدة ثلاثة أشهر من تاريخ الإصدار • رقم التسلسل: <strong style="font-family:monospace;"><?php echo htmlspecialchars($doc_serial); ?></strong>
 </div>

 <!-- الإمضاءات والختوم -->
 <div class="doc-footer" style="margin-top:48px;">
  <div class="doc-stamp">
   <span>تاريخ الإصدار: <?php echo $today; ?></span>
  </div>
  <div class="doc-sign">
   <div style="font-size:12px; font-weight:700; color:#334155;">مدير الكلية لشئون التعليم والطلاب</div>
   <div class="doc-sign-line"></div>
   <div style="font-size:11px; color:#94a3b8; margin-top:4px;">التوقيع والختم</div>
  </div>
  <div class="doc-sign">
   <div style="font-size:13px; font-weight:700; color:#334155;">عميد الكلية</div>
   <div class="doc-sign-line"></div>
   <div style="font-size:11px; color:#94a3b8; margin-top:4px;">التوقيع والختم</div>
  </div>
 </div>

</div>
<?php endif; /* end of non-graduation docs */ ?>

<?php if ($doc_key === 'graduation_certificate'): 
    // Fetch grad request data if not already passed
    if (!isset($grad_req)) {
        $grad_req = $db->find('graduation_certificate_requests', 'user_id', $selected_student['id']);
    }
?>
<!-- ===== قالب شهادة التخرج الرسمي ===== -->
<style>
.gc-outer{border:6px solid #2c5f2e;padding:5px;font-family:'Cairo','Arial',sans-serif;direction:rtl;max-width:760px;margin:0 auto;background:#fffef8;position:relative;}
.gc-inner{border:2px solid #2c5f2e;padding:20px 24px;position:relative;}
.gc-cn{position:absolute;font-size:18px;color:#2c5f2e;}
.gc-tl{top:5px;right:7px;}.gc-tr{top:5px;left:7px;}.gc-bl{bottom:5px;right:7px;}.gc-br{bottom:5px;left:7px;}
.gc-side{position:absolute;top:26px;bottom:26px;width:15px;background:repeating-linear-gradient(180deg,#2c5f2e 0,#2c5f2e 4px,transparent 4px,transparent 10px);opacity:.28;}
.gc-sr{right:2px;}.gc-sl{left:2px;}
.gc-hdr{display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid #2c5f2e;padding-bottom:10px;margin-bottom:12px;position:relative;}
.gc-logo{width:62px;height:62px;object-fit:contain;}
.gc-student-photo{width:90px;height:110px;border:1px solid #2c5f2e;background:#f8fafc;display:flex;align-items:center;justify-content:center;overflow:hidden;position:absolute;top:0;left:0;}
.gc-student-photo img{width:100%;height:100%;object-fit:cover;}
.gc-ctr{flex:1;text-align:center;}
.gc-min{font-size:10px;font-weight:700;color:#555;}
.gc-unv{font-size:15px;font-weight:900;color:#1a3a1a;margin:2px 0;}
.gc-clg{font-size:12px;font-weight:700;color:#2c5f2e;}
.gc-dpt{font-size:10px;color:#666;}
.gc-sn{font-size:10px;color:#888;direction:ltr;font-family:monospace;text-align:left;}
.gc-ttl-box{text-align:center;margin:10px 0 13px;}
.gc-ttl{display:inline-block;font-size:20px;font-weight:900;color:#1a1a1a;padding:5px 28px;border:2.5px solid #2c5f2e;background:#f5fbf5;letter-spacing:1px;}
.gc-row{display:flex;align-items:baseline;gap:5px;margin-bottom:6px;font-size:12px;line-height:1.8;border-bottom:1px dashed #ccc;padding-bottom:3px;}
.gc-lb{font-weight:800;color:#1a1a1a;white-space:nowrap;min-width:190px;}
.gc-vl{font-weight:600;color:#1a3a1a;flex:1;}
.gc-ln{flex:1;border-bottom:1px solid #555;min-width:50px;color:#2563eb;font-weight:700;}
.gc-nt{font-size:10.5px;color:#333;background:#f9f9f0;border:1px solid #d4c870;border-radius:4px;padding:7px 10px;margin:8px 0;line-height:1.8;}
.gc-sigs{display:flex;justify-content:space-between;margin-top:26px;gap:5px;}
.gc-sig{text-align:center;flex:1;}
.gc-st{font-size:10.5px;font-weight:800;color:#1a1a1a;min-height:28px;}
.gc-sc{width:58px;height:58px;border-radius:50%;border:2px solid #2c5f2e;display:flex;align-items:center;justify-content:center;margin:3px auto;font-size:7.5px;font-weight:700;color:#2c5f2e;text-align:center;opacity:.32;line-height:1.3;}
.gc-sl2{border-top:1.5px solid #334;margin:4px auto;width:90%;}
.gc-ss{font-size:9px;color:#888;}
.gc-ft{display:flex;justify-content:space-between;margin-top:10px;border-top:1.5px solid #2c5f2e;padding-top:6px;font-size:10px;color:#555;}
</style>
<div class="gc-outer">
<div class="gc-inner">
<span class="gc-cn gc-tl">✦</span><span class="gc-cn gc-tr">✦</span><span class="gc-cn gc-bl">✦</span><span class="gc-cn gc-br">✦</span>
<div class="gc-side gc-sr"></div><div class="gc-side gc-sl"></div>
<div class="gc-hdr">
 <img src="assets/images/logo.png" class="gc-logo" alt="شعار" onerror="this.style.display='none'">
 <div class="gc-ctr">
  <div class="gc-min">جمهورية مصر العربية — وزارة التعليم العالي والبحث العلمي</div>
  <div class="gc-unv"><?php echo htmlspecialchars($university_name); ?></div>
  <?php if ($college_name): ?><div class="gc-clg">كلية <?php echo htmlspecialchars($college_name); ?></div><?php endif; ?>
  <div class="gc-dpt">شعبة شئون الطلاب والتعليم</div>
 </div>
 <div class="gc-student-photo" id="preview_photo_container">
    <?php if (!empty($grad_req['photo_path'])): ?>
        <img src="<?php echo htmlspecialchars($grad_req['photo_path']); ?>" id="preview_student_photo">
    <?php else: ?>
        <i class="fas fa-user text-slate-200 text-4xl" id="preview_photo_icon"></i>
        <img src="" id="preview_student_photo" style="display:none;">
    <?php endif; ?>
 </div>
 <div class="gc-sn">رقم: <?php echo htmlspecialchars($doc_serial); ?><br><?php echo date('Y-m-d'); ?></div>
</div>
<div class="gc-ttl-box"><div class="gc-ttl">شـهـادة تـخـرج</div></div>
<div class="gc-row"><span class="gc-lb">تشهد الكلية بأن السيد / السيدة :</span><span class="gc-vl" style="font-size:13.5px;font-weight:900;"><?php echo htmlspecialchars($selected_student['full_name'] ?? ''); ?></span></div>
<div class="gc-row"><span class="gc-lb">رقم الطالب / القيد :</span><span class="gc-vl" style="font-family:monospace;"><?php echo htmlspecialchars($selected_student['username'] ?? ''); ?></span></div>
<div class="gc-row"><span class="gc-lb">الرقم القومي :</span><span class="gc-vl" style="font-family:monospace;"><?php echo htmlspecialchars($national_id); ?></span></div>
<div class="gc-row"><span class="gc-lb">التخصص / البرنامج الدراسي :</span><span class="gc-vl"><?php echo htmlspecialchars($student_major); ?></span></div>
<div class="gc-row"><span class="gc-lb">الفرقة الدراسية :</span><span class="gc-vl">الفرقة <?php echo htmlspecialchars($student_level); ?></span></div>
<div class="gc-row"><span class="gc-lb">سنة الالتحاق :</span><span class="gc-vl"><?php echo htmlspecialchars($enrollment_year); ?></span></div>
<div class="gc-row"><span class="gc-lb">المعدل التراكمي :</span><span class="gc-vl"><?php echo htmlspecialchars($student_gpa); ?> / 4.00</span></div>
<div class="gc-row"><span class="gc-lb">تاريخ موافقة مجلس الكلية :</span><span class="gc-ln" id="view_college_date"><?php echo htmlspecialchars($grad_req['college_council_date'] ?? ''); ?></span></div>
<div class="gc-row"><span class="gc-lb">تاريخ موافقة مجلس الجامعة :</span><span class="gc-ln" id="view_univ_date"><?php echo htmlspecialchars($grad_req['university_council_date'] ?? ''); ?></span></div>
<div class="gc-row"><span class="gc-lb">وتحررت هذه الشهادة لتقديمها إلى :</span><span class="gc-ln" id="view_recipient"><?php echo htmlspecialchars($grad_req['recipient'] ?? ''); ?></span></div>
<div class="gc-row">
    <span class="gc-lb">رسوم البراءة — القسيمة رقم :</span>
    <span class="gc-ln" id="view_clearance_receipt"><?php echo htmlspecialchars($grad_req['clearance_receipt'] ?? ''); ?></span>
    <span class="gc-lb" style="min-width:65px;margin-right:6px;">بتاريخ :</span>
    <span class="gc-ln" id="view_clearance_date"><?php echo htmlspecialchars($grad_req['clearance_receipt_date'] ?? ''); ?></span>
</div>
<div class="gc-row">
    <span class="gc-lb">رسوم الشهادة — القسيمة رقم :</span>
    <span class="gc-ln" id="view_cert_receipt"><?php echo htmlspecialchars($grad_req['certificate_receipt'] ?? ''); ?></span>
    <span class="gc-lb" style="min-width:65px;margin-right:6px;">بتاريخ :</span>
    <span class="gc-ln" id="view_cert_date"><?php echo htmlspecialchars($grad_req['certificate_receipt_date'] ?? ''); ?></span>
</div>
<div class="gc-nt">وعلى الجهة المقدَّم إليها هذه الشهادة التحقق من أن مقدِّمها هو صاحب الشهادة &nbsp;•&nbsp; هذه الشهادة صادرة بناءً على السجلات الأكاديمية &nbsp;•&nbsp; رقم التسلسل: <strong style="font-family:monospace;"><?php echo htmlspecialchars($doc_serial); ?></strong></div>
<div class="gc-sigs">
 <div class="gc-sig"><div class="gc-st">الموظف المختص</div><div class="gc-sc">ختم<br>رسمي</div><div class="gc-sl2"></div><div class="gc-ss">التوقيع</div></div>
 <div class="gc-sig"><div class="gc-st">مدير الإدارة</div><div class="gc-sc">ختم<br>رسمي</div><div class="gc-sl2"></div><div class="gc-ss">التوقيع</div></div>
 <div class="gc-sig"><div class="gc-st">مدير الكلية لشئون<br>التعليم والطلاب</div><div class="gc-sc">ختم<br>رسمي</div><div class="gc-sl2"></div><div class="gc-ss">التوقيع</div></div>
 <div class="gc-sig"><div class="gc-st">عميد الكلية</div><div class="gc-sc">ختم<br>عميد</div><div class="gc-sl2"></div><div style="font-size:9.5px;color:#444;">أ.د / <?php echo htmlspecialchars($college_name ?: ''); ?></div><div class="gc-ss">التوقيع والختم الرسمي</div></div>
</div>
<div class="gc-ft"><span>تحريراً في: <?php echo $today; ?></span><span style="color:#bbb;">منظومة EDU Nexus</span></div>
</div>
</div>
<?php endif; ?>

