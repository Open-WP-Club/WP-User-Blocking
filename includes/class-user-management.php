<?php
class User_Management
{
  public static function init()
  {
    add_filter('user_row_actions', array(__CLASS__, 'add_user_row_action'), 10, 2);
    add_action('admin_action_block_user', array(__CLASS__, 'block_user_action'));
    add_action('admin_action_unblock_user', array(__CLASS__, 'unblock_user_action'));
    add_filter('bulk_actions-users', array(__CLASS__, 'add_bulk_actions'));
    add_filter('handle_bulk_actions-users', array(__CLASS__, 'handle_bulk_actions'), 10, 3);
    add_filter('editable_roles', array(__CLASS__, 'remove_blocked_role_from_list'));
  }

  public static function add_user_row_action($actions, $user_object)
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

  public static function block_user_action()
  {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    if ($user_id && wp_verify_nonce($_GET['_wpnonce'], 'block_user_' . $user_id)) {
      block_user($user_id);
      wp_redirect(admin_url('users.php?blocked=1'));
      exit;
    }
    wp_redirect(admin_url('users.php?blocked=0'));
    exit;
  }

  public static function unblock_user_action()
  {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    if ($user_id && wp_verify_nonce($_GET['_wpnonce'], 'unblock_user_' . $user_id)) {
      unblock_user($user_id);
      wp_redirect(admin_url('users.php?unblocked=1'));
      exit;
    }
    wp_redirect(admin_url('users.php?unblocked=0'));
    exit;
  }

  public static function add_bulk_actions($bulk_actions)
  {
    $bulk_actions['block_users'] = __('Block Users', 'wp-user-blocking');
    $bulk_actions['unblock_users'] = __('Unblock Users', 'wp-user-blocking');
    return $bulk_actions;
  }

  public static function handle_bulk_actions($redirect_to, $doaction, $user_ids)
  {
    if ($doaction === 'block_users') {
      $blocked = 0;
      foreach ($user_ids as $user_id) {
        if (block_user($user_id)) {
          $blocked++;
        }
      }
      $redirect_to = add_query_arg('blocked', $blocked, $redirect_to);
    } elseif ($doaction === 'unblock_users') {
      $unblocked = 0;
      foreach ($user_ids as $user_id) {
        if (unblock_user($user_id)) {
          $unblocked++;
        }
      }
      $redirect_to = add_query_arg('unblocked', $unblocked, $redirect_to);
    }
    return $redirect_to;
  }

  public static function remove_blocked_role_from_list($roles)
  {
    if (isset($roles['blocked'])) {
      unset($roles['blocked']);
    }
    return $roles;
  }
}

User_Management::init();
