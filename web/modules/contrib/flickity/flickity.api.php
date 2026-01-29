<?php

/**
 * @file
 * Hooks provided by the Flickity module.
 */

/**
 * @defgroup flickity_api Flickity API
 * @{
 * Information about the classes and interfaces that make up the Flickity API.
 *
 * Flickity is a touch responsive gallery created by desandro.
 *
 * The Flickity API allows to act on the Flickity settings, that are defined in
 * content entities "flickity_group" and used to control the Flickity Carousel.
 *
 * There are several flickity-group-related hooks, which allow you to affect
 * the settings:
 * - hook_flickity_group_settings_alter()
 * @}
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the Flickity Group settings.
 *
 * This hook is called after the Flickity Group entity is loaded and allows to
 * alter the settings that controls the Flickity Carousel.
 *
 * This hook can be called ty themes.
 *
 * @param array &$group
 *   The group settings, that are structured as follow:
 *   - id: The group ID.
 *   - label: The group label.
 *   - settings: The settings that are passed to the Flickity JS script.
 *
 * @see flickity_settings()
 *
 * @ingroup flickity_api
 */
function hook_flickity_group_settings_alter(array &$group) {
  if ($group['id'] === 'my_group_id') {
    $group['settings']['arrowShape'] = 'M 0,50 L 60,00 L 50,30 L 80,30 L 80,70 L 50,70 L 60,100 Z';
  }
}

/**
 * @} End of "addtogroup hooks".
 */
