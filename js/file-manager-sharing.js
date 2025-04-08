/**
 * Simple File Manager Sharing JavaScript
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        // Initialize sharing functionality
        initShareButton();
        initShareModal();
        initSharesList();
    });

    /**
     * Initialize share button
     */
    function initShareButton() {
        // Global share button
        $('.simple-user-info .simple-share').on('click', function (e) {
            e.preventDefault();

            // Get active folder
            var activeFolder = $('.simple-folder-list a.active').data('folder');
            if (!activeFolder) {
                alert('Please select a folder to share');
                return;
            }

            // Fill modal with folder info
            $('#simple-share-type').val('folder');
            $('#simple-share-path').val(activeFolder);
            $('#simple-share-title').text('Share Folder: ' + activeFolder);

            // Show modal
            $('#simple-share-modal').show();
        });

        // Folder context menu share
        $(document).on('click', '.simple-folder-share-option', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var folderName = $(this).data('folder');

            // Close folder options menu
            $(this).closest('.simple-folder-options-menu').removeClass('active');

            // Fill modal with folder info
            $('#simple-share-type').val('folder');
            $('#simple-share-path').val(folderName);
            $('#simple-share-title').text('Share Folder: ' + folderName);

            // Show modal
            $('#simple-share-modal').show();
        });

        // File share button
        $(document).on('click', '.simple-file-share', function (e) {
            e.preventDefault();

            var fileName = $(this).data('file');
            var currentFolder = $('.simple-current-folder').text().trim().toLowerCase();

            // Fill modal with file info
            $('#simple-share-type').val('file');
            $('#simple-share-path').val(currentFolder + '/' + fileName);
            $('#simple-share-title').text('Share File: ' + fileName);

            // Show modal
            $('#simple-share-modal').show();
        });
    }

    /**
     * Initialize share modal
     */
    function initShareModal() {
        // Create share
        $('#simple-create-share-button').on('click', function () {
            var shareType = $('#simple-share-type').val();
            var targetPath = $('#simple-share-path').val();
            var expiryDays = $('#simple-share-expiry').val();
            var password = $('#simple-share-password').val();

            $.ajax({
                url: simpleFileManagerSharing.ajax_url, type: 'POST', data: {
                    action: 'simple_create_share',
                    nonce: simpleFileManagerSharing.nonce,
                    share_type: shareType,
                    target_path: targetPath,
                    expiry_days: expiryDays,
                    password: password
                }, beforeSend: function () {
                    $('#simple-create-share-button').prop('disabled', true).text('Creating...');
                }, success: function (response) {
                    if (response.success) {
                        // Update shares list
                        loadSharesList();

                        // Display share link
                        $('#simple-share-result').html('<div class="simple-share-success">' + '<p>Share link created successfully!</p>' + '<div class="simple-share-url-container">' + '<input type="text" id="simple-share-url" readonly value="' + response.data.share_url + '">' + '<button id="simple-copy-share-url" class="simple-button">Copy</button>' + '</div>' + '</div>');

                        // Initialize copy button
                        $('#simple-copy-share-url').on('click', function () {
                            var urlInput = document.getElementById('simple-share-url');
                            urlInput.select();
                            document.execCommand('copy');
                            $(this).text('Copied!');
                            setTimeout(function () {
                                $('#simple-copy-share-url').text('Copy');
                            }, 2000);
                        });
                    } else {
                        $('#simple-share-result').html('<div class="simple-share-error">' + '<p>Error: ' + response.data.message + '</p>' + '</div>');
                    }
                }, error: function () {
                    $('#simple-share-result').html('<div class="simple-share-error">' + '<p>Error creating share link. Please try again.</p>' + '</div>');
                }, complete: function () {
                    $('#simple-create-share-button').prop('disabled', false).text('Create Share Link');
                }
            });
        });

        // Show manage shares
        $('#simple-manage-shares-button').on('click', function () {
            $('#simple-share-modal').hide();
            $('#simple-shares-list-modal').show();
            loadSharesList();
        });

        // Reset modal on close
        $('.simple-close').on('click', function () {
            if ($(this).closest('.simple-modal').attr('id') === 'simple-share-modal') {
                $('#simple-share-result').empty();
                $('#simple-share-form')[0].reset();
            }
        });
    }

    /**
     * Initialize shares list
     */
    function initSharesList() {
        // Delete share
        $(document).on('click', '.simple-delete-share', function () {
            var shareId = $(this).data('id');

            if (confirm('Are you sure you want to delete this share link? This cannot be undone.')) {
                $.ajax({
                    url: simpleFileManagerSharing.ajax_url, type: 'POST', data: {
                        action: 'simple_delete_share', nonce: simpleFileManagerSharing.nonce, share_id: shareId
                    }, success: function (response) {
                        if (response.success) {
                            loadSharesList();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    }, error: function () {
                        alert('Error deleting share');
                    }
                });
            }
        });

        // Copy share link
        $(document).on('click', '.simple-copy-share', function () {
            var shareUrl = $(this).data('url');

            // Create a temporary input element
            var tempInput = document.createElement('input');
            tempInput.value = shareUrl;
            document.body.appendChild(tempInput);

            // Select and copy
            tempInput.select();
            document.execCommand('copy');

            // Remove the temporary element
            document.body.removeChild(tempInput);

            // Update button text
            var originalText = $(this).text();
            $(this).text('Copied!');

            // Reset after a delay
            var $button = $(this);
            setTimeout(function () {
                $button.text(originalText);
            }, 2000);
        });
    }

    /**
     * Load shares list
     */
    function loadSharesList() {
        $.ajax({
            url: simpleFileManagerSharing.ajax_url, type: 'POST', data: {
                action: 'simple_list_shares', nonce: simpleFileManagerSharing.nonce
            }, beforeSend: function () {
                $('#simple-shares-list').html('<p>Loading shares...</p>');
            }, success: function (response) {
                if (response.success) {
                    displaySharesList(response.data.shares);
                } else {
                    $('#simple-shares-list').html('<p>Error: ' + response.data.message + '</p>');
                }
            }, error: function () {
                $('#simple-shares-list').html('<p>Error loading shares</p>');
            }
        });
    }

    /**
     * Display shares list
     */
    function displaySharesList(shares) {
        if (shares.length === 0) {
            $('#simple-shares-list').html('<p>You haven\'t created any share links yet.</p>');
            return;
        }

        var html = '<table class="simple-shares-table">' + '<thead>' + '<tr>' + '<th>Type</th>' + '<th>Path</th>' + '<th>Created</th>' + '<th>Expires</th>' + '<th>Password</th>' + '<th>Status</th>' + '<th>Actions</th>' + '</tr>' + '</thead>' + '<tbody>';

        $.each(shares, function (index, share) {
            html += '<tr' + (!share.is_active ? ' class="inactive"' : '') + '>' + '<td>' + (share.type === 'folder' ? '📁' : '📄') + ' ' + share.type + '</td>' + '<td>' + share.path + '</td>' + '<td>' + formatDate(share.created) + '</td>' + '<td>' + (share.expires ? formatDate(share.expires) : 'Never') + '</td>' + '<td>' + (share.has_password ? 'Yes' : 'No') + '</td>' + '<td>' + (share.is_active ? 'Active' : 'Inactive') + '</td>' + '<td class="share-actions">' + '<button class="simple-copy-share" data-url="' + share.url + '">Copy Link</button>' + (share.is_active ? '<button class="simple-delete-share" data-id="' + share.id + '">Delete</button>' : '<span class="deleted-label">Deleted</span>') + '</td>' + '</tr>';
        });

        html += '</tbody></table>';

        $('#simple-shares-list').html(html);
    }

    /**
     * Format date
     */
    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    $('.file-preview img').on('click', function () {
        // Get the download URL from the parent file item
        var downloadUrl = $(this).closest('.simple-shared-file-item').find('.file-download').attr('href');

        // Open the image in a new tab
        if (downloadUrl) {
            window.open(downloadUrl, '_blank');
        }
    }).css('cursor', 'pointer');

    // Add hover effects to file items
    $('.simple-shared-file-item, .simple-shared-folder-item').hover(function () {
        $(this).addClass('item-hover');
    }, function () {
        $(this).removeClass('item-hover');
    });

    // Smooth scrolling for breadcrumb navigation
    $('.simple-shared-breadcrumb a').on('click', function (e) {
        // Don't prevent default as we need the link to work normally

        // Get the target element (the shared view container)
        var $target = $('.simple-shared-view');

        // Scroll to it smoothly if it exists
        if ($target.length) {
            $('html, body').animate({
                scrollTop: $target.offset().top - 50
            }, 300);
        }
    });

    // Make entire folder item clickable
    $('.simple-shared-folder-item').on('click', function (e) {
        // If the click wasn't on the actual link
        if (!$(e.target).is('a') && !$(e.target).is('span')) {
            // Get the link inside this folder item and follow it
            var $link = $(this).find('.simple-shared-folder-link');
            if ($link.length) {
                window.location = $link.attr('href');
            }
        }
    }).css('cursor', 'pointer');

    // Password form enhancements
    $('.simple-shared-password-form form').on('submit', function () {
        // Add a loading state to the form
        $(this).addClass('submitting');
        $(this).find('button').text('Verifying...').prop('disabled', true);
    });

    // Make file items open the download when clicked on the preview area
    $('.file-preview').on('click', function () {
        var $downloadLink = $(this).closest('.simple-shared-file-item').find('.file-download');
        if ($downloadLink.length) {
            window.open($downloadLink.attr('href'), '_blank');
        }
    }).css('cursor', 'pointer');

})(jQuery);