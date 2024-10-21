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

require_once plugin_dir_path(__FILE__) . 'includes/class-user-blocking.php';
require_once plugin_dir_path(__FILE__) . 'admin/class-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-user-management.php';
require_once plugin_dir_path(__FILE__) . 'includes/functions.php';

function run_wp_user_blocking()
{
  $plugin = new User_Blocking();
  $plugin->run();

  if (is_admin()) {
    $admin = new User_Blocking_Admin();
    $admin->run();
  }
}

run_wp_user_blocking();
