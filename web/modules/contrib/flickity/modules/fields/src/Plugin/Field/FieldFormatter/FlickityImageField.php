<?php
/**
 * @file
 * Contains \Drupal\flickity_fields\Plugin\Field\FieldFormatter\FlickityEntityView
 */

namespace Drupal\flickity_fields\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

/**
 * Plugin implementation of the 'flickity entity view' formatter.
 *
 * @FieldFormatter(
 *   id = "flickity_image_field",
 *   label = @Translation("Flickity"),
 *   description = @Translation("Display images in a Flickity carousel."),
 *   field_types = {
 *     "image"
 *   }
 * )
 */

class FlickityImageField extends ImageFormatter {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return array(
      'flickity_settings' => array(
        '#title' => $this->t('Flickity settings'),
        '#type' => 'select',
        '#options' => flickity_settings_list(),
        '#default_value' => $this->getSetting('flickity_settings'),
        '#required' => TRUE,
      )
    ) + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'flickity_settings' => 'default_group'
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return parent::settingsSummary(); // TODO: Add selected settings group to summary
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = array(
      '#theme' => 'flickity_image_field',
      '#children' => parent::viewElements($items, $langcode),
      '#settings' => $this->getSetting('flickity_settings'),
      '#title' => $this->fieldDefinition->getLabel(),
      '#label_display' => $this->label,
    );

    return $elements;
  }
}
