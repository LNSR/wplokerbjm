jQuery(document).ready(function($) {
    $('.share-button').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const postId = button.data('post-id');
        
        $.ajax({
            url: lowonganAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'share_lowongan',
                _ajax_nonce: lowonganAjax.nonce,
                post_id: postId
            },
            beforeSend: function() {
                button.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    if (navigator.share) {
                        navigator.share({
                            title: response.data.title,
                            text: response.data.description,
                            url: response.data.url
                        });
                    } else {
                        // Fallback to copy link
                        navigator.clipboard.writeText(response.data.url)
                            .then(() => showNotification('Link berhasil disalin!'))
                            .catch(() => showNotification('Gagal menyalin link', 'error'));
                    }
                }
            },
            error: function() {
                showNotification('Terjadi kesalahan', 'error');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });

    function showNotification(message, type = 'success') {
        const notification = $(`
            <div class="fixed bottom-4 left-4 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 z-50 ${
                type === 'success' ? 'bg-blue-600 text-white' : 'bg-red-600 text-white'
            }">
                ${message}
            </div>
        `).appendTo('body');

        setTimeout(() => {
            notification.addClass('translate-y-full opacity-0');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
});