jQuery(document).ready(function($) {
    function initSelect2() {
        // Initialize regular Select2 elements (non-lazy)
        $('select.select2:not(.select2-lazy)').each(function() {
            const $select = $(this);
            
            // Skip if already initialized
            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }
            
            // Validate that we have a DOM element
            if (!$select[0] || !$select[0].tagName) {
                return;
            }
            
            try {
                $select.select2({
                    width: '100%',
                    dropdownAutoWidth: true,
                    theme: 'default',
                    placeholder: function() {
                        return $(this).data('placeholder') || 'Pilih...';
                    },
                    allowClear: true,
                    dropdownParent: $select.closest('form').length ? $select.closest('form') : $('body'),
                    templateResult: function(data) {
                        if (!data.id) return data.text;
                        
                        // Get depth from the original option
                        var depth = $(data.element).data('depth') || 0;
                        let style = 'font-weight:600;';
                        let padding = '';
                        
                        if (depth === 1) {
                            style = 'font-weight:500;';
                            padding = 'padding-left: 20px;';
                        } else if (depth > 1) {
                            style = 'font-weight:400;';
                            padding = 'padding-left: ' + (20 * depth) + 'px;';
                        }
                        
                        return $('<span style="' + style + padding + '">' + data.text + '</span>');
                    },
                    templateSelection: function(data) {
                        return data.text;
                    },
                    escapeMarkup: function(markup) {
                        return markup;
                    }
                });
                
                addClearButtonHandler($select);
                
            } catch (error) {
                console.error('Error initializing regular Select2:', error, $select);
            }
        });

        // Initialize lazy-loading Select2 elements with hierarchical support
        $('.select2-lazy').each(function() {
            const $select = $(this);
            
            // Skip if already initialized
            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }
            
            const taxonomy = $select.data('taxonomy');
            const placeholder = $select.data('placeholder') || 'Pilih...';
            
            // Create a unique namespace for this select instance
            const instanceId = 'select2_' + Math.random().toString(36).substr(2, 9);
            $select.data('instance-id', instanceId);
            
            // Store hierarchical state in data attributes - Initialize properly
            $select.data('currentParent', 0);
            $select.data('expandedParents', new Set());
            $select.data('pendingExpansion', false);
            $select.data('backButtonAdded', false); // Track if back button is already added
            
            // Validate that we have a DOM element
            if (!$select[0] || !$select[0].tagName) {
                return;
            }
            
            try {
                $select.select2({
                    width: '100%',
                    dropdownAutoWidth: true,
                    theme: 'default',
                    placeholder: placeholder,
                    allowClear: true,
                    dropdownParent: $select.closest('form').length ? $select.closest('form') : $('body'),
                    ajax: {
                        url: '/wp-json/astra-child/v1/dropdown-options/',
                        dataType: 'json',
                        delay: 300,
                        data: function(params) {
                            const currentParent = $select.data('currentParent') || 0;
                            return {
                                taxonomy: taxonomy,
                                search: params.term || '',
                                page: params.page || 1,
                                per_page: 20,
                                parent: currentParent
                            };
                        },
                        processResults: function(data, params) {
                            if (!data || !data.options) {
                                return { 
                                    results: [],
                                    pagination: { more: false }
                                };
                            }
                            
                            let results = [];
                            const currentParent = $select.data('currentParent') || 0;
                            const isSearching = (params.term && params.term.length > 0);
                            const isFirstPage = !params.page || params.page === 1;
                            
                            // Only add back button on first page, if we're viewing children AND not searching
                            if (currentParent > 0 && !isSearching && isFirstPage) {
                                results.push({
                                    id: 'back-to-parent-' + instanceId,
                                    text: 'Kembali ke Level Atas',
                                    isBackButton: true,
                                    depth: -1,
                                    disabled: false
                                });
                            }
                            
                            // Process regular options
                            const options = data.options.map(option => ({
                                id: option.id,
                                text: option.text,
                                depth: option.depth || 0,
                                parent: option.parent,
                                has_children: option.has_children || false,
                                term_id: option.term_id,
                                isExpandable: option.has_children,
                                instanceId: instanceId
                            }));
                            
                            results = results.concat(options);
                            
                            // Set up event handlers after results are processed (only for first page)
                            if (isFirstPage) {
                                setTimeout(function() {
                                    setupChevronHandlers($select, instanceId);
                                }, 50);
                            }
                            
                            return {
                                results: results,
                                pagination: {
                                    more: data.pagination ? data.pagination.more : false
                                }
                            };
                        },
                        cache: false,
                        error: function(xhr, status, error) {
                            return { 
                                results: [],
                                pagination: { more: false }
                            };
                        }
                    },
                    templateResult: function(data) {
                        if (!data.id) return data.text;
                        
                        // Handle back button
                        if (data.isBackButton) {
                            return $('<div class="back-button-item" data-instance-id="' + instanceId + '" style="font-weight: 500; padding: 8px; margin-bottom: 4px; cursor: pointer;">' + 
                                    '<i class="fas fa-arrow-left" style="margin-right: 8px;"></i>' + data.text + '</div>');
                        }
                        
                        const depth = data.depth || 0;
                        let style = 'font-weight:600;';
                        let padding = 'padding-left: 16px;';
                        
                        if (depth === 1) {
                            style = 'font-weight:500;';
                            padding = 'padding-left: 36px;';
                        } else if (depth > 1) {
                            style = 'font-weight:400;';
                            padding = 'padding-left: ' + (16 + (20 * depth)) + 'px;';
                        }
                        
                        // Create the main content wrapper
                        let content = '<div class="option-content" style="' + style + padding + ' position: relative; padding-right: 50px; cursor: pointer;">' + data.text;
                        
                        // Add children indicator
                        if (data.has_children) {
                            content += '<span class="expand-chevron-wrapper" data-term-id="' + data.term_id + '" data-instance-id="' + instanceId + '" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer; padding: 12px; background-color: rgba(59, 130, 246, 0.3); border-radius: 8px; z-index: 1001; border: 2px solid #3b82f6; width: 80px; height: 36px; display: flex; align-items: center; justify-content: center; gap: 6px;">' +
                                      '<i class="fas fa-chevron-down expand-chevron" style="font-size: 14px; color: #1d4ed8; pointer-events: none; font-weight: bold;"></i>' +
                                      '</span>';
                        }
                        
                        content += '</div>';
                        
                        return $(content);
                    },
                    templateSelection: function(data) {
                        return data.text;
                    },
                    escapeMarkup: function(markup) {
                        return markup;
                    },
                    language: {
                        searching: function() {
                            return 'Mencari...';
                        },
                        loadingMore: function() {
                            return 'Memuat lebih banyak...';
                        },
                        noResults: function() {
                            return 'Tidak ada hasil ditemukan';
                        },
                        inputTooShort: function() {
                            return 'Masukkan 1 atau lebih karakter';
                        }
                    },
                    minimumInputLength: 0
                });

                // Intercept selection events to handle expansions
                $select.on('select2:selecting', function(e) {
                    const data = e.params.args.data;
                    
                    // Check if this is a back button
                    if (data.isBackButton) {
                        e.preventDefault();
                        $select.data('currentParent', 0);
                        $select.data('backButtonAdded', false);
                        
                        // Close and reopen dropdown
                        $select.select2('close');
                        setTimeout(function() {
                            $select.select2('open');
                        }, 200);
                        return false;
                    }
                    
                    // Check if this selection should be converted to expansion
                    if ($select.data('pendingExpansion')) {
                        e.preventDefault();
                        const termId = $select.data('pendingExpansion');
                        $select.data('pendingExpansion', false);
                        
                        // Update current parent
                        $select.data('currentParent', termId);
                        $select.data('backButtonAdded', false); // Reset for new level
                        
                        // Close and reopen dropdown to load children
                        $select.select2('close');
                        setTimeout(function() {
                            $select.select2('open');
                        }, 200);
                        return false;
                    }
                });

                // Reset states when dropdown opens
                $select.on('select2:open', function() {
                    // Reset back button tracking for fresh dropdown
                    $select.data('backButtonAdded', false);
                    
                    const $search = $('.select2-search__field');
                    $search.off('input.hierarchy.' + instanceId).on('input.hierarchy.' + instanceId, function() {
                        const searchTerm = $(this).val();
                        
                        // Reset to root when searching to get all matching results
                        if (searchTerm && searchTerm.length > 0) {
                            $select.data('currentParent', 0);
                            $select.data('backButtonAdded', false);
                        }
                    });
                });

                addClearButtonHandler($select);
                
            } catch (error) {
                console.error('Error initializing Select2 for lazy dropdown:', error, $select);
            }
        });
    }

    // Function to setup chevron handlers after AJAX results are rendered
    function setupChevronHandlers($select, instanceId) {
        setTimeout(function() {
            const $dropdown = $('.select2-dropdown:visible');
            
            if ($dropdown.length === 0) {
                setTimeout(function() {
                    setupChevronHandlers($select, instanceId);
                }, 100);
                return;
            }
            
            // Remove any existing handlers to prevent duplicates
            $dropdown.off('mousedown.hierarchy.' + instanceId);
            $dropdown.off('click.hierarchy.' + instanceId);
            
            // Use mousedown to capture before Select2's click handling
            $dropdown.on('mousedown.hierarchy.' + instanceId, '.expand-chevron-wrapper[data-instance-id="' + instanceId + '"]', function(e) {
                e.stopPropagation();
                e.preventDefault();
                
                const termId = $(this).data('term-id');
                
                if (termId) {
                    // Set flag for expansion instead of selection
                    $select.data('pendingExpansion', termId);
                    
                    // Trigger click on the parent option to make Select2 think it was selected
                    const $parentOption = $(this).closest('.select2-results__option');
                    if ($parentOption.length) {
                        // Simulate the selection to trigger our select2:selecting handler
                        setTimeout(function() {
                            $parentOption.trigger('mouseup');
                        }, 10);
                    }
                }
                return false;
            });
            
            // Also handle regular clicks as fallback
            $dropdown.on('click.hierarchy.' + instanceId, '.expand-chevron-wrapper[data-instance-id="' + instanceId + '"]', function(e) {
                e.stopPropagation();
                e.preventDefault();
                return false;
            });
            
        }, 200);
    }

    function addClearButtonHandler($select) {
        $select.on('select2:select select2:unselect', function(e) {
            setTimeout(function() {
                const $container = $select.next('.select2-container');
                const $clear = $container.find('.select2-selection__clear');
                
                if ($clear.length) {
                    $clear.off('mousedown click');
                    $clear.on('mousedown', function(e) {
                        e.stopPropagation();
                        e.preventDefault();
                        $select.val('').trigger('change');
                        return false;
                    });
                }
            }, 10);
        });
    }

    // Initialize Select2 after DOM is ready and other scripts have loaded
    setTimeout(initSelect2, 200);
});