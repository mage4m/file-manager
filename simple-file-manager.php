<?php
/**
 * Plugin Name: Simple File Manager
 * Description: A simple file manager with upload functionality and folder creation
 * Version: 1.1
 * Author: Claude
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Simple_File_Manager
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // Register shortcode
        add_shortcode('file_manager', array($this, 'render_file_manager'));

        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));

        // Add AJAX handlers
        add_action('wp_ajax_simple_file_upload', array($this, 'handle_file_upload'));
        add_action('wp_ajax_simple_create_folder', array($this, 'handle_create_folder'));
        add_action('wp_ajax_simple_load_folder', array($this, 'handle_load_folder'));

        // New AJAX handlers for additional functionalities
        add_action('wp_ajax_simple_delete_file', array($this, 'handle_delete_file'));
        add_action('wp_ajax_simple_rename_file', array($this, 'handle_rename_file'));
        add_action('wp_ajax_simple_rename_folder', array($this, 'handle_rename_folder'));
        add_action('wp_ajax_simple_search_files', array($this, 'handle_search_files'));
        add_action('wp_ajax_simple_file_preview', array($this, 'handle_file_preview'));
        add_action('wp_ajax_simple_delete_folder', array($this, 'handle_delete_folder'));
        add_filter('body_class', array($this, 'add_file_manager_body_class'));
    }

    /**
     * Register assets
     */
    public function register_assets()
    {
        wp_register_style('simple-file-manager-css', plugin_dir_url(__FILE__) . 'css/file-manager.css', [], '1.2', 'all');
        wp_register_script('simple-file-manager-js', plugin_dir_url(__FILE__) . 'js/file-manager.js', array('jquery'), '1.2', true);

        wp_localize_script('simple-file-manager-js', 'simpleFileManagerAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('simple-file-manager-nonce')
        ));
    }

    /**
     * Render the file manager
     */
    public function render_file_manager()
    {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to use the file manager.</p>';
        }
        global $is_file_manager_shortcode;
        $is_file_manager_shortcode = true;
        // Enqueue scripts and styles
        wp_enqueue_style('simple-file-manager-css');
        wp_enqueue_script('simple-file-manager-js');

        // Get current user
        $current_user = wp_get_current_user();
        $username = sanitize_user($current_user->user_login);

        // Setup base directory
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/user-files/' . $username;

        // Get folders
        $folders = $this->get_folders($username);

        // Start output buffer
        ob_start();
        ?>
        <div class="simple-file-manager">
            <div class="simple-sidebar">
                <div class="simple-user-info">
                    <h3><?php echo esc_html($username); ?></h3>
                    <div class="simple-actions">
                        <a href="#" class="simple-share">share</a>
                        <a href="#" class="simple-settings">settings</a>
                    </div>
                </div>

                <div class="simple-add-item">
                    <a href="#" id="simple-add-cover-page">+ Add Cover Page</a>
                </div>

                <!-- Search box for folders -->
                <div class="simple-search-box">
                    <input type="text" id="simple-folder-search" placeholder="Search folders...">
                </div>

                <ul class="simple-folder-list">
                    <?php foreach ($folders as $folder) : ?>
                        <li>
                            <a href="#" data-folder="<?php echo esc_attr($folder); ?>"
                               class="<?php echo ($folder === 'logos') ? 'active' : ''; ?>">
                                📁 <?php echo esc_html(ucfirst($folder)); ?>
                            </a>
                            <div class="simple-folder-options">
                                <button class="simple-folder-options-button">⋮</button>
                                <div class="simple-folder-options-menu">
                                    <a href="#" class="simple-folder-rename-option"
                                       data-folder="<?php echo esc_attr($folder); ?>">
                                        <span>✏️</span> Rename
                                    </a>
                                    <!--                                    <a href="#" class="simple-folder-subcollection-option" data-folder="-->
                                    <?php //echo esc_attr($folder); ?><!--">-->
                                    <!--                                        <span>➕</span> Add Subcollection-->
                                    <!--                                    </a>-->
                                    <!--                                    <a href="#" class="simple-folder-duplicate-option" data-folder="-->
                                    <?php //echo esc_attr($folder); ?><!--">-->
                                    <!--                                        <span>📋</span> Duplicate-->
                                    <!--                                    </a>-->
                                    <!--                                    <a href="#" class="simple-folder-toggle-option" data-folder="-->
                                    <?php //echo esc_attr($folder); ?><!--">-->
                                    <!--                                        <span>👁️</span> Toggle-->
                                    <!--                                    </a>-->
                                    <!--                                    <a href="#" class="simple-folder-share-option" data-folder="-->
                                    <?php //echo esc_attr($folder); ?><!--">-->
                                    <!--                                        <span>🔗</span> Share-->
                                    <!--                                    </a>-->
                                    <a href="#" class="simple-folder-delete-option delete-option"
                                       data-folder="<?php echo esc_attr($folder); ?>">
                                        <span>🗑️</span> Delete
                                    </a>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="simple-add-item">
                    <a href="#" id="simple-add-collection">+ Collection</a>
                </div>
            </div>

            <div class="simple-content">
                <div class="simple-header">
                    <h2 class="simple-current-folder"></h2>
                    <div class="simple-actions-container">
                        <!-- Search box for files -->
                        <div class="simple-file-search">
                            <input type="text" id="simple-file-search" placeholder="Search files...">
                        </div>
                        <div class="simple-view-options">
                            <button class="simple-view-grid">🔲</button>
                            <button class="simple-view-list">☰</button>
                        </div>
                    </div>
                </div>

                <div class="simple-file-area">
                    <div class="simple-drop-overlay">
                        <div class="simple-drop-message">
                            <span>📥</span> Drop files here
                        </div>
                    </div>
                    <div class="simple-default-state">
                        <h3>Hi! There</h3>
                        <p>Select a Folder to view it's content.</p>
                    </div>
                    <div class="simple-empty-state" style="display: none;">
                        <h3>Nothing in here</h3>
                        <p>Add some assets to start building out this collection, uplaod file or drag to upload.</p>

                        <div class="simple-upload-options">
                            <button id="simple-upload-files" class="simple-button">
                                <span>⬆️</span> Upload files
                            </button>
                            <button id="simple-import-url" class="simple-button">
                                <span>🔗</span> Import from URL
                            </button>
                            <!--                            <button id="simple-styles-generator" class="simple-button">-->
                            <!--                                <span>🎨</span> Styles Generator-->
                            <!--                            </button>-->
                        </div>

                        <!--                        <div class="simple-additional-options">-->
                        <!--                            <button id="simple-add-color-palette" class="simple-button">-->
                        <!--                                <span>🎨</span> Add color palette-->
                        <!--                            </button>-->
                        <!--                            <button id="simple-add-video" class="simple-button">-->
                        <!--                                <span>🎬</span> Add a video-->
                        <!--                            </button>-->
                        <!--                            <button id="simple-add-text-block" class="simple-button">-->
                        <!--                                <span>📝</span> Add text block-->
                        <!--                            </button>-->
                        <!--                            <button id="simple-add-section-title" class="simple-button">-->
                        <!--                                <span>T</span> Add section title-->
                        <!--                            </button>-->
                        <!--                        </div>-->

                        <!--                        <div class="simple-help-section">-->
                        <!--                            <p>Need some help setting up your brand space?</p>-->
                        <!--                            <a href="#" class="simple-tutorial-link">-->
                        <!--                                <span>📺</span> Watch this tutorial-->
                        <!--                            </a>-->
                        <!--                        </div>-->
                    </div>

                    <div class="simple-files-container"></div>
                </div>

                <div class="simple-navigation">
                    <a href="#" class="simple-previous">⬅️ Previous<br></a>
                    <a href="#" class="simple-next">Next ➡️<br></a>
                </div>
            </div>

            <!-- File upload form (hidden) -->
            <form id="simple-upload-form" style="display:none;">
                <input type="file" id="simple-file-input" multiple>
            </form>

            <!-- Add folder modal -->
            <div id="simple-folder-modal" class="simple-modal">
                <div class="simple-modal-content">
                    <span class="simple-close">&times;</span>
                    <h3>Add New Collection</h3>
                    <input type="text" id="simple-folder-name" placeholder="Collection name">
                    <button id="simple-create-folder" class="simple-button">Create</button>
                </div>
            </div>

            <!-- Import URL modal -->
            <div id="simple-url-modal" class="simple-modal">
                <div class="simple-modal-content">
                    <span class="simple-close">&times;</span>
                    <h3>Import from URL</h3>
                    <input type="text" id="simple-url-input" placeholder="https://example.com/image.jpg">
                    <button id="simple-import-button" class="simple-button">Import</button>
                </div>
            </div>

            <!-- Rename file modal -->
            <div id="simple-rename-file-modal" class="simple-modal">
                <div class="simple-modal-content">
                    <span class="simple-close">&times;</span>
                    <h3>Rename File</h3>
                    <input type="text" id="simple-new-file-name" placeholder="New file name">
                    <input type="hidden" id="simple-current-file-name">
                    <button id="simple-rename-file-button" class="simple-button">Rename</button>
                </div>
            </div>

            <!-- Rename folder modal -->
            <div id="simple-rename-folder-modal" class="simple-modal">
                <div class="simple-modal-content">
                    <span class="simple-close">&times;</span>
                    <h3>Rename Folder</h3>
                    <input type="text" id="simple-new-folder-name" placeholder="New folder name">
                    <input type="hidden" id="simple-current-folder-name">
                    <button id="simple-rename-folder-button" class="simple-button">Rename</button>
                </div>
            </div>
            <!-- File preview modal -->
            <div id="simple-preview-modal" class="simple-modal">
                <div class="simple-preview-modal-content">
                    <span class="simple-close">&times;</span>
                    <div class="simple-preview-header">
                        <h3 id="simple-preview-title">File Preview</h3>
                    </div>
                    <div id="simple-preview-container" class="simple-preview-container">
                        <!-- Preview content will be loaded here -->
                    </div>
                    <div id="simple-preview-details" class="simple-preview-details">
                        <!-- File details will be shown here -->
                    </div>
                </div>
            </div>
        </div>

        <div class="simple-powered-by">
            Powered by <strong>BrandWallet</strong>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get folders for a user
     */
    private function get_folders($username)
    {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/user-files/' . $username;
        $custom_folders = array();

        if (file_exists($base_dir) && is_dir($base_dir)) {
            $dir_handle = opendir($base_dir);

            if ($dir_handle) {
                while (($folder = readdir($dir_handle)) !== false) {
                    if ($folder !== '.' && $folder !== '..' && is_dir($base_dir . '/' . $folder)) {
                        $custom_folders[] = $folder;
                    }
                }
                closedir($dir_handle);
            }
        }

        return $custom_folders;
    }

    /**
     * Get folders for a user
     */
    public function add_file_manager_body_class($classes)
    {
        $classes[] = 'file-manager-active';
        return $classes;
    }

    /**
     * Handle file upload
     */
    public function handle_file_upload()
    {
        // Verify nonce
        check_ajax_referer('simple-file-manager-nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to upload files.'));
        }

        // Check if files were uploaded
        if (empty($_FILES['files'])) {
            wp_send_json_error(array('message' => 'No files were uploaded.'));
        }

        // Get folder name
        $folder = sanitize_text_field($_POST['folder']);

        // Get current user
        $current_user = wp_get_current_user();
        $username = sanitize_user($current_user->user_login);

        // Setup upload directory
        $upload_dir = wp_upload_dir();
        $destination_dir = $upload_dir['basedir'] . '/user-files/' . $username . '/' . $folder;

        // Create directory if it doesn't exist
        if (!file_exists($destination_dir)) {
            wp_mkdir_p($destination_dir);
        }

        // Initialize response
        $uploaded_files = array();
        $errors = array();

        // Process files
        $files = $_FILES['files'];
        $file_count = count($files['name']);

        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === 0) {
                $file_name = sanitize_file_name($files['name'][$i]);
                $destination = $destination_dir . '/' . $file_name;

                // Move the uploaded file
                if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
                    $file_url = $upload_dir['baseurl'] . '/user-files/' . $username . '/' . $folder . '/' . $file_name;

                    $uploaded_files[] = array(
                        'name' => $file_name,
                        'url' => $file_url,
                        'size' => size_format(filesize($destination))
                    );
                } else {
                    $errors[] = 'Failed to move uploaded file: ' . $file_name;
                }
            } else {
                $errors[] = 'Error uploading file: ' . $files['name'][$i];
            }
        }

        // Return response
        if (!empty($uploaded_files)) {
            wp_send_json_success(array(
                'files' => $uploaded_files,
                'errors' => $errors
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to upload files.',
                'errors' => $errors
            ));
        }
    }

    /**
     * Handle folder creation
     */
    public function handle_create_folder()
    {
        // Verify nonce
        check_ajax_referer('simple-file-manager-nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to create folders.'));
        }

        // Get folder name
        $folder_name = sanitize_file_name($_POST['folder_name']);

        if (empty($folder_name)) {
            wp_send_json_error(array('message' => 'Folder name cannot be empty.'));
        }

        // Get current user
        $current_user = wp_get_current_user();
        $username = sanitize_user($current_user->user_login);

        // Setup folder path
        $upload_dir = wp_upload_dir();
        $folder_path = $upload_dir['basedir'] . '/user-files/' . $username . '/' . $folder_name;

        // Check if folder already exists
        if (file_exists($folder_path)) {
            wp_send_json_error(array('message' => 'A folder with this name already exists.'));
        }

        // Create folder
        if (wp_mkdir_p($folder_path)) {
            wp_send_json_success(array(
                'folder' => $folder_name,
                'message' => 'Folder created successfully.'
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to create folder.'));
        }
    }

    /**
     * Handle loading folder contents
     */
    public function handle_load_folder()
    {
        // Verify nonce
        check_ajax_referer('simple-file-manager-nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to view folders.'));
        }

        // Get folder name
        $folder = sanitize_text_field($_POST['folder']);

        // Get current user
        $current_user = wp_get_current_user();
        $username = sanitize_user($current_user->user_login);

        // Setup folder path
        $upload_dir = wp_upload_dir();
        $folder_path = $upload_dir['basedir'] . '/user-files/' . $username . '/' . $folder;
        $folder_url = $upload_dir['baseurl'] . '/user-files/' . $username . '/' . $folder;

        // Check if folder exists
        if (!file_exists($folder_path) || !is_dir($folder_path)) {
            wp_mkdir_p($folder_path);
            wp_send_json_success(array(
                'folder' => $folder,
                'files' => array()
            ));
        }

        // Get search term if exists
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        // Get files in folder
        $files = array();
        $dir_handle = opendir($folder_path);

        if ($dir_handle) {
            while (($file = readdir($dir_handle)) !== false) {
                if ($file !== '.' && $file !== '..' && is_file($folder_path . '/' . $file)) {
                    // Apply search filter if search term exists
                    if (!empty($search_term) && stripos($file, $search_term) === false) {
                        continue;
                    }

                    $file_path = $folder_path . '/' . $file;

                    $files[] = array(
                        'name' => $file,
                        'url' => $folder_url . '/' . $file,
                        'size' => size_format(filesize($file_path)),
                        'modified' => date('Y-m-d H:i:s', filemtime($file_path)),
                        'type' => wp_check_filetype($file)['type']
                    );
                }
            }
            closedir($dir_handle);
        }

        wp_send_json_success(array(
            'folder' => $folder,
            'files' => $files
        ));
    }

    /**
     * Handle file deletion
     */
    public function handle_delete_file()
    {
        // Verify nonce
        check_ajax_referer('simple-file-manager-nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to delete files.'));
        }

        // Get file and folder name
        $file_name = sanitize_file_name($_POST['file']);
        $folder = sanitize_text_field($_POST['folder']);

        if (empty($file_name) || empty($folder)) {
            wp_send_json_error(array('message' => 'Missing file or folder name.'));
        }

        // Get current user
        $current_user = wp_get_current_user();
        $username = sanitize_user($current_user->user_login);

        // Setup file path
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/user-files/' . $username . '/' . $folder . '/' . $file_name;

        // Check if file exists
        if (!file_exists($file_path) || !is_file($file_path)) {
            wp_send_json_error(array('message' => 'File does not exist.'));
        }

        // Delete file
        if (unlink($file_path)) {
            wp_send_json_success(array(
                'message' => 'File deleted successfully.'
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete file.'));
        }
    }

    /**
     * Handle file renaming
     */
    public function handle_rename_file()
    {
        // Verify nonce
        check_ajax_referer('simple-file-manager-nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to rename files.'));
        }

        // Get file names and folder
        $current_name = sanitize_file_name($_POST['current_name']);
        $new_name = sanitize_file_name($_POST['new_name']);
        $folder = sanitize_text_field($_POST['folder']);

        if (empty($current_name) || empty($new_name) || empty($folder)) {
            wp_send_json_error(array('message' => 'Missing file or folder name.'));
        }

        // Get current user
        $current_user = wp_get_current_user();
        $username = sanitize_user($current_user->user_login);

        // Setup file paths
        $upload_dir = wp_upload_dir();
        $current_path = $upload_dir['basedir'] . '/user-files/' . $username . '/' . $folder . '/' . $current_name;
        $new_path = $upload_dir['basedir'] . '/user-files/' . $username . '/' . $folder . '/' . $new_name;

        // Check if source file exists
        if (!file_exists($current_path) || !is_file($current_path)) {
            wp_send_json_error(array('message' => 'File does not exist.'));
        }

        // Check if destination file already exists
        if (file_exists($new_path)) {
            wp_send_json_error(array('message' => 'A file with this name already exists.'));
        }

        // Rename file
        if (rename($current_path, $new_path)) {
            $file_url = $upload_dir['baseurl'] . '/user-files/' . $username . '/' . $folder . '/' . $new_name;

            wp_send_json_success(array(
                'message' => 'File renamed successfully.',
                'name' => $new_name,
                'url' => $file_url
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to rename file.'));
        }
    }

    /**
     * Handle folder renaming
     */
    public function handle_rename_folder()
    {
        // Verify nonce
        check_ajax_referer('simple-file-manager-nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to rename folders.'));
        }

        // Get folder names
        $current_name = sanitize_file_name($_POST['current_name']);
        $new_name = sanitize_file_name($_POST['new_name']);

        if (empty($current_name) || empty($new_name)) {
            wp_send_json_error(array('message' => 'Missing folder name.'));
        }

        // Get current user
        $current_user = wp_get_current_user();
        $username = sanitize_user($current_user->user_login);

        // Setup folder paths
        $upload_dir = wp_upload_dir();
        $current_path = $upload_dir['basedir'] . '/user-files/' . $username . '/' . $current_name;
        $new_path = $upload_dir['basedir'] . '/user-files/' . $username . '/' . $new_name;

        // Check if source folder exists
        if (!file_exists($current_path) || !is_dir($current_path)) {
            wp_send_json_error(array('message' => 'Folder does not exist.'));
        }

        // Check if destination folder already exists
        if (file_exists($new_path)) {
            wp_send_json_error(array('message' => 'A folder with this name already exists.'));
        }

        // Rename folder
        if (rename($current_path, $new_path)) {
            wp_send_json_success(array(
                'message' => 'Folder renamed successfully.',
                'old_name' => $current_name,
                'new_name' => $new_name
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to rename folder.'));
        }
    }

    /**
     * Handle file and folder searching
     */
    public function handle_search_files()
    {
        // Verify nonce
        check_ajax_referer('simple-file-manager-nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to search files.'));
        }

        // Get search term and search type
        $search_term = sanitize_text_field($_POST['search_term']);
        $search_type = sanitize_text_field($_POST['search_type']); // 'files' or 'folders'

        if (empty($search_term)) {
            wp_send_json_error(array('message' => 'Missing search term.'));
        }

        // Get current user
        $current_user = wp_get_current_user();
        $username = sanitize_user($current_user->user_login);

        // Setup base directory
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/user-files/' . $username;

        if ($search_type === 'folders') {
            // Search folders
            $folders = $this->get_folders($username);
            $filtered_folders = array();

            foreach ($folders as $folder) {
                if (stripos($folder, $search_term) !== false) {
                    $filtered_folders[] = $folder;
                }
            }

            wp_send_json_success(array(
                'folders' => $filtered_folders
            ));
        } else {
            // Search files across all folders
            $results = array();
            $folders = $this->get_folders($username);

            foreach ($folders as $folder) {
                $folder_path = $base_dir . '/' . $folder;
                $folder_url = $upload_dir['baseurl'] . '/user-files/' . $username . '/' . $folder;

                if (file_exists($folder_path) && is_dir($folder_path)) {
                    $dir_handle = opendir($folder_path);

                    if ($dir_handle) {
                        while (($file = readdir($dir_handle)) !== false) {
                            if ($file !== '.' && $file !== '..' && is_file($folder_path . '/' . $file)) {
                                if (stripos($file, $search_term) !== false) {
                                    $file_path = $folder_path . '/' . $file;

                                    $results[] = array(
                                        'name' => $file,
                                        'folder' => $folder,
                                        'url' => $folder_url . '/' . $file,
                                        'size' => size_format(filesize($file_path)),
                                        'modified' => date('Y-m-d H:i:s', filemtime($file_path)),
                                        'type' => wp_check_filetype($file)['type']
                                    );
                                }
                            }
                        }
                        closedir($dir_handle);
                    }
                }
            }

            wp_send_json_success(array(
                'files' => $results
            ));
        }
    }

    /**
     * Get file content for preview
     */
    public function handle_file_preview()
    {
        // Verify nonce
        check_ajax_referer('simple-file-manager-nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to preview files.'));
        }

        // Get file and folder name
        $file_name = sanitize_file_name($_POST['file']);
        $folder = sanitize_text_field($_POST['folder']);

        if (empty($file_name) || empty($folder)) {
            wp_send_json_error(array('message' => 'Missing file or folder name.'));
        }

        // Get current user
        $current_user = wp_get_current_user();
        $username = sanitize_user($current_user->user_login);

        // Setup file path
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/user-files/' . $username . '/' . $folder . '/' . $file_name;
        $file_url = $upload_dir['baseurl'] . '/user-files/' . $username . '/' . $folder . '/' . $file_name;

        // Check if file exists
        if (!file_exists($file_path) || !is_file($file_path)) {
            wp_send_json_error(array('message' => 'File does not exist.'));
        }

        // Get file type
        $file_type = wp_check_filetype($file_name);
        $mime_type = $file_type['type'];

        // Determine file content based on type
        $preview_data = array(
            'name' => $file_name,
            'type' => $mime_type,
            'url' => $file_url,
            'size' => size_format(filesize($file_path))
        );

        // For text files, get the content
        $text_types = array('text/plain', 'text/html', 'text/css', 'text/javascript', 'application/javascript', 'application/json');
        if (in_array($mime_type, $text_types)) {
            $preview_data['content'] = file_get_contents($file_path);
        }

        wp_send_json_success($preview_data);
    }

    /**
     * Handle folder deletion
     */
    public function handle_delete_folder()
    {
        // Verify nonce
        check_ajax_referer('simple-file-manager-nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to delete folders.'));
        }

        // Get folder name
        $folder_name = sanitize_file_name($_POST['folder_name']);

        if (empty($folder_name)) {
            wp_send_json_error(array('message' => 'Missing folder name.'));
        }
        // Get current user
        $current_user = wp_get_current_user();
        $username = sanitize_user($current_user->user_login);

        // Setup folder path
        $upload_dir = wp_upload_dir();
        $folder_path = $upload_dir['basedir'] . '/user-files/' . $username . '/' . $folder_name;

        // Check if folder exists
        if (!file_exists($folder_path) || !is_dir($folder_path)) {
            wp_send_json_error(array('message' => 'Folder does not exist.'));
        }

        // Delete folder and its contents
        if ($this->delete_directory($folder_path)) {
            wp_send_json_success(array(
                'message' => 'Folder deleted successfully.'
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete folder.'));
        }
    }

    /**
     * Recursively delete a directory
     */
    private function delete_directory($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }
}

// Initialize the plugin
function simple_file_manager_init()
{
    new Simple_File_Manager();
}

add_action('plugins_loaded', 'simple_file_manager_init');

// Setup folders on plugin activation
function simple_file_manager_activate()
{
    $upload_dir = wp_upload_dir();
    $base_dir = $upload_dir['basedir'] . '/user-files';

    if (!file_exists($base_dir)) {
        wp_mkdir_p($base_dir);
    }
}

register_activation_hook(__FILE__, 'simple_file_manager_activate');