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
        $share = $this->get_share_by_token($share_token);

        if (!$share) {
            // Invalid or expired token
            wp_redirect(home_url('/?shared=invalid'));
            exit;
        }

        // Check if password is required and handle password validation
        $needs_password = !empty($share->password);
        $password_verified = false;
        $password_error = '';

        if ($needs_password) {
            // Check if password is in session
            if (isset($_SESSION['share_password_verified']) &&
                isset($_SESSION['share_password_token']) &&
                $_SESSION['share_password_token'] === $share_token) {
                $password_verified = true;
            }
            // Check if password is submitted
            elseif (isset($_POST['share_password']) && !empty($_POST['share_password'])) {
                // Verify the password
                $submitted_password = sanitize_text_field($_POST['share_password']);
                if ($this->validate_share_password($share->id, $submitted_password)) {
                    // Password is correct, set session
                    $_SESSION['share_password_verified'] = true;
                    $_SESSION['share_password_token'] = $share_token;
                    $password_verified = true;
                } else {
                    $password_error = 'Invalid password. Please try again.';
                }
            }
        } else {
            // No password required
            $password_verified = true;
        }

        // Get share data for template
        $share_data = $this->prepare_share_data($share);

        // If password is required but not verified, show password form
        if ($needs_password && !$password_verified) {
            $template_path = plugin_dir_path(__FILE__) . '../templates/password-view.php';

            if (!file_exists($template_path)) {
                $this->create_password_form_template();
            }

            // Include template with password form
            include_once($template_path);
            exit;
        }

        // Get files and folders if it's a folder share
        if ($share->share_type === 'folder') {
            $share_data['contents'] = $this->get_folder_contents($share_data['current_path']);
        } else {
            $share_data['file_details'] = $this->get_file_details($share_data['current_path']);
        }

        // Render shared content
        $template_path = plugin_dir_path(__FILE__) . '../templates/shared-view.php';

        // If template doesn't exist, create and use it
        if (!file_exists($template_path)) {
            $this->create_default_template();
        }

        // Include template with all necessary data
        include_once($template_path);
        exit;
    }

    /**
     * Get share by token
     */
    private function get_share_by_token($token) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'simple_file_shares';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE share_token = %s AND is_active = 1 
                AND (expires_at IS NULL OR expires_at > NOW())",
                $token
            )
        );
    }

    /**
     * Prepare share data for template
     */
    private function prepare_share_data($share) {
        // Get user data
        $owner = get_userdata($share->user_id);
        $owner_name = $owner ? $owner->display_name : 'Unknown User';

        // Get the share type and target path
        $share_type = $share->share_type;
        $target_path = $share->target_path;

        // Parse the target path
        $path_parts = explode('/', $target_path);
        $username = $path_parts[0];
        $current_path = $target_path;

        // Get the current directory and subfolder if navigating
        $subfolder = isset($_GET['subfolder']) ? sanitize_text_field($_GET['subfolder']) : '';

        if (!empty($subfolder)) {
            // Ensure subfolder is within the shared folder
            if (strpos($subfolder, $target_path) !== 0) {
                $subfolder = $target_path;
            }
            $current_path = $subfolder;
        }

        // Build breadcrumb data
        $breadcrumb = $this->build_breadcrumb($current_path, $target_path);

        return array(
            'id' => $share->id,
            'token' => $share->share_token,
            'type' => $share_type,
            'target_path' => $target_path,
            'current_path' => $current_path,
            'owner_name' => $owner_name,
            'expires_at' => $share->expires_at,
            'username' => $username,
            'breadcrumb' => $breadcrumb
        );
    }

    /**
     * Build breadcrumb for folder navigation
     */
    private function build_breadcrumb($current_path, $target_path) {
        $breadcrumb_parts = explode('/', $current_path);
        $username = $breadcrumb_parts[0];
        $breadcrumb_path = '';
        $breadcrumb = array();

        $breadcrumb[] = array(
            'label' => 'Home',
            'path' => '',
            'is_current' => false
        );

        foreach ($breadcrumb_parts as $index => $part) {
            if ($index === 0) {
                // Skip username
                continue;
            }

            $breadcrumb_path .= '/' . $part;
            $full_path = $username . $breadcrumb_path;

            // Only add link if this isn't the last part
            if ($index < count($breadcrumb_parts) - 1) {
                $breadcrumb[] = array(
                    'label' => $part,
                    'path' => $full_path,
                    'is_current' => false
                );
            } else {
                $breadcrumb[] = array(
                    'label' => $part,
                    'path' => '',
                    'is_current' => true
                );
            }
        }

        return $breadcrumb;
    }

    /**
     * Get folder contents
     */
    private function get_folder_contents($folder_path) {
        $upload_dir = wp_upload_dir();
        $base_path = $upload_dir['basedir'] . '/user-files/' . $folder_path;
        $base_url = $upload_dir['baseurl'] . '/user-files/' . $folder_path;

        $folders = array();
        $files = array();

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
                        $file_extension = pathinfo($item, PATHINFO_EXTENSION);
                        $icon = $this->get_file_icon($file_extension);

                        $files[] = array(
                            'name' => $item,
                            'url' => $base_url . '/' . $item,
                            'size' => size_format(filesize($item_path)),
                            'modified' => date('Y-m-d H:i:s', filemtime($item_path)),
                            'type' => wp_check_filetype($item)['type'],
                            'extension' => $file_extension,
                            'icon' => $icon
                        );
                    }
                }
                closedir($dir_handle);
            }
        }

        return array(
            'folders' => $folders,
            'files' => $files,
            'base_path' => $base_path,
            'base_url' => $base_url
        );
    }

    /**
     * Get file details
     */
    private function get_file_details($file_path) {
        $upload_dir = wp_upload_dir();
        $file_full_path = $upload_dir['basedir'] . '/user-files/' . $file_path;
        $file_url = $upload_dir['baseurl'] . '/user-files/' . $file_path;

        if (file_exists($file_full_path) && is_file($file_full_path)) {
            $file_name = basename($file_full_path);
            $file_size = size_format(filesize($file_full_path));
            $file_type = wp_check_filetype($file_name)['type'];
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $icon = $this->get_file_icon($file_extension);

            return array(
                'name' => $file_name,
                'url' => $file_url,
                'size' => $file_size,
                'type' => $file_type,
                'extension' => $file_extension,
                'icon' => $icon
            );
        }

        return null;
    }

    /**
     * Get file icon based on extension
     */
    private function get_file_icon($extension) {
        $icon = '📄';

        if (in_array($extension, array('jpg', 'jpeg', 'png', 'gif'))) {
            $icon = '🖼️';
        } elseif (in_array($extension, array('mp4', 'mov', 'avi'))) {
            $icon = '🎬';
        } elseif (in_array($extension, array('mp3', 'wav'))) {
            $icon = '🎵';
        } elseif ($extension === 'pdf') {
            $icon = '📕';
        } elseif (in_array($extension, array('doc', 'docx'))) {
            $icon = '📝';
        } elseif (in_array($extension, array('xls', 'xlsx'))) {
            $icon = '📊';
        } elseif (in_array($extension, array('ppt', 'pptx'))) {
            $icon = '📊';
        } elseif (in_array($extension, array('zip', 'rar'))) {
            $icon = '📦';
        }

        return $icon;
    }

    /**
     * Create default template file if it doesn't exist
     */
    private function create_default_template() {
        $template_dir = plugin_dir_path(__FILE__) . '../templates/';

        // Create directory if it doesn't exist
        if (!file_exists($template_dir)) {
            mkdir($template_dir, 0755, true);
        }

        // Template content
        $template_content = '<?php
/**
 * Template for displaying shared files/folders
 */

// Prevent direct access
if (!defined("ABSPATH")) {
    exit;
}

// Get header
get_header();
?>
<div class="simple-shared-view">
    <div class="simple-shared-header">
        <h1 class="simple-shared-title">
            <?php if ($share_data["type"] === "folder"): ?>
                📁 <?php echo esc_html(basename($share_data["target_path"])); ?> - Shared Folder
            <?php else: ?>
                📄 <?php echo esc_html(basename($share_data["target_path"])); ?> - Shared File
            <?php endif; ?>
        </h1>
        <div class="simple-shared-info">
            <p>Shared by: <?php echo esc_html($share_data["owner_name"]); ?></p>
            <?php if ($share_data["expires_at"]): ?>
                <p>Expires: <?php echo esc_html(date("F j, Y", strtotime($share_data["expires_at"]))); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($share_data["type"] === "folder"): ?>
        <!-- Breadcrumb -->
        <div class="simple-shared-breadcrumb">
            <?php foreach ($share_data["breadcrumb"] as $index => $crumb): ?>
                <?php if ($index > 0) echo " / "; ?>
                
                <?php if (empty($crumb["path"]) || $crumb["is_current"]): ?>
                    <span><?php echo esc_html($crumb["label"]); ?></span>
                <?php else: ?>
                    <a href="?<?php echo !empty($crumb["path"]) ? "subfolder=" . esc_attr($crumb["path"]) : ""; ?>">
                        <?php echo esc_html($crumb["label"]); ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Display folders -->
        <?php if (!empty($share_data["contents"]["folders"])): ?>
            <h2>Folders</h2>
            <div class="simple-shared-folders">
                <?php foreach ($share_data["contents"]["folders"] as $folder): ?>
                    <div class="simple-shared-folder-item">
                        <a href="?subfolder=<?php echo esc_attr($folder["path"]); ?>" class="simple-shared-folder-link">
                            <span class="folder-icon">📁</span> <?php echo esc_html($folder["name"]); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Display files -->
        <?php if (!empty($share_data["contents"]["files"])): ?>
            <h2>Files</h2>
            <div class="simple-shared-files">
                <?php foreach ($share_data["contents"]["files"] as $file): ?>
                    <div class="simple-shared-file-item">
                        <div class="file-preview">
                            <?php if (strpos($file["type"], "image/") === 0): ?>
                                <img src="<?php echo esc_url($file["url"]); ?>" alt="<?php echo esc_attr($file["name"]); ?>">
                            <?php else: ?>
                                <div class="file-icon"><?php echo $file["icon"]; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="file-info">
                            <div class="file-name"><?php echo esc_html($file["name"]); ?></div>
                            <div class="file-size"><?php echo esc_html($file["size"]); ?></div>
                        </div>
                        <div class="file-actions">
                            <a href="<?php echo esc_url($file["url"]); ?>" class="file-download" target="_blank">Download</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Empty state -->
        <?php if (empty($share_data["contents"]["folders"]) && empty($share_data["contents"]["files"])): ?>
            <div class="simple-shared-empty">
                <p>This folder is empty.</p>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Display single file -->
        <?php if ($share_data["file_details"]): ?>
            <div class="simple-shared-file-view">
                <!-- File preview -->
                <div class="file-preview-container">
                    <?php if (strpos($share_data["file_details"]["type"], "image/") === 0): ?>
                        <img src="<?php echo esc_url($share_data["file_details"]["url"]); ?>" 
                             alt="<?php echo esc_attr($share_data["file_details"]["name"]); ?>" 
                             class="file-preview-image">
                    <?php elseif (strpos($share_data["file_details"]["type"], "video/") === 0): ?>
                        <video controls class="file-preview-video">
                            <source src="<?php echo esc_url($share_data["file_details"]["url"]); ?>" 
                                    type="<?php echo esc_attr($share_data["file_details"]["type"]); ?>">
                            Your browser does not support video playback.
                        </video>
                    <?php elseif (strpos($share_data["file_details"]["type"], "audio/") === 0): ?>
                        <div class="file-preview-audio">
                            <div class="audio-icon">🎵</div>
                            <audio controls>
                                <source src="<?php echo esc_url($share_data["file_details"]["url"]); ?>" 
                                        type="<?php echo esc_attr($share_data["file_details"]["type"]); ?>">
                                Your browser does not support audio playback.
                            </audio>
                        </div>
                    <?php elseif ($share_data["file_details"]["extension"] === "pdf"): ?>
                        <div class="pdf-container">
                            <iframe src="<?php echo esc_url($share_data["file_details"]["url"]); ?>" class="pdf-preview"></iframe>
                        </div>
                    <?php else: ?>
                        <div class="file-icon-large"><?php echo $share_data["file_details"]["icon"]; ?></div>
                        <p>No preview available for this file type.</p>
                    <?php endif; ?>
                </div>

                <!-- File details -->
                <div class="file-details">
                    <h2><?php echo esc_html($share_data["file_details"]["name"]); ?></h2>
                    <p>Size: <?php echo esc_html($share_data["file_details"]["size"]); ?></p>
                    <p>Type: <?php echo esc_html($share_data["file_details"]["type"]); ?></p>
                    <a href="<?php echo esc_url($share_data["file_details"]["url"]); ?>" class="download-button" download>Download File</a>
                </div>
            </div>
        <?php else: ?>
            <div class="simple-shared-error">
                <p>File not found or access denied.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php get_footer(); ?>
';

        // Write template file
        file_put_contents(plugin_dir_path(__FILE__) . '../templates/shared-view.php', $template_content);
    }

    /**
     * Create password form template
     */
    private function create_password_form_template() {
        $template_dir = plugin_dir_path(__FILE__) . '../templates/';

        // Create directory if it doesn't exist
        if (!file_exists($template_dir)) {
            mkdir($template_dir, 0755, true);
        }

        // Template content
        $template_content = '<?php
/**
 * Template for password protection form
 */

// Prevent direct access
if (!defined("ABSPATH")) {
    exit;
}

// Get header
get_header();
?>
<div class="simple-shared-view">
    <div class="simple-shared-header">
        <h1 class="simple-shared-title">Password Protected Content</h1>
    </div>

    <div class="simple-shared-password-form">
        <p>This content is password protected. Please enter the password to view it.</p>
        
        <?php if (!empty($password_error)): ?>
            <div class="simple-shared-error">
                <p><?php echo esc_html($password_error); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="share_password">Password:</label>
                <input type="password" name="share_password" id="share_password" required>
            </div>
            <button type="submit" class="simple-button">Submit</button>
        </form>
    </div>
</div>
<?php get_footer(); ?>
';

        // Write template file
        file_put_contents(plugin_dir_path(__FILE__) . '../templates/password-form.php', $template_content);
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
        $current_user = wp_get_current_user();
        $username = sanitize_user($current_user->user_login);
        $share_type = sanitize_text_field($_POST['share_type']); // 'folder' or 'file'
        $target_path = $username.'/'.sanitize_text_field($_POST['target_path']);
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