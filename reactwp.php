<?php
/**
 * Plugin Name: React WP Connector
 * Description: Provides API endpoints for React app and settings page to manage login credentials.
 * Version: 1.1
 * Author: Pathan Dev
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        // اجازه دسترسی فقط به React
        header('Access-Control-Allow-Origin: http://localhost:5173'); 
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        // برای Preflight OPTIONS
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            status_header(200);
            exit();
        }
        return $value;
    });
}, 15);

// --- REST API routes ---
add_action('rest_api_init', function() {
    register_rest_route('reactwp/v1', '/login', [
        'methods' => 'POST',
        'callback' => 'reactwp_login',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('reactwp/v1', '/update-title', [
        'methods' => 'POST',
        'callback' => 'reactwp_update_title',
        'permission_callback' => 'reactwp_authenticate'
    ]);
});

// --- Login API ---
function reactwp_login(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $username = sanitize_text_field($params['username'] ?? '');
    $password = sanitize_text_field($params['password'] ?? '');

    $stored_user = get_option('reactwp_username');
    $stored_pass = get_option('reactwp_password');

    if ($username === $stored_user && wp_check_password($password, $stored_pass)) {
        $token = base64_encode("$username|" . time());
        update_option('reactwp_token', $token);

        return new WP_REST_Response(['token' => $token, 'status' => 'success'], 200);
    }

    return new WP_REST_Response(['error' => 'Invalid credentials'], 401);
}

// --- Authenticate token ---
function reactwp_authenticate() {
    $headers = getallheaders();
    if (empty($headers['Authorization'])) return false;

    $auth = trim(str_replace('Bearer', '', $headers['Authorization']));
    $stored_token = get_option('reactwp_token');

    return $auth === $stored_token;
}

// --- Update title API ---
function reactwp_update_title(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $new_title = sanitize_text_field($params['title'] ?? '');

    if (!$new_title) {
        return new WP_REST_Response(['error' => 'Missing title'], 400);
    }

    update_option('blogname', $new_title);

    return new WP_REST_Response(['message' => 'Title updated successfully'], 200);
}

// --- Handle CORS ---
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        return $value;
    });
}, 15);

// --- Admin menu page ---
add_action('admin_menu', function() {
    add_menu_page(
        'ReactWP Settings',       // Page title
        'ReactWP',                // Menu title
        'manage_options',         // Capability
        'reactwp-settings',       // Menu slug
        'reactwp_settings_page',  // Callback function
        'dashicons-admin-generic',// Icon
        80                        // Position
    );
});

// --- Settings page ---
function reactwp_settings_page() {
    if (!current_user_can('manage_options')) return;

    $message = '';

    if (isset($_POST['reactwp_submit'])) {
        $new_user = sanitize_text_field($_POST['reactwp_username']);
        $new_pass = sanitize_text_field($_POST['reactwp_password']);

        update_option('reactwp_username', $new_user);
        update_option('reactwp_password', wp_hash_password($new_pass));

        $message = 'Credentials updated successfully!';
    }

    $stored_user = get_option('reactwp_username');
    ?>
    <div class="wrap">
        <h1>ReactWP Settings</h1>
        <?php if ($message) echo "<div style='color:green;margin-bottom:15px;'>$message</div>"; ?>
        <form method="POST">
            <table class="form-table">
                <tr>
                    <th scope="row">Username</th>
                    <td>
                        <input type="text" name="reactwp_username" value="<?php echo esc_attr($stored_user); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Password</th>
                    <td>
                        <input type="password" name="reactwp_password" placeholder="Enter new password" required>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="reactwp_submit" class="button button-primary" value="Save Changes">
            </p>
        </form>
    </div>
    <?php
}
