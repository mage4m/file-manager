<?php
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

        /* Password form */
        .simple-shared-password-form {
            max-width: 500px;
            margin: 30px auto;
            padding: 30px;
            background-color: #f9f9f9;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .simple-button {
            display: inline-block;
            background-color: #0073aa;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 16px;
            border: none;
            cursor: pointer;
        }

        .simple-button:hover {
            background-color: #005177;
        }

        /* Error message */
        .simple-shared-error {
            background-color: #f9f9f9;
            border-left: 4px solid #dc3232;
            padding: 15px;
            margin: 20px 0;
        }
    </style>

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