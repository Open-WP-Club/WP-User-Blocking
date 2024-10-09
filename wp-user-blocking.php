<?php

/**
 * Plugin Name: WP User Blocking
 * Plugin URI: https://openwpclub.com
 * Description: A plugin to block users from entering the website with customizable message and email export/import functionality.
 * Version: 1.0.0
 * Author: OpenWPclub.com
 * Author URI: https://openwpclub.com
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
  exit;
}

class WP_User_Blocking
{
  public function __construct()
  {
    add_action('init', array($this, 'init'));
    add_action('admin_menu', array($this, 'add_admin_menu'));
    add_action('admin_init', array($this, 'register_settings'));
    add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    register_activation_hook(__FILE__, array($this, 'activate'));
  }

  public function activate()
  {
    $default_message = "Access to this site has been restricted. If you believe this is an error, please contact the site administrator.";
    add_option('wp_user_blocking_message', $default_message);
  }

  public function init()
  {
    add_action('wp', array($this, 'block_users'));
  }

  public function add_admin_menu()
  {
    add_users_page(
      'WP User Blocking',
      'User Blocking',
      'manage_options',
      'wp-user-blocking',
      array($this, 'settings_page')
    );
  }

  public function register_settings()
  {
    register_setting('wp_user_blocking_options', 'wp_user_blocking_message');
    register_setting('wp_user_blocking_options', 'wp_user_blocking_emails');
    register_setting('wp_user_blocking_options', 'wp_user_blocking_ips');
    register_setting('wp_user_blocking_options', 'wp_user_blocking_button_email');
    register_setting('wp_user_blocking_options', 'wp_user_blocking_debug_mode');
    register_setting('wp_user_blocking_options', 'wp_user_blocking_block_admins');
  }

  public function enqueue_styles()
  {
    wp_enqueue_style('wp-user-blocking', plugins_url('assets/css/wp-user-blocking.css', __FILE__));
  }

  public function settings_page()
  {
?>
    <div class="wrap">
      <h1>WP User Blocking Settings</h1>
      <form method="post" action="options.php">
        <?php
        settings_fields('wp_user_blocking_options');
        do_settings_sections('wp_user_blocking_options');
        ?>
        <table class="form-table">
          <tr valign="top">
            <th scope="row">Block Message</th>
            <td><textarea name="wp_user_blocking_message" rows="5" cols="50"><?php echo esc_textarea(get_option('wp_user_blocking_message')); ?></textarea></td>
          </tr>
          <tr valign="top">
            <th scope="row">Blocked Emails (one per line)</th>
            <td><textarea name="wp_user_blocking_emails" rows="10" cols="50"><?php echo esc_textarea(get_option('wp_user_blocking_emails')); ?></textarea></td>
          </tr>
          <tr valign="top">
            <th scope="row">Blocked IPs (one per line)</th>
            <td><textarea name="wp_user_blocking_ips" rows="10" cols="50"><?php echo esc_textarea(get_option('wp_user_blocking_ips')); ?></textarea></td>
          </tr>
          <tr valign="top">
            <th scope="row">Button Email</th>
            <td><input type="email" name="wp_user_blocking_button_email" value="<?php echo esc_attr(get_option('wp_user_blocking_button_email')); ?>" /></td>
          </tr>
          <tr valign="top">
            <th scope="row">Debug Mode</th>
            <td><input type="checkbox" name="wp_user_blocking_debug_mode" value="1" <?php checked(1, $this->is_debug_mode(), true); ?> /> (Allows access to wp-admin)</td>
          </tr>
          <tr valign="top">
            <th scope="row">Block Admins</th>
            <td><input type="checkbox" name="wp_user_blocking_block_admins" value="1" <?php checked(1, get_option('wp_user_blocking_block_admins'), true); ?> /> (Enable blocking for admin users)</td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
      <h2>Export/Import Emails</h2>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="export_blocked_emails">
        <?php submit_button('Export Blocked Emails', 'secondary'); ?>
      </form>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import_blocked_emails">
        <input type="file" name="blocked_emails_file" accept=".csv">
        <?php submit_button('Import Blocked Emails', 'secondary'); ?>
      </form>
    </div>
  <?php
  }

  public function block_users()
  {
    $user = wp_get_current_user();
    $user_email = $user->user_email;
    $user_ip = $_SERVER['REMOTE_ADDR'];
    $blocked_emails = explode("\n", get_option('wp_user_blocking_emails'));
    $blocked_emails = array_map('trim', $blocked_emails);
    $blocked_ips = explode("\n", get_option('wp_user_blocking_ips'));
    $blocked_ips = array_map('trim', $blocked_ips);
    $block_admins = get_option('wp_user_blocking_block_admins') == 1;
    $is_admin = in_array('administrator', $user->roles);
    $is_debug_mode = $this->is_debug_mode();

    if ((in_array($user_email, $blocked_emails) || in_array($user_ip, $blocked_ips)) && ($block_admins || !$is_admin)) {
      if ($is_debug_mode && is_admin()) {
        // Allow access to wp-admin in debug mode
        return;
      }
      $this->display_block_page();
      exit;
    }
  }

  public function display_block_page()
  {
    $message = get_option('wp_user_blocking_message');
    $button_email = get_option('wp_user_blocking_button_email');

  ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Access Blocked</title>
      <?php wp_head(); ?>
    </head>

    <body class="wp-user-blocking-page">
      <div class="wp-user-blocking-container">
        <?php
        $site_icon_url = get_site_icon_url(80);
        if ($site_icon_url):
        ?>
          <img src="<?php echo esc_url($site_icon_url); ?>" alt="Site Icon" class="wp-user-blocking-logo">
        <?php endif; ?>
        <div class="wp-user-blocking-message">
          <?php echo wp_kses_post($message); ?>
        </div>
        <?php if ($button_email): ?>
          <a href="mailto:<?php echo esc_attr($button_email); ?>" class="wp-user-blocking-button">Contact Us</a>
        <?php endif; ?>
      </div>
      <?php wp_footer(); ?>
    </body>

    </html>
<?php
    exit;
  }

  public function is_debug_mode()
  {
    if (defined('WP_USER_BLOCKING_DEBUG') && WP_USER_BLOCKING_DEBUG) {
      return true;
    }
    return get_option('wp_user_blocking_debug_mode') == 1;
  }
}

$wp_user_blocking = new WP_User_Blocking();

// Add export/import functionality
add_action('admin_post_export_blocked_emails', 'export_blocked_emails');
add_action('admin_post_import_blocked_emails', 'import_blocked_emails');

function export_blocked_emails()
{
  $blocked_emails = get_option('wp_user_blocking_emails');
  $filename = 'blocked_emails_' . date('Y-m-d') . '.csv';

  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="' . $filename . '"');

  $output = fopen('php://output', 'w');
  fputcsv($output, array('Blocked Emails'));

  $emails = explode("\n", $blocked_emails);
  foreach ($emails as $email) {
    fputcsv($output, array(trim($email)));
  }

  fclose($output);
  exit;
}

function import_blocked_emails()
{
  if (isset($_FILES['blocked_emails_file']) && $_FILES['blocked_emails_file']['error'] == UPLOAD_ERR_OK) {
    $file = $_FILES['blocked_emails_file'];

    // Check if the file is a CSV
    $file_info = pathinfo($file['name']);
    if ($file_info['extension'] != 'csv') {
      wp_die('Please upload a CSV file.');
    }

    // Read the file contents
    $file_contents = file_get_contents($file['tmp_name']);
    if ($file_contents === false) {
      wp_die('Unable to read the uploaded file.');
    }

    $emails = explode("\n", $file_contents);
    $emails = array_map('trim', $emails);
    $emails = array_filter($emails);

    $current_emails = explode("\n", get_option('wp_user_blocking_emails'));
    $current_emails = array_map('trim', $current_emails);
    $current_emails = array_filter($current_emails);

    $merged_emails = array_unique(array_merge($current_emails, $emails));
    $merged_emails_string = implode("\n", $merged_emails);

    update_option('wp_user_blocking_emails', $merged_emails_string);

    wp_redirect(admin_url('users.php?page=wp-user-blocking&import=success'));
    exit;
  } else {
    wp_die('There was an error uploading the file. Please try again.');
  }
}

// Additional debugging information
add_action('wp_footer', function () {
  $user = wp_get_current_user();
  $user_email = $user->user_email;
  $user_ip = $_SERVER['REMOTE_ADDR'];
  $blocked_emails = explode("\n", get_option('wp_user_blocking_emails'));
  $blocked_emails = array_map('trim', $blocked_emails);
  $blocked_ips = explode("\n", get_option('wp_user_blocking_ips'));
  $blocked_ips = array_map('trim', $blocked_ips);
  $block_admins = get_option('wp_user_blocking_block_admins') == 1;
  $is_admin = in_array('administrator', $user->roles);
  $wp_user_blocking = new WP_User_Blocking();

  echo "<!-- WP User Blocking Debug:\n";
  echo "Current user email: " . esc_html($user_email) . "\n";
  echo "Current user IP: " . esc_html($user_ip) . "\n";
  echo "Blocked emails: " . esc_html(implode(', ', $blocked_emails)) . "\n";
  echo "Blocked IPs: " . esc_html(implode(', ', $blocked_ips)) . "\n";
  echo "User should be blocked (email): " . (in_array($user_email, $blocked_emails) ? 'Yes' : 'No') . "\n";
  echo "User should be blocked (IP): " . (in_array($user_ip, $blocked_ips) ? 'Yes' : 'No') . "\n";
  echo "User is admin: " . ($is_admin ? 'Yes' : 'No') . "\n";
  echo "Block admins setting: " . ($block_admins ? 'ON' : 'OFF') . "\n";
  echo "Debug mode: " . ($wp_user_blocking->is_debug_mode() ? 'ON' : 'OFF') . "\n";
  echo "-->";
});
