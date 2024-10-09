<?php

/**
 * Plugin Name: WP User Blocking
 * Plugin URI: https://openwpclub.com
 * Description: A plugin to block users from entering the website with customizable message and email export/import functionality.
 * Version: 1.0
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
  }

  public function init()
  {
    if (!is_admin() && !$this->is_debug_mode()) {
      add_action('template_redirect', array($this, 'block_users'));
    }
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
    register_setting('wp_user_blocking_options', 'wp_user_blocking_logo');
    register_setting('wp_user_blocking_options', 'wp_user_blocking_button_email');
    register_setting('wp_user_blocking_options', 'wp_user_blocking_debug_mode');
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
            <th scope="row">Logo URL</th>
            <td><input type="text" name="wp_user_blocking_logo" value="<?php echo esc_attr(get_option('wp_user_blocking_logo')); ?>" /></td>
          </tr>
          <tr valign="top">
            <th scope="row">Button Email</th>
            <td><input type="email" name="wp_user_blocking_button_email" value="<?php echo esc_attr(get_option('wp_user_blocking_button_email')); ?>" /></td>
          </tr>
          <tr valign="top">
            <th scope="row">Debug Mode</th>
            <td><input type="checkbox" name="wp_user_blocking_debug_mode" value="1" <?php checked(1, get_option('wp_user_blocking_debug_mode'), true); ?> /></td>
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
    $user_email = wp_get_current_user()->user_email;
    $blocked_emails = explode("\n", get_option('wp_user_blocking_emails'));
    $blocked_emails = array_map('trim', $blocked_emails);

    if (in_array($user_email, $blocked_emails)) {
      $this->display_block_page();
      exit;
    }
  }

  public function display_block_page()
  {
    $logo_url = get_option('wp_user_blocking_logo');
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
        <?php if ($logo_url): ?>
          <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" class="wp-user-blocking-logo">
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
  }

  public function is_debug_mode()
  {
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
  if (isset($_FILES['blocked_emails_file'])) {
    $file = $_FILES['blocked_emails_file'];
    $file_contents = file_get_contents($file['tmp_name']);
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
  }
}
