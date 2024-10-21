<?php
/**
 * Plugin Name: WP User Blocking
 * Plugin URI: https://openwpclub.com
 * Description: A plugin to block users from entering the website with customizable message
 * Version: 1.1.0
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
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
        add_filter('user_row_actions', array($this, 'add_user_row_action'), 10, 2);
        add_action('admin_action_block_user', array($this, 'block_user_action'));
        add_action('admin_action_unblock_user', array($this, 'unblock_user_action'));
        add_filter('bulk_actions-users', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-users', array($this, 'handle_bulk_actions'), 10, 3);
        add_action('init', array($this, 'add_blocked_role'));
        add_filter('editable_roles', array($this, 'remove_blocked_role_from_list'));
        add_action('admin_post_import_blocked_emails', array($this, 'import_blocked_emails'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    public function activate()
    {
        $default_message = "Access to this site has been restricted. If you believe this is an error, please contact the site administrator.";
        add_option('wp_user_blocking_message', $default_message);
        $this->add_blocked_role();
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
        if ($this->is_debug_mode()) {
          return; // Don't block if in debug mode
        }

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
            
            // If the user is not already blocked, update their role
            if (!in_array('blocked', $user->roles)) {
                $user->set_role('');
                $user->add_role('blocked');
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

    public function add_plugin_action_links($links)
    {
        $settings_link = '<a href="' . admin_url('users.php?page=wp-user-blocking') . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function add_user_row_action($actions, $user_object)
    {
        if (current_user_can('manage_options')) {
            $is_blocked = in_array('blocked', $user_object->roles);
            if ($is_blocked) {
                $unblock_link = wp_nonce_url(admin_url("users.php?action=unblock_user&user_id={$user_object->ID}"), 'unblock_user_' . $user_object->ID);
                $actions['unblock_user'] = "<a href='{$unblock_link}' class='unblock_user'>Unblock User</a>";
            } else {
                $block_link = wp_nonce_url(admin_url("users.php?action=block_user&user_id={$user_object->ID}"), 'block_user_' . $user_object->ID);
                $actions['block_user'] = "<a href='{$block_link}' class='block_user'>Block User</a>";
            }
        }
        return $actions;
    }

    public function block_user_action()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        if ($user_id && wp_verify_nonce($_GET['_wpnonce'], 'block_user_' . $user_id)) {
            $this->block_user($user_id);
            wp_redirect(admin_url('users.php?blocked=1'));
            exit;
        }
        wp_redirect(admin_url('users.php?blocked=0'));
        exit;
    }

    public function unblock_user_action()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        if ($user_id && wp_verify_nonce($_GET['_wpnonce'], 'unblock_user_' . $user_id)) {
            $this->unblock_user($user_id);
            wp_redirect(admin_url('users.php?unblocked=1'));
            exit;
        }
        wp_redirect(admin_url('users.php?unblocked=0'));
        exit;
    }

    public function add_bulk_actions($bulk_actions)
    {
        $bulk_actions['block_users'] = __('Block Users', 'wp-user-blocking');
        $bulk_actions['unblock_users'] = __('Unblock Users', 'wp-user-blocking');
        return $bulk_actions;
    }

    public function handle_bulk_actions($redirect_to, $doaction, $user_ids)
    {
        if ($doaction === 'block_users') {
            $blocked = 0;
            foreach ($user_ids as $user_id) {
                if ($this->block_user($user_id)) {
                    $blocked++;
                }
            }
            $redirect_to = add_query_arg('blocked', $blocked, $redirect_to);
        } elseif ($doaction === 'unblock_users') {
            $unblocked = 0;
            foreach ($user_ids as $user_id) {
                if ($this->unblock_user($user_id)) {
                    $unblocked++;
                }
            }
            $redirect_to = add_query_arg('unblocked', $unblocked, $redirect_to);
        }
        return $redirect_to;
    }

    private function block_user($user_id)
    {
        $user = get_userdata($user_id);
        if ($user) {
            $blocked_emails = get_option('wp_user_blocking_emails', '');
            $emails = explode("\n", $blocked_emails);
            if (!in_array($user->user_email, $emails)) {
                $emails[] = $user->user_email;
                $blocked_emails = implode("\n", array_filter($emails));
                update_option('wp_user_blocking_emails', trim($blocked_emails));
                
                // Remove all existing roles and capabilities
                $user->set_role('');
                
                // Assign the 'blocked' role
                $user->add_role('blocked');
                
                return true;
            }
        }
        return false;
    }

  private function unblock_user($user_id)
  {
    $user = get_userdata($user_id);
    if ($user) {
      // Remove the 'blocked' role
      $user->remove_role('blocked');

      // Assign the default role (usually 'subscriber')
      $user->set_role(get_option('default_role', 'subscriber'));

      // Remove the email from the blocked list
      $blocked_emails = get_option('wp_user_blocking_emails', '');
      $emails = explode("\n", $blocked_emails);
      $emails = array_filter($emails, function ($email) use ($user) {
        return trim($email) !== $user->user_email;
      });
      $blocked_emails = implode("\n", $emails);
      update_option('wp_user_blocking_emails', trim($blocked_emails));

      return true;
    }
    return false;
  }

  public function add_blocked_role()
  {
    add_role(
      'blocked',
      __('Blocked', 'wp-user-blocking'),
      array(
        'read' => false,
        'edit_posts' => false,
        'delete_posts' => false,
      )
    );
  }

  public function remove_blocked_role_from_list($roles)
  {
    if (isset($roles['blocked'])) {
      unset($roles['blocked']);
    }
    return $roles;
  }

  public function import_blocked_emails()
  {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

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

      $current_emails = explode("\n", get_option('wp_user_blocking_emails', ''));
      $current_emails = array_map('trim', $current_emails);
      $current_emails = array_filter($current_emails);

      $merged_emails = array_unique(array_merge($current_emails, $emails));
      $merged_emails_string = implode("\n", $merged_emails);

      update_option('wp_user_blocking_emails', trim($merged_emails_string));

      // Block users with imported email addresses
      $users = get_users();
      foreach ($users as $user) {
        if (in_array($user->user_email, $merged_emails)) {
          $user->set_role('');
          $user->add_role('blocked');
        }
      }

      wp_redirect(admin_url('users.php?page=wp-user-blocking&import=success'));
      exit;
    } else {
      wp_die('There was an error uploading the file. Please try again.');
    }
  }
}

$wp_user_blocking = new WP_User_Blocking();

// Add export functionality
add_action('admin_post_export_blocked_emails', 'export_blocked_emails');

function export_blocked_emails()
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

// Add admin notice for successful user blocking, unblocking and email import
add_action('admin_notices', function () {
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
});

// Additional debugging information
add_action('wp_footer', function () {
  if (!current_user_can('manage_options') || !WP_DEBUG) {
    return;
  }

  $user = wp_get_current_user();
  $user_email = $user->user_email;
  $user_ip = $_SERVER['REMOTE_ADDR'];
  $blocked_emails = explode("\n", get_option('wp_user_blocking_emails', ''));
  $blocked_emails = array_map('trim', $blocked_emails);
  $blocked_ips = explode("\n", get_option('wp_user_blocking_ips', ''));
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