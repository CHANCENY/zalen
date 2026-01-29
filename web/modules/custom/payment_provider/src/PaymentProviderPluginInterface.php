<?php

namespace Drupal\payment_provider;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Interface for plugin - PaymentProviderPlugin.
 */
interface PaymentProviderPluginInterface extends PluginInspectionInterface {

  /** The method through which we will get the plugin ID. */
  public function getId();
  
  /** The method return type of payment provider */
  public function getProviderType();


}
