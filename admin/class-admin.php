<?php
class User_Blocking_Admin
{
  public function run()
  {
    add_action('admin_menu', array($this, 'add_admin_menu'));
    add_action('admin_init', array($this, 'register_settings'));
    add_filter('plugin_action_links_' . plugin_basename(plugin_dir_path(__DIR__) . 'wp-user-blocking.php'), array($this, 'add_plugin_action_links'));
    add_action('admin_post_export_blocked_emails', array($this, 'export_blocked_emails'));
    add_action('admin_post_import_blocked_emails', array($this, 'import_blocked_emails'));
    add_action('admin_notices', array($this, 'admin_notices'));
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

  public function settings_page()
  {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    include plugin_dir_path(__FILE__) . 'views/settings-page.php';
  }

  public function add_plugin_action_links($links)
  {
    $settings_link = '<a href="' . admin_url('users.php?page=wp-user-blocking') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
  }

  public function export_blocked_emails()
  {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

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

  public function import_blocked_emails()
  {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    if (isset($_FILES['blocked_emails_file']) && $_FILES['blocked_emails_file']['error'] == UPLOAD_ERR_OK) {
      $file = $_FILES['blocked_emails_file'];

      if (pathinfo($file['name'], PATHINFO_EXTENSION) != 'csv') {
        wp_die('Please upload a CSV file.');
      }

      $file_handle = fopen($file['tmp_name'], 'r');
      if ($file_handle === false) {
        wp_die('Unable to open the uploaded file.');
      }

      $emails = array();
      $row = 0;
      while (($data = fgetcsv($file_handle, 1000, ",")) !== FALSE) {
        $row++;
        if ($row == 1) {
          // Skip the header row
          continue;
        }
        if (isset($data[0]) && is_email($data[0])) {
          $emails[] = sanitize_email($data[0]);
        }
      }
      fclose($file_handle);

      if (empty($emails)) {
        wp_die('No valid email addresses found in the CSV file.');
      }

      $current_emails = get_blocked_emails();
      $merged_emails = array_unique(array_merge($current_emails, $emails));
      $merged_emails_string = implode("\n", $merged_emails);

      update_option('wp_user_blocking_emails', trim($merged_emails_string));

      block_users_by_email($merged_emails);

      wp_redirect(admin_url('users.php?page=wp-user-blocking&import=success'));
      exit;
    } else {
      wp_die('There was an error uploading the file. Please try again.');
    }
  }

  public function admin_notices()
  {
    if (isset($_GET['blocked'])) {
      if ($_GET['blocked'] == 1) {
        echo '<div class="notice notice-success is-dismissible"><p>User has been blocked successfully.</p></div>';
      } elseif ($_GET['blocked'] == 0) {
        echo '<div class="notice notice-error is-dismissible"><p>Failed to block user. Please try again.</p></div>';
      } else {
        $blocked = intval($_GET['blocked']);
        echo '<div class="notice notice-success is-dismissible"><p>' . $blocked . ' user(s) have been blocked successfully.</p></div>';
      }
    }

    if (isset($_GET['unblocked'])) {
      if ($_GET['unblocked'] == 1) {
        echo '<div class="notice notice-success is-dismissible"><p>User has been unblocked successfully.</p></div>';
      } elseif ($_GET['unblocked'] == 0) {
        echo '<div class="notice notice-error is-dismissible"><p>Failed to unblock user. Please try again.</p></div>';
      } else {
        $unblocked = intval($_GET['unblocked']);
        echo '<div class="notice notice-success is-dismissible"><p>' . $unblocked . ' user(s) have been unblocked successfully.</p></div>';
      }
    }

    if (isset($_GET['import']) && $_GET['import'] == 'success') {
      echo '<div class="notice notice-success is-dismissible"><p>Blocked emails have been imported successfully and associated users have been blocked.</p></div>';
    }
  }
}
