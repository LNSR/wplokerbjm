document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on the search results page
    const searchResultsGrid = document.getElementById('search-results-grid');
    const loadingSpinner = document.getElementById('search-loading');
    
    if (!searchResultsGrid || !loadingSpinner) return;

    // Variables for infinite scroll
    let currentPage = 1;
    let isLoading = false;
    let hasMore = true;
    
    // Get current URL search params
    const urlSearchParams = new URLSearchParams(window.location.search);
    
    // Function to check if we should load more results
    function checkScroll() {
        if (isLoading || !hasMore) return;
        
        const scrollPosition = window.scrollY;
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight;
        
        // If we're near the bottom of the page
        if (scrollPosition + windowHeight > documentHeight - 500) {
            loadMoreResults();
        }
    }
    
    // Function to load more results
    function loadMoreResults() {
        isLoading = true;
        currentPage++;
        loadingSpinner.classList.remove('hidden');
        
        // Create form data from current URL parameters
        const formData = new FormData();
        formData.append('action', 'job_search_scroll');
        formData.append('_ajax_nonce', jobSearchScrollData.nonce);
        formData.append('paged', currentPage);
        
        // Add search parameters from URL
        for (const [key, value] of urlSearchParams.entries()) {
            formData.append(key, value);
        }
        
        fetch(jobSearchScrollData.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Append new items to the grid
                searchResultsGrid.insertAdjacentHTML('beforeend', data.data.html);
                
                // Update hasMore flag
                hasMore = data.data.hasMore;
                
                // Update search count
                const searchCount = document.getElementById('search-count');
                if (searchCount) {
                    const countSpan = searchCount.querySelector('span');
                    if (countSpan) {
                        countSpan.textContent = parseInt(countSpan.textContent || '0');
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error loading more results:', error);
        })
        .finally(() => {
            isLoading = false;
            loadingSpinner.classList.add('hidden');
        });
    }
    
    // Add scroll event listener
    window.addEventListener('scroll', checkScroll);
});