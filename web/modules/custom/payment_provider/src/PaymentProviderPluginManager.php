<?php

namespace Drupal\payment_provider;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manager for plugin - PaymentProviderPlugin.
 */
class PaymentProviderPluginManager extends DefaultPluginManager {

  /** {@inheritdoc} */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/PaymentProvider',//где будут искаться плагины нашего типа
      $namespaces,//содержит корневые пути, необходимые для поиска плагинов в соответствии с неймспейсами.
      $module_handler,//позволяет узнать инфу о модулях, какие включены и т.д. Также используется для поиска информации о плагинах.
      'Drupal\payment_provider\PaymentProviderPluginInterface',
      'Drupal\payment_provider\Annotation\PaymentProvider',
    );
    //Register hook_payment_provider_plugin_info_alter().
    $this->alterInfo('payment_provider_plugin_info');
    //Set a key for the plugin cache.
    $this->setCacheBackend($cache_backend, 'payment_provider_plugin');
    $this->factory = new DefaultFactory($this->getDiscovery());
  }


}
