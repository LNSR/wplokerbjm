<section class="job-status-section mb-12">
    <div class="max-w-7xl mx-auto">
        <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Direkomendasikan</h2>
        
        <!-- Carousel Container -->
        <div class="relative">
            <!-- Carousel Navigation -->
            <button id="prev-status" class="hidden md:block absolute left-0 top-1/2 -translate-y-1/2 -translate-x-5 z-10 bg-white rounded-full p-2 shadow-lg hover:bg-gray-50">
                <i class="fas fa-chevron-left text-gray-600"></i>
            </button>
            
            <button id="next-status" class="hidden md:block absolute right-0 top-1/2 -translate-y-1/2 translate-x-5 z-10 bg-white rounded-full p-2 shadow-lg hover:bg-gray-50">
                <i class="fas fa-chevron-right text-gray-600"></i>
            </button>

            <!-- Carousel Content -->
            <div class="overflow-hidden">
                <div id="status-carousel" class="flex gap-6 transition-transform duration-300 px-2">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
        
        <!-- Loading State -->
        <div id="status-carousel-loading" class="text-center py-8 hidden">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        </div>
    </div>
</section>