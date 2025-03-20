<?php
/**
 * Add sharing functionality to Simple File Manager
 */

// Add sharing functionality to the Simple_File_Manager class
class Simple_File_Manager_Sharing {

    /**
     * Constructor
     */
    public function __construct() {
        // Add AJAX handlers for sharing
        add_action('wp_ajax_simple_create_share', array($this, 'handle_create_share'));
        add_action('wp_ajax_simple_list_shares', array($this, 'handle_list_shares'));
        add_action('wp_ajax_simple_delete_share', array($this, 'handle_delete_share'));

        // Add shortcode for viewing shared content
        add_shortcode('shared_file_manager', array($this, 'render_shared_files'));

        // Add public access endpoint
        add_action('init', array($this, 'register_share_endpoint'));
        add_action('template_redirect', array($this, 'handle_share_access'));

        // Add styles and scripts
        add_action('wp_enqueue_scripts', array($this, 'register_share_assets'));

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Register assets for sharing functionality
     */
    public function register_share_assets() {
        // Register JavaScript
        wp_register_script('simple-file-manager-sharing-js', plugin_dir_url(__FILE__) . '../js/file-manager-sharing.js', array('jquery'), '1.0', true);

        wp_localize_script('simple-file-manager-sharing-js', 'simpleFileManagerSharing', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('simple-file-manager-share-nonce'),
            'site_url' => site_url('/shared/')
        ));

        // Register CSS
        wp_register_style('simple-file-manager-sharing-css', plugin_dir_url(__FILE__) . '../css/file-manager-sharing.css', array(), '1.0');

        // Check if we're on a share page and enqueue assets accordingly
        global $wp_query;
        if (isset($wp_query->query_vars['share_token']) && !empty($wp_query->query_vars['share_token'])) {
            wp_enqueue_style('simple-file-manager-sharing-css');
            wp_enqueue_script('simple-file-manager-sharing-js');
        }
    }

    /**
     * Register share endpoint
     */
    public function register_share_endpoint() {
        add_rewrite_rule(
            'shared/([a-zA-Z0-9]+)/?$',
            'index.php?share_token=$matches[1]',
            'top'
        );

        add_rewrite_tag('%share_token%', '([a-zA-Z0-9]+)');
    }

