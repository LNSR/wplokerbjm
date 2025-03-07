class StatusCarousel {
    constructor() {
        this.carousel = document.getElementById('status-carousel');
        this.prevBtn = document.getElementById('prev-status');
        this.nextBtn = document.getElementById('next-status');
        this.loading = document.getElementById('status-carousel-loading');
        this.currentIndex = 0;
        this.slides = [];
        
        // Touch tracking variables
        this.touchStartX = 0;
        this.touchEndX = 0;
        this.minSwipeDistance = 50; // Minimum distance for a swipe to register
        
        // Auto slide variables
        this.autoSlideInterval = 15000; // 15 seconds
        this.autoSlideTimer = null;
        this.isUserInteracting = false;
        
        this.init();
    }
    
    init() {
        this.loadContent();
        this.setupEventListeners();
    }
    
    loadContent() {
        this.loading.classList.remove('hidden');
        
        const formData = new FormData();
        formData.append('action', 'load_status_carousel');
        formData.append('_ajax_nonce', statusCarouselData.nonce);
        
        fetch(statusCarouselData.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.renderSlides(data.data);
                this.slides = this.carousel.children;
                this.updateCarousel();
                this.startAutoSlide(); // Start auto slide after content loads
            }
        })
        .catch(error => {
            console.error('Error loading carousel:', error);
        })
        .finally(() => {
            this.loading.classList.add('hidden');
        });
    }
    
    renderSlides(jobs) {
        this.carousel.innerHTML = jobs.map(job => `
            <div class="flex w-full md:w-3/5 lg:w-2/5">
                <div class="relative bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200 p-4 md:p-5 h-full flex flex-col justify-between">
                    <!-- Status badge moved above the title -->
                    <div class="mb-3">
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium ${job.status.class}">
                            <i class="${job.status.icon} mr-1"></i>
                            ${job.status.label}
                        </span>
                    </div>

                    <h3 class="text-xl font-semibold text-gray-900 mb-4">
                        <a href="${job.permalink}" class="hover:text-blue-600 transition-colors">
                            ${job.title}
                        </a>
                    </h3>

                    <div class="space-y-2">
                        <p class="text-gray-600 font-bold">${job.company}</p>
                        <p class="flex items-center text-gray-500">
                            <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>
                            ${job.location}
                        </p>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <a href="${job.permalink}" 
                           class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-700">
                            Lihat Detail
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    updateCarousel() {
        if (!this.slides.length) return;
        
        const slideWidth = this.slides[0].offsetWidth;
        this.carousel.style.transform = `translateX(-${this.currentIndex * slideWidth}px)`;
        
        this.prevBtn.disabled = this.currentIndex === 0;
        this.nextBtn.disabled = this.currentIndex >= this.slides.length - 1;
        
        this.prevBtn.style.opacity = this.prevBtn.disabled ? '0.5' : '1';
        this.nextBtn.style.opacity = this.nextBtn.disabled ? '0.5' : '1';
    }
    
    setupEventListeners() {
        if (this.prevBtn) {
            this.prevBtn.addEventListener('click', () => {
                this.pauseAutoSlide();
                this.navigatePrev();
                this.resumeAutoSlide();
            });
        }
        
        if (this.nextBtn) {
            this.nextBtn.addEventListener('click', () => {
                this.pauseAutoSlide();
                this.navigateNext();
                this.resumeAutoSlide();
            });
        }
        
        // Add touch event listeners for swipe functionality
        if (this.carousel) {
            this.carousel.addEventListener('touchstart', (e) => {
                this.pauseAutoSlide();
                this.handleTouchStart(e);
            }, { passive: true });
            
            this.carousel.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: true });
            
            this.carousel.addEventListener('touchend', () => {
                this.handleTouchEnd();
                this.resumeAutoSlide();
            }, { passive: true });
            
            // Pause auto slide when hovering over carousel
            this.carousel.addEventListener('mouseenter', () => this.pauseAutoSlide());
            this.carousel.addEventListener('mouseleave', () => this.resumeAutoSlide());
        }

        window.addEventListener('resize', () => this.updateCarousel());
        
        // Handle page visibility changes to pause/resume auto slide
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseAutoSlide();
            } else {
                this.resumeAutoSlide();
            }
        });
    }

    // Auto slide methods
    startAutoSlide() {
        this.autoSlideTimer = setInterval(() => {
            if (!this.isUserInteracting) {
                if (this.currentIndex >= this.slides.length - 1) {
                    // Reset to first slide when reaching the end
                    this.currentIndex = 0;
                } else {
                    this.currentIndex++;
                }
                this.updateCarousel();
            }
        }, this.autoSlideInterval);
    }
    
    pauseAutoSlide() {
        this.isUserInteracting = true;
        clearInterval(this.autoSlideTimer);
    }
    
    resumeAutoSlide() {
        // Add a small delay before resuming
        setTimeout(() => {
            this.isUserInteracting = false;
            this.startAutoSlide();
        }, 1000);
    }

    // Touch event handlers
    handleTouchStart(e) {
        this.touchStartX = e.touches[0].clientX;
    }
    
    handleTouchMove(e) {
        this.touchEndX = e.touches[0].clientX;
    }
    
    handleTouchEnd() {
        const swipeDistance = this.touchEndX - this.touchStartX;
        
        // Check if the swipe was significant enough
        if (Math.abs(swipeDistance) > this.minSwipeDistance) {
            if (swipeDistance > 0) {
                // Swipe right - go to previous slide
                this.navigatePrev();
            } else {
                // Swipe left - go to next slide
                this.navigateNext();
            }
        }
        
        // Reset touch tracking
        this.touchStartX = 0;
        this.touchEndX = 0;
    }
    
    // Navigation methods
    navigatePrev() {
        if (this.currentIndex > 0) {
            this.currentIndex--;
            this.updateCarousel();
        }
    }
    
    navigateNext() {
        if (this.currentIndex < this.slides.length - 1) {
            this.currentIndex++;
            this.updateCarousel();
        }
    }
}

document.addEventListener('DOMContentLoaded', () => new StatusCarousel());