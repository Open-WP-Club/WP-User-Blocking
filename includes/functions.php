<?php
function is_debug_mode()
{
  if (defined('WP_USER_BLOCKING_DEBUG') && WP_USER_BLOCKING_DEBUG) {
    return true;
  }
  return get_option('wp_user_blocking_debug_mode') == 1;
}

function get_blocked_emails()
{
  $blocked_emails = get_option('wp_user_blocking_emails', '');
  $emails = explode("\n", $blocked_emails);
  return array_map('trim', array_filter($emails));
}

function get_blocked_ips()
{
  $blocked_ips = get_option('wp_user_blocking_ips', '');
  $ips = explode("\n", $blocked_ips);
  return array_map('trim', array_filter($ips));
}

function block_user($user_id)
{
  $user = get_userdata($user_id);
  if ($user) {
    $blocked_emails = get_blocked_emails();
    if (!in_array($user->user_email, $blocked_emails)) {
      $blocked_emails[] = $user->user_email;
      $blocked_emails_string = implode("\n", array_filter($blocked_emails));
      update_option('wp_user_blocking_emails', trim($blocked_emails_string));

      // Remove all existing roles and capabilities
      $user->set_role('');

      // Assign the 'blocked' role
      $user->add_role('blocked');

      return true;
    }
  }
  return false;
}

function unblock_user($user_id)
{
  $user = get_userdata($user_id);
  if ($user) {
    // Remove the 'blocked' role
    $user->remove_role('blocked');

    // Assign the default role (usually 'subscriber')
    $user->set_role(get_option('default_role', 'subscriber'));

    // Remove the email from the blocked list
    $blocked_emails = get_blocked_emails();
    $blocked_emails = array_diff($blocked_emails, array($user->user_email));
    $blocked_emails_string = implode("\n", $blocked_emails);
    update_option('wp_user_blocking_emails', trim($blocked_emails_string));

    return true;
  }
  return false;
}

function block_users_by_email($emails)
{
  $users = get_users();
  foreach ($users as $user) {
    if (in_array($user->user_email, $emails)) {
      $user->set_role('');
      $user->add_role('blocked');
    }
  }
}

function add_debug_info()
{
  if (!current_user_can('manage_options') || !WP_DEBUG) {
    return;
  }

  $user = wp_get_current_user();
  $user_email = $user->user_email;
  $user_ip = $_SERVER['REMOTE_ADDR'];
  $blocked_emails = get_blocked_emails();
  $blocked_ips = get_blocked_ips();
  $block_admins = get_option('wp_user_blocking_block_admins') == 1;
  $is_admin = in_array('administrator', $user->roles);

  echo "<!-- WP User Blocking Debug:\n";
  echo "Current user email: " . esc_html($user_email) . "\n";
  echo "Current user IP: " . esc_html($user_ip) . "\n";
  echo "Blocked emails: " . esc_html(implode(', ', $blocked_emails)) . "\n";
  echo "Blocked IPs: " . esc_html(implode(', ', $blocked_ips)) . "\n";
  echo "User should be blocked (email): " . (in_array($user_email, $blocked_emails) ? 'Yes' : 'No') . "\n";
  echo "User should be blocked (IP): " . (in_array($user_ip, $blocked_ips) ? 'Yes' : 'No') . "\n";
  echo "User is admin: " . ($is_admin ? 'Yes' : 'No') . "\n";
  echo "Block admins setting: " . ($block_admins ? 'ON' : 'OFF') . "\n";
  echo "Debug mode: " . (is_debug_mode() ? 'ON' : 'OFF') . "\n";
  echo "-->";
}

// Add the debug info to the footer
add_action('wp_footer', 'add_debug_info');
