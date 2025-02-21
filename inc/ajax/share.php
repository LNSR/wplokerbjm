<?php
function handle_share_lowongan() {
    check_ajax_referer('lowongan_share_nonce', 'nonce');
    
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) {
        wp_send_json_error('Invalid post ID');
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'lowongan') {
        wp_send_json_error('Invalid lowongan post');
    }

    $job_data = get_job_meta_data($post_id);
    
    wp_send_json_success([
        'title' => $post->post_title,
        'description' => sprintf(
            'Lowongan Kerja %s di %s', 
            $post->post_title, 
            $job_data['company']
        ),
        'url' => get_permalink($post_id)
    ]);
}
add_action('wp_ajax_share_lowongan', 'handle_share_lowongan');
add_action('wp_ajax_nopriv_share_lowongan', 'handle_share_lowongan');
?>