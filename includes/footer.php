</main>
<!-- Footer -->
<footer class="footer pt-5 pb-4 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <h5 class="fw-bold mb-3"><?php echo SITE_NAME; ?></h5>
                <p>أفضل خدمة تأجير سيارات في المغرب. نوفر لك سيارات فاخرة بأسعار تنافسية.</p>
                <div class="social-icons mt-3">
                    <a href="#" class="btn btn-outline-light btn-sm rounded-circle"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm rounded-circle"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm rounded-circle"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm rounded-circle"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            <div class="col-lg-2 mb-4">
                <h5 class="fw-bold mb-3">روابط سريعة</h5>
                <ul class="list-unstyled">
                    <li><a href="<?php echo SITE_URL; ?>" class="text-white-50 text-decoration-none">الرئيسية</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/cars.php" class="text-white-50 text-decoration-none">السيارات</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/about.php" class="text-white-50 text-decoration-none">من نحن</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/contact.php" class="text-white-50 text-decoration-none">اتصل بنا</a></li>
                </ul>
            </div>
            <div class="col-lg-3 mb-4">
                <h5 class="fw-bold mb-3">معلومات الاتصال</h5>
                <ul class="list-unstyled">
                    <li><i class="fas fa-phone"></i> <a href="tel:+212600000000" class="text-white-50">+212 600-000000</a></li>
                    <li><i class="fas fa-envelope"></i> <a href="mailto:info@carrental.ma" class="text-white-50">info@carrental.ma</a></li>
                    <li><i class="fas fa-map-marker-alt"></i> الدار البيضاء، المغرب</li>
                </ul>
            </div>
            <div class="col-lg-3 mb-4">
                <h5 class="fw-bold mb-3">ساعات العمل</h5>
                <ul class="list-unstyled">
                    <li>الإثنين - الجمعة: 08:00 - 20:00</li>
                    <li>السبت: 09:00 - 18:00</li>
                    <li>الأحد: 10:00 - 16:00</li>
                </ul>
            </div>
        </div>
        <hr class="bg-white">
        <div class="text-center pt-3">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. جميع الحقوق محفوظة.</p>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
<?php if (isset($page_js) && file_exists(BASE_PATH . "/assets/js/{$page_js}")): ?>
    <script src="<?php echo ASSETS_URL; ?>/js/<?php echo $page_js; ?>"></script>
<?php endif; ?>

<script>
    // تهيئة الـ Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
    
    // رسائل الجلسة
    <?php if (isset($_SESSION['messages']) && !empty($_SESSION['messages'])): ?>
        <?php foreach ($_SESSION['messages'] as $msg): ?>
            Toastify({
                text: "<?php echo addslashes($msg['text']); ?>",
                duration: 5000,
                close: true,
                gravity: "top",
                position: "center",
                backgroundColor: "<?php echo $msg['type'] === 'success' ? '#28a745' : ($msg['type'] === 'error' ? '#dc3545' : '#17a2b8'); ?>",
                stopOnFocus: true
            }).showToast();
        <?php endforeach; ?>
        <?php unset($_SESSION['messages']); ?>
    <?php endif; ?>
</script>
</body>
</html>