/**
 * Simple File Manager JavaScript
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        // Variables
        var currentFolder = '';
        var searchTimeout;

        // Initialize events
        initFolderNavigation();
        initFileUpload();
        initDragAndDrop();
        initFolderCreation();
        initModals();
        initViewToggle();
        initFileActions();
        initFolderActions();
        initSearch();
        initFilePreview();
        initFolderOptions();

        // Load initial folder
        // loadFolder(currentFolder);

        /**
         * Initialize folder navigation
         */
        function initFolderNavigation() {
            // Handle folder clicks
            $(document).on('click', '.simple-folder-list a', function (e) {
                e.preventDefault();

                // Update active class
                $('.simple-folder-list a').removeClass('active');
                $(this).addClass('active');

                // Get folder name
                currentFolder = $(this).data('folder');

                // Update current folder display
                $('.simple-current-folder').text(currentFolder.charAt(0).toUpperCase() + currentFolder.slice(1));

                // Load folder contents
                loadFolder(currentFolder);
            });

            // Previous/Next navigation
            $('.simple-previous, .simple-next').on('click', function (e) {
                e.preventDefault();

                var folders = [];
                $('.simple-folder-list a').each(function () {
                    folders.push($(this).data('folder'));
                });

                var currentIndex = folders.indexOf(currentFolder);
                var newIndex;

                if ($(this).hasClass('simple-previous')) {
                    newIndex = (currentIndex - 1 + folders.length) % folders.length;
                } else {
                    newIndex = (currentIndex + 1) % folders.length;
                }

                currentFolder = folders[newIndex];

                // Update active class
                $('.simple-folder-list a').removeClass('active');
                $('.simple-folder-list a[data-folder="' + currentFolder + '"]').addClass('active');

                // Update current folder display
                $('.simple-current-folder').text(currentFolder.charAt(0).toUpperCase() + currentFolder.slice(1));

                // Update navigation text
                var prevIndex = (newIndex - 1 + folders.length) % folders.length;
                var nextIndex = (newIndex + 1) % folders.length;

                $('.simple-previous').html('⬅️ Previous<br>' + folders[prevIndex].charAt(0).toUpperCase() + folders[prevIndex].slice(1));
                $('.simple-next').html('Next ➡️<br>' + folders[nextIndex].charAt(0).toUpperCase() + folders[nextIndex].slice(1));

                // Load folder contents
                loadFolder(currentFolder);
            });
        }

        /**
         * Initialize file upload
         */
        function initFileUpload() {
            // Upload button click
            $('#simple-upload-files').on('click', function () {
                $('#simple-file-input').click();
            });

            // File input change
            $('#simple-file-input').on('change', function () {
                if (this.files.length > 0) {
                    uploadFiles(this.files);
                }
            });

            // Import from URL
            $('#simple-import-url').on('click', function () {
                $('#simple-url-modal').show();
            });

            // Import button click
            $('#simple-import-button').on('click', function () {
                var url = $('#simple-url-input').val().trim();

                if (url) {
                    importFromUrl(url);
                    $('#simple-url-modal').hide();
                    $('#simple-url-input').val('');
                } else {
                    alert('Please enter a valid URL');
                }
            });
        }

        /**
         * Initialize drag and drop functionality
         */
        function initDragAndDrop() {
            // Get drop overlay and file area
            var $fileArea = $('.simple-file-area');
            var $dropOverlay = $('.simple-drop-overlay');

            // Counter to track drag enter/leave events
            var dragCounter = 0;

            // Handle document level drag events to prevent browser default behavior
            $(document).on('dragover dragenter drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
            });

            // Handle drag enter on file area
            $(document).on('dragenter', '.simple-file-area', function (e) {
                e.preventDefault();
                dragCounter++;
                $dropOverlay.css('display', 'flex');
            });

            // Handle drag leave on file area
            $(document).on('dragleave', '.simple-file-area', function (e) {
                e.preventDefault();
                dragCounter--;
                if (dragCounter === 0) {
                    $dropOverlay.css('display', 'none');
                }
            });

            // Handle drop on file area
            $(document).on('drop', '.simple-file-area', function (e) {
                e.preventDefault();
                e.stopPropagation();

                // Reset counter and hide overlay
                dragCounter = 0;
                $dropOverlay.css('display', 'none');

                // Get dropped files
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    uploadFiles(files);
                }
            });

            // Handle drop on overlay
            $(document).on('drop', '.simple-drop-overlay', function (e) {
                e.preventDefault();
                e.stopPropagation();

                // Reset counter and hide overlay
                dragCounter = 0;
                $dropOverlay.css('display', 'none');

                // Get dropped files
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    uploadFiles(files);
                }
            });
        }

        /**
         * Initialize folder creation
         */
        function initFolderCreation() {
            $('#simple-add-collection').on('click', function (e) {
                e.preventDefault();
                $('#simple-folder-modal').show();
            });

            $('#simple-create-folder').on('click', function () {
                var folderName = $('#simple-folder-name').val().trim();

                if (folderName) {
                    createFolder(folderName);
                    $('#simple-folder-modal').hide();
                    $('#simple-folder-name').val('');
                    // setTimeout(() => {
                    //     window.location.reload();
                    // }, 1000);
                } else {
                    alert('Please enter a folder name');
                }
            });
        }

        /**
         * Initialize modals
         */
        function initModals() {
            // Close modal
            $('.simple-close').on('click', function () {
                $(this).closest('.simple-modal').hide();
            });

            // Close modal on outside click
            $('.simple-modal').on('click', function (e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });
        }

        /**
         * Initialize view toggle
         */
        function initViewToggle() {
            $('.simple-view-grid').on('click', function () {
                $('.simple-files-container').removeClass('list-view');
            });

            $('.simple-view-list').on('click', function () {
                $('.simple-files-container').addClass('list-view');
            });
        }

        /**
         * Initialize file actions (rename, delete)
         */
        function initFileActions() {
            // Delete file
            $(document).on('click', '.simple-file-delete', function () {
                var fileName = $(this).data('file');

                if (confirm('Are you sure you want to delete this file?')) {
                    deleteFile(fileName, currentFolder);
                }
            });

            // Rename file
            $(document).on('click', '.simple-file-rename', function () {
                var fileName = $(this).data('file');

                // Set current file name in modal
                $('#simple-current-file-name').val(fileName);
                $('#simple-new-file-name').val(fileName);

                // Show modal
                $('#simple-rename-file-modal').show();
            });

            // Rename file button click
            $('#simple-rename-file-button').on('click', function () {
                var currentName = $('#simple-current-file-name').val();
                var newName = $('#simple-new-file-name').val().trim();

                if (newName && currentName !== newName) {
                    renameFile(currentName, newName, currentFolder);
                    $('#simple-rename-file-modal').hide();
                } else if (!newName) {
                    alert('Please enter a new file name');
                } else {
                    $('#simple-rename-file-modal').hide();
                }
            });
        }

        /**
         * Initialize folder actions (rename)
         */
        function initFolderActions() {
            // Rename folder
            $(document).on('click', '.simple-folder-rename', function () {
                var folderName = $(this).data('folder');

                // Set current folder name in modal
                $('#simple-current-folder-name').val(folderName);
                $('#simple-new-folder-name').val(folderName);

                // Show modal
                $('#simple-rename-folder-modal').show();
            });

            // Rename folder button click
            $('#simple-rename-folder-button').on('click', function () {
                var currentName = $('#simple-current-folder-name').val();
                var newName = $('#simple-new-folder-name').val().trim();

                if (newName && currentName !== newName) {
                    renameFolder(currentName, newName);
                    $('#simple-rename-folder-modal').hide();
                } else if (!newName) {
                    alert('Please enter a new folder name');
                } else {
                    $('#simple-rename-folder-modal').hide();
                }
            });
        }

        /**
         * Initialize search functionality
         */
        function initSearch() {
            // Search files
            $('#simple-file-search').on('input', function () {
                var searchTerm = $(this).val().trim();

                // Clear previous timeout
                clearTimeout(searchTimeout);

                // Set new timeout to prevent multiple requests
                searchTimeout = setTimeout(function () {
                    loadFolder(currentFolder, searchTerm);
                }, 300);
            });

            // Search folders
            $('#simple-folder-search').on('input', function () {
                var searchTerm = $(this).val().trim();

                // Clear previous timeout
                clearTimeout(searchTimeout);

                // Set new timeout to prevent multiple requests
                searchTimeout = setTimeout(function () {
                    searchFolders(searchTerm);
                }, 300);
            });
        }

        /**
         * Initialize file preview functionality
         */
        function initFilePreview() {
            // Add preview button to file actions
            $(document).on('click', '.simple-file-preview', function () {
                var fileName = $(this).data('file');
                var fileUrl = $(this).data('url');

                previewFile(fileName, fileUrl, currentFolder);
            });
        }

        /**
         * Initialize folder options menu
         */
        function initFolderOptions() {
            // Toggle menu on click
            $(document).on('click', '.simple-folder-options-button', function (e) {
                e.preventDefault();
                e.stopPropagation();

                // Close any other open menus
                $('.simple-folder-options-menu').not($(this).next()).removeClass('active');

                // Toggle menu
                $(this).next('.simple-folder-options-menu').toggleClass('active');
            });

            // Close menu when clicking outside
            $(document).on('click', function (e) {
                if (!$(e.target).closest('.simple-folder-options').length) {
                    $('.simple-folder-options-menu').removeClass('active');
                }
            });

            // Rename folder option
            $(document).on('click', '.simple-folder-rename-option', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var folderName = $(this).data('folder');

                // Close menu
                $(this).closest('.simple-folder-options-menu').removeClass('active');

                // Set current folder name in modal
                $('#simple-current-folder-name').val(folderName);
                $('#simple-new-folder-name').val(folderName);

                // Show modal
                $('#simple-rename-folder-modal').show();
            });

            // Delete folder option
            $(document).on('click', '.simple-folder-delete-option', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var folderName = $(this).data('folder');

                // Close menu
                $(this).closest('.simple-folder-options-menu').removeClass('active');

                // Confirm deletion
                if (confirm('Are you sure you want to delete this folder and all its contents? This action cannot be undone.')) {
                    deleteFolder(folderName);
                }
            });

            // Placeholder functions for other options
            $(document).on('click', '.simple-folder-subcollection-option, .simple-folder-duplicate-option, .simple-folder-toggle-option, .simple-folder-share-option', function (e) {
                e.preventDefault();
                e.stopPropagation();

                // Close menu
                $(this).closest('.simple-folder-options-menu').removeClass('active');

                alert('This feature is not implemented yet.');
            });
        }

        /**
         * Preview file
         */
        function previewFile(fileName, fileUrl, folder) {
            $.ajax({
                url: simpleFileManagerAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'simple_file_preview',
                    nonce: simpleFileManagerAjax.nonce,
                    file: fileName,
                    folder: folder
                },
                beforeSend: function () {
                    // Show modal with loading message
                    $('#simple-preview-title').text('Loading preview...');
                    $('#simple-preview-container').html('<p>Loading...</p>');
                    $('#simple-preview-details').empty();
                    $('#simple-preview-modal').show();
                },
                success: function (response) {
                    if (response.success) {
                        var data = response.data;
                        $('#simple-preview-title').text(data.name);

                        // Show preview based on file type
                        var previewHtml = '';
                        var fileType = data.type ? data.type.split('/')[0] : '';
                        var fileExtension = data.name.split('.').pop().toLowerCase();

                        if (fileType === 'image') {
                            // Image preview
                            previewHtml = '<img src="' + data.url + '" alt="' + data.name + '">';
                        } else if (fileType === 'audio') {
                            // Audio preview
                            previewHtml = '<audio controls><source src="' + data.url + '">Your browser does not support audio playback.</audio>';
                        } else if (fileType === 'video') {
                            // Video preview
                            previewHtml = '<video controls><source src="' + data.url + '">Your browser does not support video playback.</video>';
                        } else if (fileExtension === 'pdf') {
                            // PDF preview (iframe)
                            previewHtml = '<iframe class="simple-preview-pdf" src="' + data.url + '"></iframe>';
                        } else if (data.content) {
                            // Text preview
                            previewHtml = '<pre class="simple-preview-text">' + data.content + '</pre>';
                        } else {
                            // Unsupported file type
                            previewHtml = '<div class="simple-unsupported-preview">' +
                                '<p>No preview available for this file type.</p>' +
                                '<a href="' + data.url + '" class="simple-button" target="_blank">Download File</a>' +
                                '</div>';
                        }

                        $('#simple-preview-container').html(previewHtml);

                        // Show file details
                        var detailsHtml = '<p>Type: ' + (data.type || 'Unknown') + '</p>' +
                            '<p>Size: ' + data.size + '</p>';

                        $('#simple-preview-details').html(detailsHtml);
                    } else {
                        $('#simple-preview-title').text('Error');
                        $('#simple-preview-container').html('<p>Error: ' + response.data.message + '</p>');
                    }
                },
                error: function () {
                    $('#simple-preview-title').text('Error');
                    $('#simple-preview-container').html('<p>Error loading preview</p>');
                }
            });
        }

        /**
         * Load folder contents
         */
        function loadFolder(folder, searchTerm) {
            $.ajax({
                url: simpleFileManagerAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'simple_load_folder',
                    nonce: simpleFileManagerAjax.nonce,
                    folder: folder,
                    search: searchTerm || ''
                },
                beforeSend: function () {
                    $('.simple-default-state').hide();
                    $('.simple-files-container').html('<p>Loading...</p>');
                },
                success: function (response) {
                    if (response.success) {
                        displayFiles(response.data.files);
                    } else {
                        $('.simple-files-container').html('<p>Error: ' + response.data.message + '</p>');
                    }
                },
                error: function () {
                    $('.simple-files-container').html('<p>Error loading folder contents</p>');
                }
            });
        }

        /**
         * Search folders
         */
        function searchFolders(searchTerm) {
            if (!searchTerm) {
                // Show all folders if search is empty
                $('.simple-folder-list li').show();
                return;
            }

            // Filter folders in the UI
            $('.simple-folder-list li').each(function () {
                var folderName = $(this).find('a').data('folder').toLowerCase();

                if (folderName.indexOf(searchTerm.toLowerCase()) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }

        /**
         * Display files
         */
        function displayFiles(files) {
            // Clear container
            $('.simple-files-container').empty();

            // Check if there are files to display
            if (files.length === 0) {
                // Show the empty state instead of adding a persistent upload area
                $('.simple-empty-state').show();
                $('.simple-default-state').hide();
                return;
            }

            // If we have files, hide the empty state and add the persistent upload area
            $('.simple-empty-state').hide();
            $('.simple-default-state').hide();

            // Add persistent upload area only when files exist
            var uploadAreaHtml = '<div class="simple-persistent-upload">' +
                '<h4 class="simple-persistent-upload-title">Upload to this folder</h4>' +
                '<div class="simple-persistent-upload-actions">' +
                '<button id="simple-persistent-upload-btn" class="simple-button">' +
                '<span>⬆️</span> Upload Files</button>' +
                // '<button id="simple-persistent-import-url" class="simple-button">' +
                // '<span>🔗</span> Import URL</button>' +
                '</div>' +
                '</div>';

            $('.simple-files-container').append(uploadAreaHtml);

            // Handle new upload button click
            $('#simple-persistent-upload-btn').on('click', function () {
                $('#simple-file-input').click();
            });

            // Handle new import URL button click
            $('#simple-persistent-import-url').on('click', function () {
                $('#simple-url-modal').show();
            });

            var html = '';

            $.each(files, function (index, file) {
                var previewHtml = '';

                if (file.type && file.type.indexOf('image/') === 0) {
                    previewHtml = '<img src="' + file.url + '" alt="' + file.name + '">';
                } else {
                    // Show icon based on file type
                    var fileExtension = file.name.split('.').pop().toLowerCase();
                    var icon = '📄';

                    if (['jpg', 'jpeg', 'png', 'gif', 'svg'].includes(fileExtension)) {
                        icon = '🖼️';
                    } else if (['mp4', 'mov', 'avi', 'webm'].includes(fileExtension)) {
                        icon = '🎬';
                    } else if (['mp3', 'wav', 'ogg'].includes(fileExtension)) {
                        icon = '🎵';
                    } else if (['pdf'].includes(fileExtension)) {
                        icon = '📕';
                    } else if (['zip', 'rar', 'tar', 'gz'].includes(fileExtension)) {
                        icon = '📦';
                    } else if (['doc', 'docx'].includes(fileExtension)) {
                        icon = '📝';
                    }

                    previewHtml = '<div class="simple-file-icon">' + icon + '</div>';
                }

                html += '<div class="simple-file-item">' +
                    '<div class="simple-file-preview">' + previewHtml + '</div>' +
                    '<div class="simple-file-info">' +
                    '<div class="simple-file-name">' + file.name + '</div>' +
                    '<div class="simple-file-size">' + file.size + '</div>' +
                    '</div>' +
                    '<div class="simple-file-actions">' +
                    '<button class="simple-file-action simple-file-preview" data-file="' + file.name + '" data-url="' + file.url + '">👁️</button>' +
                    '<button class="simple-file-action simple-file-rename" data-file="' + file.name + '">✏️</button>' +
                    '<button class="simple-file-action simple-file-download" data-url="' + file.url + '">⬇️</button>' +
                    '<button class="simple-file-action simple-file-delete" data-file="' + file.name + '">🗑️</button>' +
                    '</div>' +
                    '</div>';
            });

            $('.simple-files-container').append(html);

            // Handle file actions
            $('.simple-file-download').on('click', function () {
                var url = $(this).data('url');
                window.open(url, '_blank');
            });
        }

        /**
         * Upload files
         */
        function uploadFiles(files) {
            var formData = new FormData();

            // Add files
            for (var i = 0; i < files.length; i++) {
                formData.append('files[]', files[i]);
            }

            // Add data
            formData.append('action', 'simple_file_upload');
            formData.append('nonce', simpleFileManagerAjax.nonce);
            formData.append('folder', currentFolder);

            $.ajax({
                url: simpleFileManagerAjax.ajax_url,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                beforeSend: function () {
                    $('.simple-files-container').html('<p>Uploading...</p>');
                    $('.simple-default-state').hide();
                    $('.simple-empty-state').hide();
                },
                success: function (response) {
                    if (response.success) {
                        loadFolder(currentFolder);

                        if (response.data.errors && response.data.errors.length > 0) {
                            alert('Some files failed to upload: ' + response.data.errors.join(', '));
                        }
                    } else {
                        alert('Error: ' + response.data.message);
                        loadFolder(currentFolder);
                    }
                },
                error: function () {
                    alert('Error uploading files');
                    loadFolder(currentFolder);
                },
                complete: function () {
                    // Reset file input
                    $('#simple-file-input').val('');
                }
            });
        }

        /**
         * Import from URL
         */
        function importFromUrl(url) {
            // This is a simplified version - would need server-side code to properly fetch and save the file
            alert('Import from URL functionality would be implemented here. URL: ' + url);
        }

        /**
         * Create folder
         */
        function createFolder(folderName) {
            $.ajax({
                url: simpleFileManagerAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'simple_create_folder',
                    nonce: simpleFileManagerAjax.nonce,
                    folder_name: folderName
                },
                beforeSend: function () {
                    $('#simple-create-folder').prop('disabled', true).text('Creating...');
                },
                success: function (response) {
                    if (response.success) {
                        // Add folder to list
                        var folderHtml = '<li><a href="#" data-folder="' + response.data.folder + '">' +
                            '📁 ' + response.data.folder.charAt(0).toUpperCase() + response.data.folder.slice(1) +
                            '</a>' +
                            '<div class="simple-folder-options">' +
                            '<button class="simple-folder-options-button">⋮</button>' +
                            '<div class="simple-folder-options-menu">' +
                            '<a href="#" class="simple-folder-rename-option" data-folder="' + response.data.folder + '">' +
                            '<span>✏️</span> Rename' +
                            '</a>' +
                            '<a href="#" class="simple-folder-delete-option delete-option" data-folder="' + response.data.folder + '">' +
                            '<span>🗑️</span> Delete' +
                            '</a>' +
                            '</div>' +
                            '</div>' +
                            '</li>';

                        $('.simple-folder-list').append(folderHtml);

                        // Switch to new folder
                        currentFolder = response.data.folder;
                        $('.simple-current-folder').text(currentFolder.charAt(0).toUpperCase() + currentFolder.slice(1));
                        $('.simple-folder-list a').removeClass('active');
                        $('.simple-folder-list a[data-folder="' + currentFolder + '"]').addClass('active');

                        loadFolder(currentFolder);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function () {
                    alert('Error creating folder');
                },
                complete: function () {
                    $('#simple-create-folder').prop('disabled', false).text('Create');
                }
            });
        }

        /**
         * Delete file
         */
        function deleteFile(fileName, folder) {
            $.ajax({
                url: simpleFileManagerAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'simple_delete_file',
                    nonce: simpleFileManagerAjax.nonce,
                    file: fileName,
                    folder: folder
                },
                beforeSend: function () {
                    // Add loading indicator if needed
                },
                success: function (response) {
                    if (response.success) {
                        // Reload folder to reflect changes
                        loadFolder(currentFolder);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function () {
                    alert('Error deleting file');
                }
            });
        }

        /**
         * Delete folder
         */
        function deleteFolder(folderName) {
            $.ajax({
                url: simpleFileManagerAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'simple_delete_folder',
                    nonce: simpleFileManagerAjax.nonce,
                    folder_name: folderName
                },
                beforeSend: function () {
                    // Add loading indicator if needed
                },
                success: function (response) {
                    if (response.success) {
                        // Remove folder from list
                        $('.simple-folder-list a[data-folder="' + folderName + '"]').closest('li').remove();

                        // If this was the current folder, switch to another folder
                        if (currentFolder === folderName) {
                            // Get first available folder
                            var firstFolder = $('.simple-folder-list a').first().data('folder');

                            if (firstFolder) {
                                // Instead of triggering a click, manually update the UI
                                $('.simple-folder-list a').removeClass('active');
                                $('.simple-folder-list a[data-folder="' + firstFolder + '"]').addClass('active');

                                // Update current folder
                                currentFolder = firstFolder;
                                $('.simple-current-folder').text(currentFolder.charAt(0).toUpperCase() + currentFolder.slice(1));

                                // Load folder contents directly
                                loadFolder(currentFolder);
                            } else {
                                // No folders left, reload page
                                location.reload();
                            }
                        }
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function () {
                    alert('Error deleting folder');
                }
            });
        }

        /**
         * Rename file
         */
        function renameFile(currentName, newName, folder) {
            $.ajax({
                url: simpleFileManagerAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'simple_rename_file',
                    nonce: simpleFileManagerAjax.nonce,
                    current_name: currentName,
                    new_name: newName,
                    folder: folder
                },
                beforeSend: function () {
                    // Add loading indicator if needed
                },
                success: function (response) {
                    if (response.success) {
                        // Reload folder to reflect changes
                        loadFolder(currentFolder);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function () {
                    alert('Error renaming file');
                }
            });
        }

        /**
         * Rename folder
         */
        function renameFolder(currentName, newName) {
            $.ajax({
                url: simpleFileManagerAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'simple_rename_folder',
                    nonce: simpleFileManagerAjax.nonce,
                    current_name: currentName,
                    new_name: newName
                },
                beforeSend: function () {
                    // Add loading indicator if needed
                },
                success: function (response) {
                    if (response.success) {
                        // Update folder in list
                        var $folderLink = $('.simple-folder-list a[data-folder="' + currentName + '"]');
                        var $parentFolder = $('.simple-folder-list > li > a[data-folder="' + currentName + '"]');
                        var $folderRename = $('.simple-folder-list .simple-folder-rename[data-folder="' + currentName + '"]');

                        $folderLink.data('folder', newName).attr('data-folder', newName);
                        // $folderLink.html('📁 ' + newName.charAt(0).toUpperCase() + newName.slice(1));

                        $folderRename.data('folder', newName).attr('data-folder', newName);

                        // If this was the current folder, update current folder
                        if (currentFolder === currentName) {
                            currentFolder = newName;
                            $parentFolder.html('📁 ' + newName.charAt(0).toUpperCase() + newName.slice(1));
                            $('.simple-current-folder').text(newName.charAt(0).toUpperCase() + newName.slice(1));
                            loadFolder(currentFolder);
                        }
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function () {
                    alert('Error renaming folder');
                }
            });
        }
    });
})(jQuery);