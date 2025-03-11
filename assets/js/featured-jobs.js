document.addEventListener('DOMContentLoaded', function() {
    const grid = document.getElementById('featured-jobs-grid');
    const pagination = document.getElementById('featured-jobs-pagination');
    const loading = document.getElementById('featured-jobs-loading');

    if (!grid || !pagination) return;

    const loadJobs = (page = 1, append = false) => {
        loading.classList.remove('hidden');
        
        // Get filter options from data attribute
        const filterOptions = JSON.parse(grid.dataset.filters || '{}');

        const formData = new FormData();
        formData.append('action', 'load_featured_jobs');
        formData.append('page', page);
        formData.append('_ajax_nonce', featuredJobsData.nonce);
        formData.append('filters', JSON.stringify(filterOptions)); // Add filters to request

        fetch(featuredJobsData.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update content
                if (!append) {
                    grid.innerHTML = data.data.html;
                }

                // Update pagination buttons
                const buttons = pagination.querySelectorAll('button');
                buttons.forEach(button => {
                    const buttonPage = parseInt(button.dataset.page);
                    const isCurrent = buttonPage === page;
                    
                    button.className = `page-number px-4 py-2 rounded-lg ${
                        isCurrent ? 
                        'bg-blue-600 text-white' : 
                        'bg-white text-blue-600 hover:bg-blue-50'
                    } border border-blue-200 transition-colors`;
                });

                // Smooth scroll if needed
                if (page > 1) {
                    grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        })
        .catch(error => {
            console.error('Error loading jobs:', error);
            grid.innerHTML = `
                <div class="col-span-full text-center p-8 bg-red-50 rounded-lg">
                    <p class="text-red-600">Maaf, terjadi kesalahan. Silakan coba lagi.</p>
                </div>
            `;
        })
        .finally(() => {
            loading.classList.add('hidden');
        });
    };

    // Add click handlers to pagination buttons
    pagination.addEventListener('click', (e) => {
        const button = e.target.closest('button');
        if (button && button.classList.contains('page-number')) {
            const page = parseInt(button.dataset.page);
            loadJobs(page, false);
        }
    });
});
