<?php
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
        .simple-shared-error,
        .simple-shared-expired {
            background-color: #f9f9f9;
            border-left: 4px solid #0073aa;
            padding: 15px;
            margin: 20px 0;
        }

        .simple-shared-error,
        .simple-shared-expired {
            border-left-color: #dc3232;
        }

        /* Single file view */
        .simple-shared-file-view {
            margin: 20px 0;
        }

        .file-preview-container {
            margin-bottom: 20px;
            text-align: center;
        }

        .file-preview-image {
            max-width: 100%;
            height: auto;
        }

        .file-preview-video,
        .pdf-preview {
            width: 100%;
            height: 500px;
            border: 1px solid #ddd;
        }

        .file-preview-audio {
            padding: 20px;
            background: #f5f5f5;
            border-radius: 5px;
        }

        .file-icon-large {
            font-size: 120px;
            margin: 20px 0;
        }

        .file-details {
            margin-top: 20px;
        }

        .download-button {
            display: inline-block;
            background-color: #0073aa;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 4px;
            margin-top: 15px;
            font-size: 16px;
        }

        .download-button:hover {
            background-color: #005177;
            color: white;
        }

        /* Button */
        .simple-button {
            display: inline-block;
            background-color: #0073aa;
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            border: none;
            cursor: pointer;
        }

        .simple-button:hover {
            background-color: #005177;
            color: white;
        }
    </style>

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