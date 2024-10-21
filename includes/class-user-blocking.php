<?php
class User_Blocking
{
  public function run()
  {
    add_action('init', array($this, 'init'));
    add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    add_action('wp', array($this, 'block_users'));
  }

  public function init()
  {
    $this->add_blocked_role();
  }

  public function enqueue_styles()
  {
    wp_enqueue_style('wp-user-blocking', plugins_url('assets/css/wp-user-blocking.css', dirname(__FILE__)));
  }

  public function block_users()
  {
    if (is_debug_mode()) {
      return;
    }

    $user = wp_get_current_user();
    $user_email = $user->user_email;
    $user_ip = $_SERVER['REMOTE_ADDR'];
    $blocked_emails = get_blocked_emails();
    $blocked_ips = get_blocked_ips();
    $block_admins = get_option('wp_user_blocking_block_admins') == 1;
    $is_admin = in_array('administrator', $user->roles);

    if ((in_array($user_email, $blocked_emails) || in_array($user_ip, $blocked_ips)) && ($block_admins || !$is_admin)) {
      if (is_debug_mode() && is_admin()) {
        return;
      }

      if (!in_array('blocked', $user->roles)) {
        $user->set_role('');
        $user->add_role('blocked');
      }

      $this->display_block_page();
      exit;
    }
  }

  private function display_block_page()
  {
    $message = get_option('wp_user_blocking_message');
    $button_email = get_option('wp_user_blocking_button_email');

    include plugin_dir_path(__FILE__) . '../templates/blocked-page.php';
    exit;
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
}
