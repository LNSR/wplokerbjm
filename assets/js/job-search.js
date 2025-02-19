document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('job-search-form');
    const resultsContainer = document.getElementById('search-results');
    const submitButton = document.getElementById('search-submit');
    const loadingSpinner = document.getElementById('search-loading');
    const featuredSection = document.querySelector('.featured-jobs-section'); // Add class to your Featured Jobs section

    if (searchForm) {
        const performSearch = (e, page = 1) => {
            if (e) e.preventDefault();
            
            // Get all form values
            const formData = new FormData(searchForm);
            formData.append('page', page);
            
            const hasSearchCriteria = Array.from(formData.values()).some(value => 
                value !== '' && value !== 'search_jobs' && value !== page.toString()
            );
            
            // Show/hide sections based on search state
            if (!hasSearchCriteria) {
                resultsContainer.classList.add('hidden');
                featuredSection?.classList.remove('hidden');
                return;
            }

            // Show loading state
            const buttonText = submitButton.querySelector('span');
            buttonText.classList.add('hidden');
            loadingSpinner.classList.remove('hidden');
            submitButton.disabled = true;

            // Hide featured section and show results
            featuredSection?.classList.add('hidden');
            resultsContainer.classList.remove('hidden');

            formData.append('_ajax_nonce', jobSearchData.nonce);
            formData.append('action', 'search_jobs');

            // Make AJAX request
            fetch(jobSearchData.ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const resultsGrid = resultsContainer.querySelector('.search-results-grid');
                    resultsGrid.innerHTML = data.data.html;
                    
                    // Add click handlers to pagination buttons
                    resultsGrid.querySelectorAll('.page-number').forEach(button => {
                        button.addEventListener('click', (e) => {
                            const pageNum = parseInt(e.target.dataset.page);
                            performSearch(null, pageNum);
                            
                            // Smooth scroll to results
                            resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                    });

                    if (hasSearchCriteria) {
                        resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                } else {
                    throw new Error(data.data.message || 'Search failed');
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                resultsContainer.querySelector('.search-results-grid').innerHTML = `
                    <div class="text-center p-8 bg-red-50 rounded-lg">
                        <p class="text-red-600">Maaf, terjadi kesalahan. Silakan coba lagi.</p>
                    </div>
                `;
            })
            .finally(() => {
                buttonText.classList.remove('hidden');
                loadingSpinner.classList.add('hidden');
                submitButton.disabled = false;
            });
        };

        // Handle form submit
        searchForm.addEventListener('submit', (e) => performSearch(e, 1));

        // Handle live search on text input
        const searchInput = searchForm.querySelector('input[name="s"]');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length >= 3 || this.value.length === 0) {
                    performSearch();
                }
            }, 500);
        });

        // Handle select changes
        const selects = searchForm.querySelectorAll('select');
        selects.forEach(select => {
            select.addEventListener('change', () => {
                performSearch();
            });
        });

        // Reset button handler
        const resetButton = document.createElement('button');
        resetButton.type = 'button';
        resetButton.className = 'w-auto px-4 md:px-8 py-3 md:py-4 text-blue-600 hover:text-blue-700 font-semibold rounded-lg border-2 border-blue-600 hover:border-blue-700 transition-colors duration-200 flex items-center justify-center gap-2 text-center text-sm md:text-base';
        resetButton.innerHTML = '<i class="fas fa-undo"></i> Reset';
        
        resetButton.addEventListener('click', () => {
            searchForm.reset();
            resultsContainer.classList.add('hidden');
            featuredSection?.classList.remove('hidden');
        });

        // Append to the reset button container instead of the form
        document.getElementById('reset-button-container').appendChild(resetButton);
    }
});