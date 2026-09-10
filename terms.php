<?php
/**
 * الشروط والأحكام
 */
require_once 'includes/config.php';
$page_title = 'الشروط والأحكام';
$page_description = 'شروط وأحكام استخدام خدمة تأجير السيارات';
include 'includes/header.php';
?>
<main>
<section style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:60px 0 40px;color:#fff;">
    <div class="container">
        <h1 style="font-weight:900;font-size:2.2rem;"><i class="fas fa-file-contract me-2"></i> الشروط والأحكام</h1>
        <p style="opacity:.85;">آخر تحديث: <?php echo date('d/m/Y'); ?></p>
    </div>
</section>

<section style="padding:50px 0;">
    <div class="container" style="max-width:900px;">
        <div style="background:#fff;border-radius:16px;box-shadow:0 5px 25px rgba(0,0,0,.06);padding:35px;line-height:2;">

            <h3 style="color:#667eea;font-weight:800;"><i class="fas fa-info-circle me-2"></i> 1. مقدمة</h3>
            <p>مرحباً بكم في <strong><?php echo SITE_NAME; ?></strong>. باستخدامكم لموقعنا وخدماتنا فإنكم توافقون على هذه الشروط والأحكام. يرجى قراءتها بعناية قبل إتمام أي حجز.</p>
            <hr>

            <h3 style="color:#667eea;font-weight:800;"><i class="fas fa-id-card me-2"></i> 2. شروط المستأجر</h3>
            <ul>
                <li>أن لا يقل عمر السائق عن <strong>21 سنة</strong> (25 سنة للفئات الفاخرة والرياضية).</li>
                <li>رخصة سياقة مغربية أو دولية <strong>سارية المفعول</strong> مضى على إصدارها سنة على الأقل.</li>
                <li>بطاقة تعريف وطنية أو جواز سفر ساري المفعول.</li>
                <li>وديعة الضمان تُسترجع كاملة عند إرجاع السيارة بحالتها الأصلية.</li>
            </ul>
            <hr>

            <h3 style="color:#667eea;font-weight:800;"><i class="fas fa-calendar-check me-2"></i> 3. الحجز والإلغاء</h3>
            <ul>
                <li>يُعتبر الحجز مؤكداً بعد إتمام عملية الدفع أو موافقة الإدارة.</li>
                <li>يمكن إلغاء الحجز <strong>مجاناً</strong> قبل 48 ساعة من موعد الاستلام.</li>
                <li>الإلغاء بعد هذه المدة قد يترتب عليه خصم يصل إلى قيمة يوم واحد من الإيجار.</li>
                <li>في حالة عدم الحضور دون إشعار، يحق للشركة إلغاء الحجز والاحتفاظ بالعربون.</li>
            </ul>
            <hr>

            <h3 style="color:#667eea;font-weight:800;"><i class="fas fa-money-bill-wave me-2"></i> 4. الأسعار والدفع</h3>
            <ul>
                <li>الأسعار معروضة بالدرهم المغربي (<?php echo CURRENCY_SYMBOL; ?>) وتشمل الضريبة (<?php echo TAX_RATE; ?>%) ما لم يُذكر خلاف ذلك.</li>
                <li>الوقود، الغرامات، والمخالفات المرورية على عاتق المستأجر.</li>
                <li>خصم 10% للحجوزات من 7 أيام فأكثر، وخصم 20% للحجوزات من 30 يوماً فأكثر.</li>
                <li>طرق الدفع المقبولة: التحويل البنكي، البطاقة البنكية، أو نقداً عند الاستلام.</li>
            </ul>
            <hr>

            <h3 style="color:#667eea;font-weight:800;"><i class="fas fa-road me-2"></i> 5. استخدام السيارة</h3>
            <ul>
                <li>يُمنع منعاً كلياً قيادة السيارة خارج التراب الوطني دون ترخيص كتابي مسبق.</li>
                <li>يُمنع تأجير السيارة من الباطن أو السماح لشخص غير مصرح به بقيادتها.</li>
                <li>يُمنع استخدام السيارة في السباقات، النقل المأجور، أو أي نشاط غير قانوني.</li>
                <li>تحديد الكيلومترات اليومي مذكور في تفاصيل كل سيارة، ويُحتسب مبلغ إضافي عن كل كيلومتر زائد.</li>
                <li>تُرجع السيارة بنفس مستوى الوقود عند الاستلام.</li>
            </ul>
            <hr>

            <h3 style="color:#667eea;font-weight:800;"><i class="fas fa-shield-alt me-2"></i> 6. التأمين والمسؤولية</h3>
            <ul>
                <li>جميع سياراتنا مؤمنة تأميناً شاملاً مع تحمل (Franchise) محدد في العقد.</li>
                <li>يمكن شراء خيار "التأمين بدون تحمل" من الإضافات الاختيارية.</li>
                <li>المستأجر مسؤول عن أي أضرار ناتجة عن الإهمال أو مخالفة شروط العقد.</li>
                <li>يجب إبلاغ الشركة والسلطات المختصة فور وقوع أي حادث.</li>
            </ul>
            <hr>

            <h3 style="color:#667eea;font-weight:800;"><i class="fas fa-clock me-2"></i> 7. الاستلام والتسليم</h3>
            <ul>
                <li>التأخر عن موعد التسليم بأكثر من ساعتين يُحتسب يوماً إضافياً كاملاً.</li>
                <li>يجب فحص السيارة مع الموظف عند الاستلام والتسليم وتوقيع محضر الحالة.</li>
                <li>أي تمديد للحجز يجب طلبه قبل انتهاء المدة الأصلية وموافق عليه من الإدارة.</li>
            </ul>
            <hr>

            <h3 style="color:#667eea;font-weight:800;"><i class="fas fa-user-shield me-2"></i> 8. حماية البيانات الشخصية</h3>
            <p>نلتزم بحماية بياناتكم الشخصية وعدم مشاركتها مع أي طرف ثالث إلا بموافقتكم أو بموجب القانون. تُستخدم بياناتكم فقط لمعالجة الحجوزات وتحسين خدماتنا.</p>
            <hr>

            <h3 style="color:#667eea;font-weight:800;"><i class="fas fa-gavel me-2"></i> 9. القانون المطبق</h3>
            <p>تخضع هذه الشروط للقانون المغربي، وأي نزاع يُعرض على محاكم مدينة الدار البيضاء.</p>

            <div style="margin-top:30px;padding:20px;background:#eef0ff;border-radius:12px;text-align:center;">
                <p class="mb-2"><strong>لأي استفسار حول هذه الشروط:</strong></p>
                <a href="contact.php" class="btn btn-primary"><i class="fas fa-envelope me-1"></i> اتصل بنا</a>
            </div>
        </div>
    </div>
</section>
</main>
<?php include 'includes/footer.php'; ?>
