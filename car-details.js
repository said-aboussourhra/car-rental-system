// car-details.js - JavaScript for Car Details Page

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize Datepickers
    if (document.querySelector('.datepicker')) {
        flatpickr(".datepicker", {
            locale: "ar",
            dateFormat: "Y-m-d",
            minDate: "today",
            disableMobile: "true",
            onChange: function(selectedDates, dateStr, instance) {
                // Update price calculation if both dates selected
                calculatePrice();
            }
        });
    }
    
    // Initialize Swiper for thumbnails if many images
    if (document.querySelectorAll('.thumbnail-item').length > 4) {
        new Swiper('.thumbnails-container', {
            slidesPerView: 4,
            spaceBetween: 10,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            }
        });
    }
    
    // Activate first tab from URL hash
    const hash = window.location.hash;
    if (hash) {
   
        if (tab) {
            new bootstrap.Tab(tab).show();
        }
    }
    
    // Update hash on tab change
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            window.location.hash = e.target.dataset.bsTarget;
        });
    });
    
    // Price Calculator
    calculatePrice();
    
    // Review Form Submission
    document.getElementById('reviewForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        submitReview(this);
    });
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Sticky sidebar scroll
    window.addEventListener('scroll', handleStickyScroll);
    
    // Animate rating distribution on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                document.querySelectorAll('.rating-distribution .progress-bar').forEach(bar => {
                    const width = bar.style.width;
                    bar.style.width = '0%';
                    setTimeout(() => {
                        bar.style.width = width;
                    }, 100);
                });
                observer.disconnect();
            }
        });
    });
    
    const ratingDist = document.querySelector('.rating-distribution');
    if (ratingDist) {
        observer.observe(ratingDist);
    }
});

// Image Gallery Functions
let currentImageIndex = 0;
const totalImages = document.querySelectorAll('.thumbnail-item').length;

function setMainImage(index, imageSrc) {
    currentImageIndex = index;
    const mainImage = document.getElementById('mainImage');
    
    // Fade out
    mainImage.style.opacity = '0';
    
    setTimeout(() => {
        mainImage.src = imageSrc;
        // Fade in
        mainImage.style.opacity = '1';
    }, 200);
    
    // Update active thumbnail
    document.querySelectorAll('.thumbnail-item').forEach((thumb, i) => {
        thumb.classList.toggle('active', i === index);
    });
}

function changeImage(direction) {
    let newIndex = currentImageIndex + direction;
    
    if (newIndex < 0) {
        newIndex = totalImages - 1;
    } else if (newIndex >= totalImages) {
        newIndex = 0;
    }
    
    const thumbnail = document.querySelectorAll('.thumbnail-item')[newIndex];
    if (thumbnail) {
        thumbnail.click();
    }
}

// Lightbox Functions
let lightboxImages = [];
let lightboxCurrentIndex = 0;

function openLightbox() {
    const modal = document.getElementById('lightboxModal');
    const lightboxImage = document.getElementById('lightboxImage');
    
    // Collect all car images
    lightboxImages = Array.from(document.querySelectorAll('.thumbnail-item img')).map(img => img.src);
    lightboxCurrentIndex = currentImageIndex;
    
    lightboxImage.src = lightboxImages[lightboxCurrentIndex];
    modal.classList.add('active');
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    const modal = document.getElementById('lightboxModal');
    modal.classList.remove('active');
    
    // Enable body scroll
    document.body.style.overflow = '';
}

function changeLightboxImage(direction) {
    lightboxCurrentIndex += direction;
    
    if (lightboxCurrentIndex < 0) {
        lightboxCurrentIndex = lightboxImages.length - 1;
    } else if (lightboxCurrentIndex >= lightboxImages.length) {
        lightboxCurrentIndex = 0;
    }
    
    const lightboxImage = document.getElementById('lightboxImage');
    lightboxImage.style.opacity = '0';
    
    setTimeout(() => {
        lightboxImage.src = lightboxImages[lightboxCurrentIndex];
        lightboxImage.style.opacity = '1';
    }, 200);
}

// Keyboard navigation for lightbox
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('lightboxModal');
    if (modal.classList.contains('active')) {
        if (e.key === 'Escape') {
            closeLightbox();
        } else if (e.key === 'ArrowLeft') {
            changeLightboxImage(-1);
        } else if (e.key === 'ArrowRight') {
            changeLightboxImage(1);
        }
    }
});

// Toggle Favorite
function toggleFavorite(carId) {
  
    
    const button = document.querySelector('.favorite-btn');
    
    fetch('api/toggle-wishlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ car_id: carId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.classList.toggle('active');
            const icon = button.querySelector('i');
            if (button.classList.contains('active')) {
                icon.classList.replace('far', 'fas');
                Swal.fire({
                    icon: 'success',
                    title: 'تمت الإضافة',
                    text: 'تمت إضافة السيارة إلى المفضلة',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                icon.classList.replace('fas', 'far');
                Swal.fire({
                    icon: 'info',
                    title: 'تمت الإزالة',
                    text: 'تمت إزالة السيارة من المفضلة',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: 'حدث خطأ أثناء تحديث المفضلة'
        });
    });
}

// Calculate Price based on dates
function calculatePrice() {
    const pickupDate = document.querySelector('[name="pickup_date"]')?.value;
    const returnDate = document.querySelector('[name="return_date"]')?.value;
   
    
    if (pickupDate && returnDate) {
        const start = new Date(pickupDate);
        const end = new Date(returnDate);
        const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
        
        if (days > 0) {
            const total = days * dailyRate;
            // Update estimated price display if exists
            const priceDisplay = document.getElementById('estimatedPrice');
            if (priceDisplay) {
                priceDisplay.textContent = total.toLocaleString() + ' درهم';
            }
        }
    }
}

// Submit Review
function submitReview(form) {
    const formData = new FormData(form);
    
    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'تم إرسال التقييم',
                text: 'شكراً لتقييمك! سيتم نشره بعد المراجعة.',
                confirmButtonText: 'حسناً'
            }).then(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('addReviewModal'));
                modal.hide();
                form.reset();
                // Reload reviews section
                setTimeout(() => location.reload(), 1500);
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: data.message || 'حدث خطأ أثناء إرسال التقييم'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'خطأ في الاتصال',
            text: 'يرجى المحاولة مرة أخرى'
        });
    });
}

// Handle Sticky Scroll
function handleStickyScroll() {
    const sidebar = document.querySelector('.car-info-card');
    const footer = document.querySelector('.footer');
    
    if (sidebar && footer && window.innerWidth > 992) {
        const sidebarRect = sidebar.getBoundingClientRect();
        const footerRect = footer.getBoundingClientRect();
        
        if (sidebarRect.bottom >= footerRect.top) {
            sidebar.style.position = 'relative';
            sidebar.style.top = 'auto';
        }
    }
}

// Share buttons
function shareOnWhatsApp() {
    const url = window.location.href;
   
}

function shareOnFacebook() {
   
}

function shareOnTwitter() {
    const url = window.location.href;
   
}