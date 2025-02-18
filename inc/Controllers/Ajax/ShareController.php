<?php
namespace AstraChild\Controllers\Ajax;

/**
 * Share Controller
 * 
 * Handles job sharing functionality
 */
class ShareController extends BaseJobAjaxController
{
    /**
     * @var string AJAX action name
     */
    protected $action = 'share_lowongan';
    
    /**
     * @var string Nonce name
     */
    protected $nonce = 'lowongan_share_nonce';    

    
    /**
     * Handle share request
     * 
     * @return void
     */
    public function handleRequest()
    {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$this->validateJobRequest('_ajax_nonce', $post_id)) {
            return;
        }

        $post = get_post($post_id);
        $job_data = $this->jobModel->getJobMetaData($post_id);
        
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
}