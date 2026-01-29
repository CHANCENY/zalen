<?php

namespace Drupal\payment_provider;

use Drupal\Component\Plugin\PluginBase;
use Drupal\payment_provider\PaymentProviderPluginInterface;

/**
 * Base class for plugin - PaymentProviderPlugin.
 */
abstract class PaymentProviderPluginBase extends PluginBase implements PaymentProviderPluginInterface {

  /** {@inheritdoc} By default, it writes all these variables to the plugin local properties of the same name. */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /** {@inheritdoc} */
  public function getId() {
    return $this->pluginDefinition['id'];
  }
  
  /** {@inheritdoc} */
  public function getProviderType() {
    return '';
  }


}
