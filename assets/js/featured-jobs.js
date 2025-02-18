document.addEventListener('DOMContentLoaded', function() {
    // Get the elements
    const loadMoreButton = document.getElementById('featured-jobs-load-more');
    const featuredJobsGrid = document.getElementById('featured-jobs-grid');
    const loadingSpinner = document.getElementById('featured-jobs-loading');
    
    // If elements don't exist, exit early
    if (!loadMoreButton || !featuredJobsGrid || !loadingSpinner) {
        return;
    }
    
    // Set up variables for tracking state
    let currentPage = parseInt(loadMoreButton.getAttribute('data-page') || '1');
    let maxPages = parseInt(loadMoreButton.getAttribute('data-max-pages') || '1');
    let isLoading = false;
    
    // Get filters and per page values
    const filters = JSON.parse(featuredJobsGrid.getAttribute('data-filters') || '{}');
    const perPage = featuredJobsGrid.getAttribute('data-per-page') || '10';
    
    // Add click event listener to the load more button
    loadMoreButton.addEventListener('click', function() {
        loadMoreFeaturedJobs();
    });
    
    // Function to load more jobs
    function loadMoreFeaturedJobs() {
        // If already loading or reached max pages, exit
        if (isLoading || currentPage >= maxPages) {
            return;
        }
        
        // Set loading state
        isLoading = true;
        loadMoreButton.disabled = true;
        loadingSpinner.classList.remove('hidden');
        
        // Create form data for AJAX request
        const formData = new FormData();
        formData.append('action', 'load_featured_jobs');
        formData.append('_ajax_nonce', featuredJobsData.nonce);
        formData.append('page', ++currentPage);
        formData.append('filters', JSON.stringify(filters));
        
        // Send AJAX request
        fetch(featuredJobsData.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Append new items to the grid
                featuredJobsGrid.insertAdjacentHTML('beforeend', data.data.html);
                
                // Update variables
                currentPage = data.data.currentPage;
                
                // If no more pages, hide the button
                if (currentPage >= data.data.maxPages) {
                    loadMoreButton.classList.add('hidden');
                }
            } else {
                console.error('Error loading more jobs:', data.data?.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
        })
        .finally(() => {
            // Reset loading state
            isLoading = false;
            loadMoreButton.disabled = false;
            loadingSpinner.classList.add('hidden');
        });
    }
});
