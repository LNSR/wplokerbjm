document.addEventListener('DOMContentLoaded', function() {
    const grid = document.getElementById('job-archive-grid');
    const loadMoreButton = document.querySelector('.load-more-button');
    const loading = document.getElementById('job-archive-loading');

    if (!grid || !loadMoreButton) return;
    
    let currentPage = parseInt(loadMoreButton.dataset.currentPage);
    const maxPages = parseInt(loadMoreButton.dataset.maxPages);

    const loadMoreJobs = () => {
        // Disable button while loading
        loadMoreButton.disabled = true;
        loading.classList.remove('hidden');
        
        // Get next page
        const nextPage = currentPage + 1;
        
        const formData = new FormData();
        formData.append('action', 'load_archive_jobs');
        formData.append('page', nextPage);
        formData.append('_ajax_nonce', archiveJobsData.nonce);
        
        // Add any current query parameters (for category archives, etc.)
        if (grid.dataset.queryVars) {
            formData.append('query_vars', grid.dataset.queryVars);
        }

        fetch(archiveJobsData.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Append new content
                grid.insertAdjacentHTML('beforeend', data.data.html);
                
                // Update current page
                currentPage = nextPage;
                loadMoreButton.dataset.currentPage = currentPage;
                
                // Hide button if we've reached the last page
                if (currentPage >= maxPages) {
                    loadMoreButton.parentElement.style.display = 'none';
                }
            }
        })
        .finally(() => {
            loading.classList.add('hidden');
            loadMoreButton.disabled = false;
        });
    };

    // Add click handler to load more button
    loadMoreButton.addEventListener('click', loadMoreJobs);
});