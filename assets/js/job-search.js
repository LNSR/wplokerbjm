document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('job-search-form');
    const resultsContainer = document.getElementById('search-results');
    const submitButton = document.getElementById('search-submit');
    const loadingSpinner = document.getElementById('search-loading');

    if (searchForm) {
        // Function to handle search
        const performSearch = (e) => {
            if (e) e.preventDefault();
            
            // Show loading state
            const buttonText = submitButton.querySelector('span');
            buttonText.classList.add('hidden');
            loadingSpinner.classList.remove('hidden');
            submitButton.disabled = true;

            // Get form data
            const formData = new FormData(searchForm);
            formData.append('_ajax_nonce', jobSearchData.nonce);

            // Make AJAX request
            fetch(jobSearchData.ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultsContainer.innerHTML = data.data.html;
                    resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    throw new Error(data.data.message || 'Search failed');
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                resultsContainer.innerHTML = `
                    <div class="text-center p-8 bg-red-50 rounded-lg">
                        <p class="text-red-600">Maaf, terjadi kesalahan. Silakan coba lagi.</p>
                    </div>
                `;
            })
            .finally(() => {
                // Reset button state
                buttonText.classList.remove('hidden');
                loadingSpinner.classList.add('hidden');
                submitButton.disabled = false;
            });
        };

        // Handle form submit
        searchForm.addEventListener('submit', performSearch);

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
    }
});