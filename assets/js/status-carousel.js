class StatusCarousel {
    constructor() {
        this.carousel = document.getElementById('status-carousel');
        if (!this.carousel) return;
        
        this.itemsToShow = parseInt(this.carousel.dataset.items || 3);
        this.items = this.carousel.children;
        this.itemCount = this.items.length;
        this.currentIndex = 0;
        
        this.prevBtn = document.getElementById('prev-status');
        this.nextBtn = document.getElementById('next-status');
        this.loading = document.getElementById('status-carousel-loading');
        
        // Touch tracking variables
        this.touchStartX = 0;
        this.touchEndX = 0;
        this.minSwipeDistance = 50; // Minimum distance for a swipe to register
        
        // Auto slide variables
        this.autoSlideInterval = 10000; // 10 seconds
        this.autoSlideTimer = null;
        this.isUserInteracting = false;
        
        this.init();
    }
    
    init() {
        this.setItemWidths();
        this.loadContent();
        this.setupEventListeners();
    }
    
    setItemWidths() {
        if (!this.carousel) return;
        
        // Get number of items to show from data attribute
        const itemsToShow = parseInt(this.carousel.dataset.items || 3);
        
        // Get window width for responsive adjustment
        const windowWidth = window.innerWidth;
        
        // Choose number of items based on screen size
        let effectiveItems = itemsToShow;
        if (windowWidth < 640) {
            effectiveItems = 1; // Show 1 item on small mobile
        } else if (windowWidth < 768) {
            effectiveItems = 1.2; // Show slightly more than 1 item on larger mobile
        } else if (windowWidth < 1024) {
            effectiveItems = 2; // Show 2 items on tablet
        }
        
        // Calculate width percentage based on items to show with proper spacing
        const gapCompensation = windowWidth < 640 ? '0.5rem' : '1rem';
        const itemWidth = `calc(${100 / effectiveItems}% - ${gapCompensation})`;
        
        // Apply width to all carousel items
        const items = this.carousel.querySelectorAll('.status-carousel-item');
        items.forEach(item => {
            item.style.flex = `0 0 ${100 / effectiveItems}%`;
            item.style.maxWidth = itemWidth;
        });
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
                this.setItemWidths();
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
        this.carousel.innerHTML = jobs.map(job => {
            let deadlineHTML = '';
            if (job.deadline) {
                const deadline = new Date(job.deadline);
                const now = new Date();
                const timeDiff = deadline - now;
                const daysLeft = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));
                
                let colorClass = '';
                let deadlineText = '';
                
                if (timeDiff > 0) {
                    if (daysLeft <= 3) {
                        colorClass = 'bg-yellow-100 text-yellow-800';
                        deadlineText = `${daysLeft} hari lagi`;
                    } else {
                        colorClass = 'bg-green-100 text-green-800';
                        deadlineText = `${daysLeft} hari lagi`;
                    }
                } else {
                    colorClass = 'bg-red-100 text-red-800';
                    deadlineText = `Berakhir ${Math.abs(daysLeft)} hari lalu`;
                }
                
                deadlineHTML = `
                    <div class="absolute top-3 right-3 z-10">
                        <div class="flex items-center rounded-full px-2 py-1 text-xs ${colorClass} shadow-sm">
                            <i class="fas fa-clock mr-1"></i>
                            <span class="font-medium">${deadlineText}</span>
                        </div>
                    </div>
                `;
            }
            
            // Status badge code remains the same
            const statusHTML = `
                <div class="absolute top-3 left-3 z-10">
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium ${job.status.class} shadow-sm">
                        <i class="${job.status.icon} mr-1"></i>
                        ${job.status.label}
                    </span>
                </div>
            `;
            
            return `
                <div class="status-carousel-item">
                    <div class="relative bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200 p-4 md:p-5 h-full flex flex-col justify-between">
                        <!-- Badges Section -->
                        <div class="badges-container min-h-[35px] relative mb-2">
                            ${deadlineHTML}
                            
                            ${statusHTML}
                        </div>
                        
                        <!-- Visual Divider -->
                        <div class="border-b border-gray-100"></div>
                        
                        <!-- Job Title -->
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">
                            <a href="${job.permalink}" class="hover:text-blue-600 transition-colors">
                                ${job.title}
                            </a>
                        </h3>
        
                        <div class="mb-0">
                            <!-- Company name stays full width -->
                            <p class="flex items-center text-gray-600 mb-2">
                                <i class="fas fa-building mr-2 text-blue-600"></i>
                                <span class="font-bold">${job.company}</span>
                            </p>
                            
                            <!-- Flex container for location, education and experience -->
                            <div class="flex flex-wrap gap-x-4 gap-y-2">
                                <!-- Location -->
                                <p class="flex items-center text-gray-500">
                                    <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>
                                    ${job.location}
                                </p>
                                
                                <!-- Education when available -->
                                ${job.education ? `
                                <p class="flex items-center text-gray-500">
                                    <i class="fas fa-graduation-cap mr-2 text-blue-600"></i>
                                    ${job.education}
                                </p>
                                ` : ''}
                                
                                <!-- Experience when available -->
                                ${job.experience ? `
                                <p class="flex items-center text-gray-500">
                                    <i class="fas fa-history mr-2 text-blue-600"></i>
                                    ${job.experience} Tahun
                                </p>
                                ` : ''}
                            </div>
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
            `;
        }).join('');
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

        window.addEventListener('resize', () => {
            this.setItemWidths();
            this.updateCarousel();
        });
        
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