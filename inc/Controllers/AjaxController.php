<?php
namespace AstraChild\Controllers;

/**
 * Base AJAX Controller
 * 
 * Abstract class for handling AJAX requests
 */
abstract class AjaxController
{
    /**
     * @var string The AJAX action name
     */
    protected $action;
    
    /**
     * @var string The nonce name to verify
     */
    protected $nonce;
    
    /**
     * Initialize the controller
     */
    public function __construct()
    {
        if (empty($this->action)) {
            throw new \LogicException('AJAX action name is required');
        }
        
        $this->registerHooks();
    }
    
    /**
     * Register WordPress hooks
     */
    protected function registerHooks()
    {
        add_action('wp_ajax_' . $this->action, [$this, 'handleRequest']);
        add_action('wp_ajax_nopriv_' . $this->action, [$this, 'handleRequest']);
    }
    
    /**
     * Verify nonce before processing request
     * 
     * @param string $nonce_field Field name containing nonce
     * @return bool Whether nonce was verified
     */
    protected function verifyNonce($nonce_field = '_ajax_nonce')
    {
        if (empty($this->nonce)) {
            return true;
        }
        
        return check_ajax_referer($this->nonce, $nonce_field, false);
    }
    
    /**
     * Handle the AJAX request
     * 
     * @return void
     */
    abstract public function handleRequest();
}