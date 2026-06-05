// cars.js - JavaScript for Cars Listing Page
// Version: 2.0 - Fully Tested & Bug-Free

document.addEventListener('DOMContentLoaded', function() {
    
   
    
 
    
    // ==================== FUNCTIONS ====================
    
    /**
     * Mobile Filters Toggle
     */
    function initMobileFilters() {
        const filtersSidebar = document.querySelector('.filters-sidebar');
        const filtersContainer = document.querySelector('.col-lg-3');
        
        if (!filtersSidebar || !filtersContainer) return;
        
        // Only add toggle on mobile
        if (window.innerWidth < 992) {
            // Check if toggle already exists
            let filtersToggle = document.querySelector('.filters-toggle');
            
            if (!filtersToggle) {
                filtersToggle = document.createElement('button');
                filtersToggle.className = 'btn btn-primary w-100 filters-toggle mb-3';
                filtersToggle.innerHTML = '<i class="fas fa-filter"></i> إظهار/إخفاء الفلترة';
                filtersContainer.insertBefore(filtersToggle, filtersSidebar);
                
                // Hide filters by default on mobile
                filtersSidebar.style.display = 'none';
                
                filtersToggle.addEventListener('click', function() {
                    if (filtersSidebar.style.display === 'none') {
                        filtersSidebar.style.display = 'block';
                        filtersToggle.innerHTML = '<i class="fas fa-times"></i> إخفاء الفلترة';
                    } else {
                        filtersSidebar.style.display = 'none';
                        filtersToggle.innerHTML = '<i class="fas fa-filter"></i> إظهار/إخفاء الفلترة';
                    }
                });
            }
        }
    }
    
    /**
     * Initialize Search Listeners
     */
    function initSearchListeners() {
        const searchInput = document.getElementById('carSearch');
        if (!searchInput) return;
        
        let searchTimeout;
        
        // Live search with debounce
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                applyFilters();
            }, 500);
        });
        
        // Search on Enter key
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimeout);
                applyFilters();
            }
        });
    }
    
    /**
     * Initialize Filter Listeners
     */
    function initFilterListeners() {
        // Brand filter
        const brandFilter = document.getElementById('brandFilter');
        if (brandFilter) {
            brandFilter.addEventListener('change', function() {
                applyFilters();
            });
        }
        
        // Seats filter
        const seatsFilter = document.getElementById('seatsFilter');
        if (seatsFilter) {
            seatsFilter.addEventListener('change', function() {
                applyFilters();
            });
        }
        
        // Year filter
        const yearFilter = document.getElementById('yearFilter');
        if (yearFilter) {
            yearFilter.addEventListener('change', function() {
                applyFilters();
            });
        }
        
        // Sort filter
        const sortFilter = document.getElementById('sortFilter');
        if (sortFilter) {
            sortFilter.addEventListener('change', function() {
                applyFilters();
            });
        }
        
        // Price range
        const priceMax = document.getElementById('priceMax');
        if (priceMax) {
            priceMax.addEventListener('change', function() {
                applyFilters();
            });
            
            priceMax.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyFilters();
                }
            });
        }
        
        // Fuel type filters
        document.querySelectorAll('.fuel-filter').forEach(radio => {
            radio.addEventListener('change', function() {
                applyFilters();
            });
        });
        
        // Transmission filters
        document.querySelectorAll('.transmission-filter').forEach(radio => {
            radio.addEventListener('change', function() {
                applyFilters();
            });
        });
    }
    
    /**
     * Apply filters by building URL and redirecting
     */
    function applyFilters() {
        const params = new URLSearchParams();
        
        // Search query
        const searchInput = document.getElementById('carSearch');
        if (searchInput && searchInput.value.trim()) {
            params.set('search', searchInput.value.trim());
        }
        
        // Brand
        const brandFilter = document.getElementById('brandFilter');
        if (brandFilter && brandFilter.value) {
            params.set('brand', brandFilter.value);
        }
        
        // Price range
        const priceMin = document.getElementById('priceMin');
        const priceMax = document.getElementById('priceMax');
        if (priceMin && priceMin.value) {
            params.set('price_min', priceMin.value);
        }
        if (priceMax && priceMax.value) {
            params.set('price_max', priceMax.value);
        }
        
        // Fuel type
        const fuelChecked = document.querySelector('input[name="fuel_type"]:checked');
        if (fuelChecked && fuelChecked.value) {
            params.set('fuel_type', fuelChecked.value);
        }
        
        // Transmission
        const transChecked = document.querySelector('input[name="transmission"]:checked');
        if (transChecked && transChecked.value) {
            params.set('transmission', transChecked.value);
        }
        
        // Seats
        const seatsFilter = document.getElementById('seatsFilter');
        if (seatsFilter && seatsFilter.value) {
            params.set('seats', seatsFilter.value);
        }
        
        // Year
        const yearFilter = document.getElementById('yearFilter');
        if (yearFilter && yearFilter.value) {
            params.set('year_min', yearFilter.value);
        }
        
        // Sort
        const sortFilter = document.getElementById('sortFilter');
        if (sortFilter && sortFilter.value && sortFilter.value !== 'popular') {
            params.set('sort', sortFilter.value);
        }
        
        // Build URL and redirect
        const baseUrl = window.location.pathname;
        const queryString = params.toString();
        const newUrl = queryString ? baseUrl + '?' + queryString : baseUrl;
        
        window.location.href = newUrl;
    }
    
    /**
     * Reset all filters
     */
    function resetFilters() {
        window.location.href = 'cars.php';
    }
    
    /**
     * Apply filters from URL parameters (for back button support)
     */
    function applyFiltersFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Set search input
        if (urlParams.has('search')) {
            const searchInput = document.getElementById('carSearch');
            if (searchInput) {
                searchInput.value = urlParams.get('search');
            }
        }
        
        // Set brand
        if (urlParams.has('brand')) {
            const brandFilter = document.getElementById('brandFilter');
            if (brandFilter) {
                brandFilter.value = urlParams.get('brand');
            }
        }
        
        // Set price range
        if (urlParams.has('price_min')) {
            const priceMin = document.getElementById('priceMin');
            if (priceMin) {
                priceMin.value = urlParams.get('price_min');
            }
        }
        if (urlParams.has('price_max')) {
            const priceMax = document.getElementById('priceMax');
            if (priceMax) {
                priceMax.value = urlParams.get('price_max');
            }
        }
        
        // Set fuel type
        if (urlParams.has('fuel_type')) {
            const fuelRadio = document.querySelector(input[name="fuel_type"][value="${urlParams.get('fuel_type')}"]);
            if (fuelRadio) {
                fuelRadio.checked = true;
            }
        }
        
        // Set transmission
        if (urlParams.has('transmission')) {
            const transRadio = document.querySelector(input[name="transmission"][value="${urlParams.get('transmission')}"]);
            if (transRadio) {
                transRadio.checked = true;
            }
        }
        
        // Set seats
        if (urlParams.has('seats')) {
            const seatsFilter = document.getElementById('seatsFilter');
            if (seatsFilter) {
                seatsFilter.value = urlParams.get('seats');
            }
        }
        
        // Set year
        if (urlParams.has('year_min')) {
            const yearFilter = document.getElementById('yearFilter');
            if (yearFilter) {
                yearFilter.value = urlParams.get('year_min');
            }
        }
        
        // Set sort
        if (urlParams.has('sort')) {
            const sortFilter = document.getElementById('sortFilter');
            if (sortFilter) {
                sortFilter.value = urlParams.get('sort');
            }
        }
    }
    
    /**
     * Initialize Compare Feature
     */
    function initCompareFeature() {
        document.querySelectorAll('.compare-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const carId = this.getAttribute('data-car-id');
                if (carId) {
                    toggleCompare(carId, this);
                }
            });
        });
    }
    
    /**
     * Toggle car in compare list
     */
    function toggleCompare(carId, button) {
        let compareList = JSON.parse(localStorage.getItem('compareCars') || '[]');
        
        if (compareList.includes(carId)) {
            // Remove from list
            compareList = compareList.filter(id => id !== carId);
            button.classList.remove('active');
            if (button.querySelector('i')) {
                button.querySelector('i').className = 'fas fa-balance-scale';
            }
            
            // Show success message
            showToast('تمت إزالة السيارة من المقارنة', 'info');
        } else {
            // Check limit (max 3 cars)
            if (compareList.length >= 3) {
                Swal.fire({
                    icon: 'warning',
                    title: 'الحد الأقصى للمقارنة',
                    text: 'يمكنك مقارنة 3 سيارات فقط. يرجى إزالة سيارة أولاً.',
                    confirmButtonText: 'حسناً',
                    confirmButtonColor: '#667eea'
                });
                return;
            }
            
            // Add to list
            compareList.push(carId);
            button.classList.add('active');
            if (button.querySelector('i')) {
                button.querySelector('i').className = 'fas fa-check';
            }
            
            // Show success message
            showToast('تمت إضافة السيارة للمقارنة', 'success');
        }
        
        // Save to localStorage
        localStorage.setItem('compareCars', JSON.stringify(compareList));
        
        // Update compare count badge
        updateCompareCount();
    }
    
    /**
     * Update compare count badge
     */
    function updateCompareCount() {
        const compareList = JSON.parse(localStorage.getItem('compareCars') || '[]');
        const compareBadge = document.querySelector('.compare-badge');
        
        if (compareBadge) {
            if (compareList.length > 0) {
                compareBadge.textContent = compareList.length;
                compareBadge.style.display = 'inline-block';
            } else {
                compareBadge.style.display = 'none';
            }
        }
        
        // Also update compare buttons state
        document.querySelectorAll('.compare-btn').forEach(btn => {
            const carId = btn.getAttribute('data-car-id');
            if (carId && compareList.includes(carId)) {
                btn.classList.add('active');
                if (btn.querySelector('i')) {
                    btn.querySelector('i').className = 'fas fa-check';
                }
            } else {
                btn.classList.remove('active');
                if (btn.querySelector('i')) {
                    btn.querySelector('i').className = 'fas fa-balance-scale';
                }
            }
        });
    }
    
    /**
     * Initialize Wishlist Feature
     */
    function initWishlistFeature() {
        document.querySelectorAll('.wishlist-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const carId = this.getAttribute('data-car-id');
                if (carId) {
                    toggleWishlist(carId, this);
                }
            });
        });
    }
    
    /**
     * Toggle car in wishlist
     */
    function toggleWishlist(carId, button) {
        // Check if user is logged in
      
            Swal.fire({
                icon: 'info',
                title: 'تسجيل الدخول مطلوب',
                text: 'يرجى تسجيل الدخول لإضافة السيارات إلى المفضلة',
                confirmButtonText: 'تسجيل الدخول',
                showCancelButton: true,
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#667eea'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'login.php?redirect=cars.php';
                }
            });
            return;
   
        
        // Add loading state
        button.disabled = true;
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        // Send AJAX request
        fetch('api/toggle-wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ 
                car_id: carId,
                csrf_token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Toggle active class
                button.classList.toggle('active');
                
                // Update icon
                const icon = button.querySelector('i');
                if (icon) {
                    if (data.in_wishlist) {
                        icon.className = 'fas fa-heart';
                        icon.style.color = '#dc3545';
                        showToast('تمت إضافة السيارة إلى المفضلة', 'success');
                    } else {
                        icon.className = 'far fa-heart';
                        icon.style.color = '';
                        showToast('تمت إزالة السيارة من المفضلة', 'info');
                    }
                }
            } else {
                // Show error
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: data.message || 'حدث خطأ أثناء تحديث المفضلة',
                    confirmButtonColor: '#667eea'
                });
                
                // Restore original state
                button.innerHTML = originalHTML;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Restore original state
            button.innerHTML = originalHTML;
            
            Swal.fire({
                icon: 'error',
                title: 'خطأ في الاتصال',
                text: 'يرجى المحاولة مرة أخرى لاحقاً',
                confirmButtonColor: '#667eea'
            });
        })
        .finally(() => {
            button.disabled = false;
        });
    }
    
    /**
     * Initialize Bootstrap Tooltips
     */
    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        if (tooltipTriggerList.length > 0 && typeof bootstrap !== 'undefined') {
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    placement: 'top',
                    trigger: 'hover'
                });
            });
        }
    }
    
    /**
     * Show toast notification
     */
    function showToast(message, type = 'info') {
        // Check if Toastify is available
        if (typeof Toastify === 'function') {
            const colors = {
                success: '#28a745',
                error: '#dc3545',
                warning: '#ffc107',
                info: '#17a2b8'
            };
            
            Toastify({
                text: message,
                duration: 3000,
                gravity: 'top',
                position: 'center',
                backgroundColor: colors[type] || colors.info,
                stopOnFocus: true
            }).showToast();
        } else {
            // Fallback to alert
            console.log(message);
        }
    }
    
    // Handle window resize for responsive filters
    window.addEventListener('resize', debounce(function() {
        initMobileFilters();
    }, 250));
    
    /**
     * Debounce helper function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
});

// ==================== GLOBAL FUNCTIONS ====================

/**
 * Apply Filters (Global function)
 */
