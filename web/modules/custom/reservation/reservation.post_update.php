<?php

/**
 * @file
 * Post update functions for the reservation module.
 */

/**
 * Implements hook_removed_post_updates().
 */
function reservation_removed_post_updates() {
  return [
    'reservation_post_update_enable_reservation_admin_view' => '9.0.0',
    'reservation_post_update_add_ip_address_setting' => '9.0.0',
  ];
}
