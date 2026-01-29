<?php

namespace Drupal\reservation\Plugin\Menu\LocalTask;

use Drupal\reservation\ReservationStorageInterface;
use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a local task that shows the amount of unapproved reservations.
 */
class UnapprovedReservations extends LocalTaskDefault implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * The reservation storage service.
   *
   * @var \Drupal\reservation\ReservationStorageInterface
   */
  protected $reservationStorage;

  /**
   * Construct the UnapprovedReservations object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\reservation\ReservationStorageInterface $reservation_storage
   *   The reservation storage service.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ReservationStorageInterface $reservation_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->reservationStorage = $reservation_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('reservation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(Request $request = NULL) {
    return $this->t('Unapproved reservations (@count)', ['@count' => $this->reservationStorage->getUnapprovedCount()]);
  }

}
