<?php
// Ensure this file is being included by a parent file
if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}
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
        <td><input type="checkbox" name="wp_user_blocking_debug_mode" value="1" <?php checked(1, is_debug_mode(), true); ?> /> (Allows access to wp-admin)</td>
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