// home.js - JavaScript for Homepage

document.addEventListener('DOMContentLoaded', function() {
    
    // Preloader
    window.addEventListener('load', function() {
        const preloader = document.querySelector('.preloader');
        if (preloader) {
            preloader.style.opacity = '0';
            preloader.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                preloader.style.display = 'none';
            }, 500);
        }
    });
    
    // Initialize Swiper for Testimonials
    if (document.querySelector('.testimonialSwiper')) {
        new Swiper('.testimonialSwiper', {
            slidesPerView: 1,
            spaceBetween: 30,
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            breakpoints: {
                768: {
                    slidesPerView: 2,
                },
                992: {
                    slidesPerView: 3,
                }
            }
        });
    }
    
    // Initialize Swiper for Brands
    if (document.querySelector('.brandsSwiper')) {
        new Swiper('.brandsSwiper', {
            slidesPerView: 2,
            spaceBetween: 30,
            loop: true,
            autoplay: {
                delay: 2000,
                disableOnInteraction: false,
            },
            breakpoints: {
                576: {
                    slidesPerView: 3,
                },
                768: {
                    slidesPerView: 4,
                },
                992: {
                    slidesPerView: 6,
                }
            }
        });
    }
    
    // Initialize DatePickers
    if (document.querySelector('.datepicker')) {
        flatpickr(".datepicker", {
            locale: "ar",
            dateFormat: "Y-m-d",
            minDate: "today",
            disableMobile: "true"
        });
    }
    
    // Back to Top Button
    const backToTop = document.getElementById('backToTop');
    if (backToTop) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 500) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });
        
        backToTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // Smooth Scroll for Links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Counter Animation
    const counters = document.querySelectorAll('.stat-item h4');
    const speed = 200;
    
    function animateCounter(counter) {
        const target = parseInt(counter.textContent.replace(/[^0-9]/g, ''));
        let count = 0;
        const increment = target / speed;
        
        const updateCount = () => {
            if (count < target) {
                count += increment;
                counter.textContent = '+' + Math.ceil(count).toLocaleString();
                requestAnimationFrame(updateCount);
            } else {
                counter.textContent = '+' + target.toLocaleString();
            }
        };
        
        updateCount();
    }
    
    // Intersection Observer for counters
    if (window.IntersectionObserver) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    counters.forEach(counter => animateCounter(counter));
                    observer.disconnect();
                }
            });
        });
        
        const statsSection = document.querySelector('.hero-stats');
        if (statsSection) {
            observer.observe(statsSection);
        }
    }
    
    // WOW Animation
    if (typeof WOW !== 'undefined') {
        new WOW().init();
    }
    
    // Parallax Effect on Hero
    window.addEventListener('scroll', function() {
        const hero = document.querySelector('.hero-section');
        if (hero) {
            const scroll = window.scrollY;
            hero.style.backgroundPositionY = scroll * 0.5 + 'px';
        }
    });
    
    // Newsletter Form
    document.getElementById('newsletterForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const email = this.querySelector('input[type="email"]').value;
        
        // Here you would typically send this to your backend
        Swal.fire({
            icon: 'success',
            title: 'تم الاشتراك بنجاح!',
            text: 'شكراً لاشتراكك في نشرتنا البريدية',
            confirmButtonText: 'موافق',
            confirmButtonColor: '#667eea'
        });
        
        this.reset();
    });
    
    // Quick Booking Form Validation
    document.getElementById('quickBookingForm')?.addEventListener('submit', function(e) {
        const pickupDate = this.querySelector('[name="pickup_date"]').value;
        const returnDate = this.querySelector('[name="return_date"]').value;
        
        if (pickupDate && returnDate && new Date(returnDate) <= new Date(pickupDate)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'خطأ في التواريخ',
                text: 'يجب أن يكون تاريخ التسليم بعد تاريخ الاستلام',
                confirmButtonText: 'حسناً'
            });
        }
    });
    
    // GSAP Animations
    if (typeof gsap !== 'undefined') {
        // Hero Content Animation
        gsap.from('.hero-content h1', {
            duration: 1,
            y: 50,
            opacity: 0,
            ease: 'power3.out'
        });
        
        gsap.from('.hero-content p', {
            duration: 1,
            y: 30,
            opacity: 0,
            delay: 0.3,
            ease: 'power3.out'
        });
        
        // Feature Cards Animation
        gsap.from('.feature-card', {
            scrollTrigger: {
                trigger: '.features-section',
                start: 'top center'
            },
            duration: 0.8,
            y: 50,
            opacity: 0,
            stagger: 0.2,
            ease: 'power3.out'
        });
    }
});

// Handle Quick Search
function quickSearch() {
    const form = document.getElementById('quickBookingForm');
    if (form.checkValidity()) {
        form.submit();
    } else {
        form.reportValidity();
    }
}