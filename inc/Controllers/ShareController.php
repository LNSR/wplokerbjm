<?php
namespace AstraChild\Controllers;

use AstraChild\Models\JobModel;

/**
 * Share Controller
 * 
 * Handles job sharing functionality
 */
class ShareController extends AjaxController
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
     * @var JobModel
     */
    private $jobModel;
    
    /**
     * Initialize the controller
     */
    public function __construct()
    {
        $this->jobModel = new JobModel();
        parent::__construct();
    }
    
    /**
     * Handle share request
     * 
     * @return void
     */
    public function handleRequest()
    {
        if (!$this->verifyNonce()) {
            wp_send_json_error('Invalid security token');
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'lowongan') {
            wp_send_json_error('Invalid lowongan post');
        }

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