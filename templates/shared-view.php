<?php
/**
 * Template for displaying shared files/folders
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current share details
global $wpdb;
$table_name = $wpdb->prefix . 'simple_file_shares';
$share_token = get_query_var('share_token');

$share = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM $table_name WHERE share_token = %s AND is_active = 1 
         AND (expires_at IS NULL OR expires_at > NOW())",
        $share_token
    )
);

if (!$share) {
    // Display error message and exit
    get_header();
    ?>
    <div class="simple-shared-view">
        <div class="simple-shared-expired">
            <h2>This share link has expired or is no longer available</h2>
            <p>The link you're trying to access is no longer valid. It may have expired or been deleted by the owner.</p>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="simple-button">Return to Home</a>
        </div>
    </div>
    <?php
    get_footer();
    exit;
}

// Check if password is required
$needs_password = !empty($share->password);
$password_verified = false;

if ($needs_password) {
    // Password verification code... (unchanged)
    // ...
}

// Get user data
$owner = get_userdata($share->user_id);
$owner_name = $owner ? $owner->display_name : 'Unknown User';

// Get the share type and target path
$share_type = $share->share_type;
$target_path = $share->target_path;

// Parse the target path
$path_parts = explode('/', $target_path);
$username = $path_parts[0];
$current_path = implode('/', $path_parts);

// Get the current directory and subfolder if navigating
$subfolder = isset($_GET['subfolder']) ? sanitize_text_field($_GET['subfolder']) : '';

if (!empty($subfolder)) {
    // Ensure subfolder is within the shared folder
    if (strpos($subfolder, $target_path) !== 0) {
        $subfolder = $target_path;
    }
    $current_path = $subfolder;
}

// Include header
get_header();

// Add necessary styles
?>
    <style>
        /* Main container */
        .simple-shared-view {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }

        /* Header styling */
        .simple-shared-header {
            margin-bottom: 25px;
            border-bottom: 1px solid #e5e5e5;
            padding-bottom: 15px;
        }

        .simple-shared-title {
            font-size: 24px;
            margin-bottom: 10px;
        }

        /* Breadcrumb styles */
        .simple-shared-breadcrumb {
            background: #f9f9f9;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .simple-shared-breadcrumb a {
            color: #0073aa;
            text-decoration: none;
        }

        .simple-shared-breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Shared files grid layout */
        .simple-shared-files {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .simple-shared-file-item {
            border: 1px solid #e5e5e5;
            border-radius: 5px;
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .simple-shared-file-item:hover {
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        /* File preview container */
        .file-preview {
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f9f9f9;
            overflow: hidden;
        }

        .file-preview img {
            max-width: 100%;
            max-height: 150px;
            object-fit: contain;
        }

        .file-icon {
            font-size: 48px;
            color: #555;
        }

        /* File info section */
        .file-info {
            padding: 10px;
        }

        .file-name {
            font-weight: 500;
            margin-bottom: 5px;
            word-break: break-word;
            font-size: 14px;
        }

        .file-size {
            color: #666;
            font-size: 12px;
        }

        /* File actions */
        .file-actions {
            border-top: 1px solid #e5e5e5;
            padding: 10px;
            text-align: center;
        }

        .file-download {
            display: inline-block;
            background-color: #0073aa;
            color: white;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            transition: background-color 0.2s ease;
        }

        .file-download:hover {
            background-color: #005177;
            color: white;
        }

        /* Folder styles */
        .simple-shared-folders {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .simple-shared-folder-item {
            border: 1px solid #e5e5e5;
            border-radius: 5px;
            padding: 15px;
            transition: all 0.2s ease;
        }

        .simple-shared-folder-item:hover {
            background-color: #f9f9f9;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .simple-shared-folder-link {
            display: flex;
            align-items: center;
            color: #333;
            text-decoration: none;
            font-weight: 500;
        }

        .folder-icon {
            margin-right: 8px;
            font-size: 20px;
        }

        /* Section headings */
        .simple-shared-view h2 {
            margin: 30px 0 15px;
            font-size: 18px;
            font-weight: 500;
            color: #333;
        }

        /* Empty and error states */
        .simple-shared-empty,
        .simple-shared-error {
            background-color: #f9f9f9;
            border-left: 4px solid #0073aa;
            padding: 15px;
            margin: 20px 0;
        }

        .simple-shared-error {
            border-left-color: #dc3232;
        }
    </style>

    <div class="simple-shared-view">
        <div class="simple-shared-header">
            <h1 class="simple-shared-title">
                <?php if ($share_type === 'folder'): ?>
                    📁 <?php echo esc_html(basename($target_path)); ?> - Shared Folder
                <?php else: ?>
                    📄 <?php echo esc_html(basename($target_path)); ?> - Shared File
                <?php endif; ?>
            </h1>
            <div class="simple-shared-info">
                <p>Shared by: <?php echo esc_html($owner_name); ?></p>
                <?php if ($share->expires_at): ?>
                    <p>Expires: <?php echo esc_html(date('F j, Y', strtotime($share->expires_at))); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($share_type === 'folder'): ?>
            <?php
            // Build breadcrumb trail
            $breadcrumb_parts = explode('/', $current_path);
            $breadcrumb_path = '';
            $breadcrumb_html = '<div class="simple-shared-breadcrumb">';
            $breadcrumb_html .= '<a href="?">Home</a>';

            foreach ($breadcrumb_parts as $index => $part) {
                if ($index === 0) {
                    // Skip username
                    continue;
                }

                $breadcrumb_path .= '/' . $part;
                $full_path = $username . $breadcrumb_path;

                // Only add link if this isn't the last part
                if ($index < count($breadcrumb_parts) - 1) {
                    $breadcrumb_html .= ' / <a href="?subfolder=' . esc_attr($full_path) . '">' . esc_html($part) . '</a>';
                } else {
                    $breadcrumb_html .= ' / <span>' . esc_html($part) . '</span>';
                }
            }

            $breadcrumb_html .= '</div>';

            echo $breadcrumb_html;

            // Get folder contents
            $upload_dir = wp_upload_dir();
            $folder_path = $upload_dir['basedir'] . '/user-files/' . 'admin/' . $current_path;
            $folder_url = $upload_dir['baseurl'] . '/user-files/' . 'admin/' . $current_path;

            // Check if this is still within the shared folder
            if (strpos($current_path, $target_path) !== 0 && $current_path !== $target_path) {
                echo '<div class="simple-shared-error">Access denied. You can only browse within the shared folder.</div>';
            } else {
                // Display folders and files
                if (file_exists($folder_path) && is_dir($folder_path)) {
                    $folders = array();
                    $files = array();

                    $dir_handle = opendir($folder_path);

                    if ($dir_handle) {
                        while (($item = readdir($dir_handle)) !== false) {
                            if ($item === '.' || $item === '..') {
                                continue;
                            }

                            $item_path = $folder_path . '/' . $item;

                            if (is_dir($item_path)) {
                                $folders[] = array(
                                    'name' => $item,
                                    'path' => $current_path . '/' . $item
                                );
                            } else {
                                $files[] = array(
                                    'name' => $item,
                                    'url' => $folder_url . '/' . $item,
                                    'size' => size_format(filesize($item_path)),
                                    'modified' => date('Y-m-d H:i:s', filemtime($item_path)),
                                    'type' => wp_check_filetype($item)['type']
                                );
                            }
                        }
                        closedir($dir_handle);
                    }

                    // Display folders
                    if (!empty($folders)) {
                        echo '<h2>Folders</h2>';
                        echo '<div class="simple-shared-folders">';

                        foreach ($folders as $folder) {
                            echo '<div class="simple-shared-folder-item">';
                            echo '<a href="?subfolder=' . esc_attr($folder['path']) . '" class="simple-shared-folder-link">';
                            echo '<span class="folder-icon">📁</span> ' . esc_html($folder['name']);
                            echo '</a>';
                            echo '</div>';
                        }

                        echo '</div>';
                    }

                    // Display files
                    if (!empty($files)) {
                        echo '<h2>Files</h2>';
                        echo '<div class="simple-shared-files">';

                        foreach ($files as $file) {
                            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                            $icon = '📄';

                            // Set icon based on file type
                            if (in_array($file_extension, array('jpg', 'jpeg', 'png', 'gif'))) {
                                $icon = '🖼️';
                            } elseif (in_array($file_extension, array('mp4', 'mov', 'avi'))) {
                                $icon = '🎬';
                            } elseif (in_array($file_extension, array('mp3', 'wav'))) {
                                $icon = '🎵';
                            } elseif ($file_extension === 'pdf') {
                                $icon = '📕';
                            } elseif (in_array($file_extension, array('doc', 'docx'))) {
                                $icon = '📝';
                            } elseif (in_array($file_extension, array('xls', 'xlsx'))) {
                                $icon = '📊';
                            }

                            echo '<div class="simple-shared-file-item">';
                            echo '<div class="file-preview">';

                            // Show image preview for image files
                            if (strpos($file['type'], 'image/') === 0) {
                                echo '<img src="' . esc_url($file['url']) . '" alt="' . esc_attr($file['name']) . '">';
                            } else {
                                echo '<div class="file-icon">' . $icon . '</div>';
                            }

                            echo '</div>';
                            echo '<div class="file-info">';
                            echo '<div class="file-name">' . esc_html($file['name']) . '</div>';
                            echo '<div class="file-size">' . esc_html($file['size']) . '</div>';
                            echo '</div>';
                            echo '<div class="file-actions">';
                            echo '<a href="' . esc_url($file['url']) . '" class="file-download" target="_blank">Download</a>';
                            echo '</div>';
                            echo '</div>';
                        }

                        echo '</div>';
                    }

                    // Show empty state
                    if (empty($folders) && empty($files)) {
                        echo '<div class="simple-shared-empty">';
                        echo '<p>This folder is empty.</p>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="simple-shared-error">';
                    echo '<p>Folder not found or access denied.</p>';
                    echo '</div>';
                }
            }
            ?>
        <?php else: ?>
            <?php
            // Display single file
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/user-files/' . 'admin/' . $target_path;
            $file_url = $upload_dir['baseurl'] . '/user-files/' . 'admin/' . $target_path;

            if (file_exists($file_path) && is_file($file_path)) {
                $file_name = basename($file_path);
                $file_size = size_format(filesize($file_path));
                $file_type = wp_check_filetype($file_name)['type'];
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);

                echo '<div class="simple-shared-file-view">';

                // File preview based on type
                echo '<div class="file-preview-container">';

                if (strpos($file_type, 'image/') === 0) {
                    // Image preview
                    echo '<img src="' . esc_url($file_url) . '" alt="' . esc_attr($file_name) . '" class="file-preview-image">';
                } elseif (strpos($file_type, 'video/') === 0) {
                    // Video preview
                    echo '<video controls class="file-preview-video">';
                    echo '<source src="' . esc_url($file_url) . '" type="' . esc_attr($file_type) . '">';
                    echo 'Your browser does not support video playback.';
                    echo '</video>';
                } elseif (strpos($file_type, 'audio/') === 0) {
                    // Audio preview
                    echo '<div class="file-preview-audio">';
                    echo '<div class="audio-icon">🎵</div>';
                    echo '<audio controls>';
                    echo '<source src="' . esc_url($file_url) . '" type="' . esc_attr($file_type) . '">';
                    echo 'Your browser does not support audio playback.';
                    echo '</audio>';
                    echo '</div>';
                } elseif ($file_extension === 'pdf') {
                    // PDF preview
                    echo '<div class="pdf-container">';
                    echo '<iframe src="' . esc_url($file_url) . '" class="pdf-preview"></iframe>';
                    echo '</div>';
                } else {
                    // Generic file icon
                    $icon = '📄';

                    if (in_array($file_extension, array('doc', 'docx'))) {
                        $icon = '📝';
                    } elseif (in_array($file_extension, array('xls', 'xlsx'))) {
                        $icon = '📊';
                    } elseif (in_array($file_extension, array('ppt', 'pptx'))) {
                        $icon = '📊';
                    } elseif ($file_extension === 'pdf') {
                        $icon = '📕';
                    } elseif (in_array($file_extension, array('zip', 'rar'))) {
                        $icon = '📦';
                    }

                    echo '<div class="file-icon-large">' . $icon . '</div>';
                    echo '<p>No preview available for this file type.</p>';
                }

                echo '</div>';

                // File details
                echo '<div class="file-details">';
                echo '<h2>' . esc_html($file_name) . '</h2>';
                echo '<p>Size: ' . esc_html($file_size) . '</p>';
                echo '<p>Type: ' . esc_html($file_type) . '</p>';
                echo '<a href="' . esc_url($file_url) . '" class="download-button" download>Download File</a>';
                echo '</div>';

                echo '</div>';
            } else {
                echo '<div class="simple-shared-error">';
                echo '<p>File not found or access denied.</p>';
                echo '</div>';
            }
            ?>
        <?php endif; ?>
    </div>

<?php get_footer(); ?>