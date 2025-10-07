<?php
/**
 * Plugin Name: React WP API
 * Description: API برای لاگین و تغییر عنوان سایت از طریق پروژه React
 * Version: 1.0
 * Author: Pathan
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {
  remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
  add_filter('rest_pre_serve_request', function($value) {
header('Access-Control-Allow-Origin: http://localhost:5173');

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    return $value;
  });
}, 15);

add_action('rest_api_init', function () {
  register_rest_route('reactwp/v1', '/login', [
    'methods' => 'POST',
    'callback' => 'reactwp_login',
    'permission_callback' => '__return_true'
  ]);

  register_rest_route('reactwp/v1', '/get-title', [
    'methods' => 'GET',
    'callback' => 'reactwp_get_title',
    'permission_callback' => '__return_true'
  ]);

  register_rest_route('reactwp/v1', '/update-title', [
    'methods' => 'POST',
    'callback' => 'reactwp_update_title',
    'permission_callback' => '__return_true'
  ]);
});

// ---- LOGIN ----
function reactwp_login(WP_REST_Request $req)
{
  $params = $req->get_json_params();
  $username = sanitize_text_field($params['username'] ?? '');
  $password = $params['password'] ?? '';

  $user = wp_authenticate($username, $password);

  if (is_wp_error($user)) {
    return new WP_REST_Response(['message' => 'نام کاربری یا رمز عبور اشتباه است'], 401);
  }

  return ['message' => 'ورود موفق', 'user' => $user->user_login];
}

// ---- GET SITE TITLE ----
function reactwp_get_title()
{
  return ['title' => get_bloginfo('name')];
}

// ---- UPDATE TITLE ----
function reactwp_update_title(WP_REST_Request $req)
{
  $params = $req->get_json_params();
  $new_title = sanitize_text_field($params['new_title'] ?? '');

  if (empty($new_title))
    return new WP_REST_Response(['message' => 'عنوان جدید نمی‌تواند خالی باشد'], 400);

  update_option('blogname', $new_title);

  return ['message' => 'عنوان بروزرسانی شد', 'title' => $new_title];
}
