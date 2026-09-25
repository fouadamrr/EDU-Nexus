<?php
// هيدر رسمي بينفع لكل التقارير اللي بنطبعها من الموقع
// المفروض نكون منشنين على اللوجو والمتغيرات التانية في الملف اللي بيناديله
?>
<div class="report-official-header no-print-background">
    <div style="text-align: right; min-width: 180px;">
        <div class="text-[14px] font-black uppercase">جامعة EDU Nexus</div>
        <div class="text-[12px] font-bold">بوابة الخدمات الإدارية</div>
        <div class="text-[11px] font-bold text-slate-500">شئون الطلاب والتسجيل</div>
    </div>
    <div style="text-align: center; flex: 1;">
        <div class="header-logo-box mx-auto">
            <?php if (!empty($logo_b64)): ?>
                <img src="<?php echo $logo_b64; ?>" alt="Logo" style="width:100%; height:100%; object-fit:contain;">
            <?php else: ?>
                <i class="fas fa-university text-2xl text-slate-300"></i>
            <?php endif; ?>
        </div>
        <h2 class="text-[16px] font-black"><?php echo $report_title; ?></h2>
        <?php if (isset($sub_title)): ?>
            <div class="text-[11px] font-bold text-slate-600"><?php echo htmlspecialchars($sub_title); ?></div>
        <?php endif; ?>
    </div>
    <div style="text-align: left; min-width: 180px; font-size: 10px;">
        <div>تاريخ الاستخراج: <?php echo date('Y/m/d'); ?></div>
        <?php if (isset($count_label) && isset($count_value)): ?>
            <div class="mt-1 font-black"><?php echo $count_label; ?>: <?php echo $count_value; ?></div>
        <?php endif; ?>
        <div class="mt-4 text-[9px] text-slate-400">وثيقة رسمية معتمدة من EDU Nexus</div>
    </div>
</div>