    /**
     * Handle share access
     */
    public function handle_share_access() {
        $share_token = get_query_var('share_token');

        if (empty($share_token)) {
            return;
        }

        // Check if the token exists and is valid
        global $wpdb;
        $table_name = $wpdb->prefix . 'simple_file_shares';

        $share = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE share_token = %s AND is_active = 1 
             AND (expires_at IS NULL OR expires_at > NOW())",
                $share_token
            )
        );

        if (!$share) {
            // Invalid or expired token
            wp_redirect(home_url('/?shared=invalid'));
            exit;
        }

        // Render shared content
        $template_path = plugin_dir_path(__FILE__) . '../templates/shared-view.php';

        // If template doesn't exist, create and use it
        if (!file_exists($template_path)) {
            // This helps in development/testing - in production you would have the file
            $this->create_default_template();
        }

        include_once($template_path);
        exit;
    }

    /**
     * Create default template file if it doesn't exist
     * This is for development/testing purposes
     */
    private function create_default_template() {
        $template_dir = plugin_dir_path(__FILE__) . '../templates/';

        // Create directory if it doesn't exist
        if (!file_exists($template_dir)) {
            mkdir($template_dir, 0755, true);
        }

        // Get the content from our updated template
        $template_content = file_get_contents(plugin_dir_path(__FILE__) . '../templates/shared-view.php');

        if (!$template_content) {
            // Fallback content if sample isn't available
            $template_content = '<?php
/**
 * Template for displaying shared files/folders
 */

// Prevent direct access
if (!defined("ABSPATH")) {
    exit;
}

get_header();
echo "<div class=\"simple-shared-view\">Shared content not found.</div>";
get_footer();
';
        }

        // Write template file
        file_put_contents(plugin_dir_path(__FILE__) . '../templates/shared-view.php', $template_content);
    }

    /**
     * Handle creating a share link
     */
    public function handle_create_share() {
        // Verify nonce
        check_ajax_referer('simple-file-manager-share-nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to share files.'));
        }

        // Get parameters
        $share_type = sanitize_text_field($_POST['share_type']); // 'folder' or 'file'
        $target_path = sanitize_text_field($_POST['target_path']);
        $expiry_days = isset($_POST['expiry_days']) ? intval($_POST['expiry_days']) : 0;
        $password = isset($_POST['password']) ? sanitize_text_field($_POST['password']) : '';

        // Validate parameters
        if (empty($share_type) || empty($target_path)) {
            wp_send_json_error(array('message' => 'Missing required parameters.'));
        }

        // Generate a unique token
        $share_token = wp_generate_password(12, false);

        // Set expiry date if provided
        $expires_at = null;
        if ($expiry_days > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
        }

        // Hash password if provided
        $hashed_password = !empty($password) ? wp_hash_password($password) : null;

        // Store in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'simple_file_shares';

        $inserted = $wpdb->insert(
            $table_name,
            array(
                'user_id' => get_current_user_id(),
                'share_token' => $share_token,
                'share_type' => $share_type,
                'target_path' => $target_path,
                'created_at' => current_time('mysql'),
                'expires_at' => $expires_at,
                'password' => $hashed_password,
                'is_active' => 1
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );

        if (!$inserted) {
            wp_send_json_error(array('message' => 'Failed to create share link.'));
        }

        // Create the share URL
        $share_url = site_url("/shared/$share_token");

        wp_send_json_success(array(
            'share_token' => $share_token,
            'share_url' => $share_url,
            'message' => 'Share link created successfully.'
        ));
    }

    /**
     * Handle listing user's share links
     */
    public function handle_list_shares() {
        // Verify nonce
        check_ajax_referer('simple-file-manager-share-nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to view shares.'));
        }

        // Get current user ID
        $user_id = get_current_user_id();

        // Get shares from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'simple_file_shares';

        $shares = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, share_token, share_type, target_path, created_at, 
                 expires_at, password IS NOT NULL as has_password, is_active
                 FROM $table_name WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            )
        );

        // Format shares for response
        $formatted_shares = array();
        foreach ($shares as $share) {
            $formatted_shares[] = array(
                'id' => $share->id,
                'token' => $share->share_token,
                'type' => $share->share_type,
                'path' => $share->target_path,
                'created' => $share->created_at,
                'expires' => $share->expires_at,
                'has_password' => (bool) $share->has_password,
                'is_active' => (bool) $share->is_active,
                'url' => site_url("/shared/{$share->share_token}")
            );
        }

        wp_send_json_success(array(
            'shares' => $formatted_shares
        ));
    }

    /**
     * Handle deleting a share link
     */
    public function handle_delete_share() {
        // Verify nonce
        check_ajax_referer('simple-file-manager-share-nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to delete shares.'));
        }

        // Get share ID
        $share_id = isset($_POST['share_id']) ? intval($_POST['share_id']) : 0;

        if ($share_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid share ID.'));
        }

        // Get current user ID
        $user_id = get_current_user_id();

        // Delete share (actually just deactivate it)
        global $wpdb;
        $table_name = $wpdb->prefix . 'simple_file_shares';

        $updated = $wpdb->update(
            $table_name,
            array('is_active' => 0),
            array('id' => $share_id, 'user_id' => $user_id),
            array('%d'),
            array('%d', '%d')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => 'Failed to delete share link.'));
        }

        wp_send_json_success(array(
            'message' => 'Share link deleted successfully.'
        ));
    }

    /**
     * Render shared files/folders
     */
    public function render_shared_files($atts) {
        // This is a placeholder for the shortcode
        // Actual rendering happens in handle_share_access
        return '<div class="simple-shared-content-placeholder">Please use a valid share link to access shared content.</div>';
    }

    /**
     * Get shared folder contents
     */
    public function get_shared_folder_contents($user_id, $folder_path) {
        $upload_dir = wp_upload_dir();
        $base_path = $upload_dir['basedir'] . '/user-files/' . $folder_path;
        $base_url = $upload_dir['baseurl'] . '/user-files/' . $folder_path;

        $files = array();
        $folders = array();

        if (file_exists($base_path) && is_dir($base_path)) {
            $dir_handle = opendir($base_path);

            if ($dir_handle) {
                while (($item = readdir($dir_handle)) !== false) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }

                    $item_path = $base_path . '/' . $item;

                    if (is_dir($item_path)) {
                        $folders[] = array(
                            'name' => $item,
                            'path' => $folder_path . '/' . $item
                        );
                    } else {
                        $files[] = array(
                            'name' => $item,
                            'url' => $base_url . '/' . $item,
                            'size' => size_format(filesize($item_path)),
                            'modified' => date('Y-m-d H:i:s', filemtime($item_path)),
                            'type' => wp_check_filetype($item)['type']
                        );
                    }
                }
                closedir($dir_handle);
            }
        }

        return array(
            'folders' => $folders,
            'files' => $files
        );
    }

    /**
     * Validate share password
     */
    public function validate_share_password($share_id, $password) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'simple_file_shares';

        $share = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT password FROM $table_name WHERE id = %d",
                $share_id
            )
        );

        if (!$share || empty($share->password)) {
            return false;
        }

        return wp_check_password($password, $share->password);
    }
}