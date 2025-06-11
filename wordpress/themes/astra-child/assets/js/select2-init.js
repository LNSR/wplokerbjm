jQuery(document).ready(function($) {
    setTimeout(function() {
        if ($('select.select2').length > 0) {
            $('select.select2').each(function() {
                const $select = $(this);
                
                $select.select2({
                    width: '100%',
                    dropdownAutoWidth: true,
                    theme: 'default',
                    placeholder: function() {
                        return $(this).data('placeholder') || 'Pilih...';
                    },
                    allowClear: true,
                    dropdownParent: $select.closest('form')
                });
                
                $select.on('select2:select select2:unselect', function(e) {
                    setTimeout(function() {
                        const $container = $select.next('.select2-container');
                        const $clear = $container.find('.select2-selection__clear');
                        
                        if ($clear.length) {
                            $clear.off('mousedown click');
                            $clear.on('mousedown', function(e) {
                                e.stopPropagation();
                                e.preventDefault();
                                
                                // Manually clear the select
                                $select.val('').trigger('change');
                                return false;
                            });
                            
                            $clear.on('click', function(e) {
                                e.stopPropagation();
                                e.preventDefault();
                                return false;
                            });
                        }
                    }, 10);
                });
            });
        }
    }, 100);
});