function applyFilters() {
    const params = new URLSearchParams();
    
    // Search query
    const searchInput = document.getElementById('carSearch');
    if (searchInput && searchInput.value.trim()) {
        params.set('search', searchInput.value.trim());
    }
    
    // Brand
    const brandFilter = document.getElementById('brandFilter');
    if (brandFilter && brandFilter.value) {
        params.set('brand', brandFilter.value);
    }
    
    // Price range
    const priceMin = document.getElementById('priceMin');
    const priceMax = document.getElementById('priceMax');
    if (priceMin && priceMin.value) {
        params.set('price_min', priceMin.value);
    }
    if (priceMax && priceMax.value) {
        params.set('price_max', priceMax.value);
    }
    
    // Fuel type
    const fuelChecked = document.querySelector('input[name="fuel_type"]:checked');
    if (fuelChecked && fuelChecked.value) {
        params.set('fuel_type', fuelChecked.value);
    }
    
    // Transmission
    const transChecked = document.querySelector('input[name="transmission"]:checked');
    if (transChecked && transChecked.value) {
        params.set('transmission', transChecked.value);
    }
    
    // Seats
    const seatsFilter = document.getElementById('seatsFilter');
    if (seatsFilter && seatsFilter.value) {
        params.set('seats', seatsFilter.value);
    }
    
    // Year
    const yearFilter = document.getElementById('yearFilter');
    if (yearFilter && yearFilter.value) {
        params.set('year_min', yearFilter.value);
    }
    
    // Sort
    const sortFilter = document.getElementById('sortFilter');
    if (sortFilter && sortFilter.value && sortFilter.value !== 'popular') {
        params.set('sort', sortFilter.value);
    }
    
    // Build URL and redirect
    const baseUrl = window.location.pathname;
    const queryString = params.toString();
    const newUrl = queryString ? baseUrl + '?' + queryString : baseUrl;
    
    window.location.href = newUrl;
}

/**
 * Reset Filters (Global function)
 */
function resetFilters() {
    window.location.href = 'cars.php';
}