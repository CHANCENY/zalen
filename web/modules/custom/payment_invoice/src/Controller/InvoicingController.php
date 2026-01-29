<?php

namespace Drupal\payment_invoice\Controller;

use Drupal\Core\Controller\ControllerBase;

class InvoicingController extends ControllerBase {


  public function invoice(): array {

//    $reservation = \Drupal::service('entity_type.manager')->getStorage('reservation')->load(229);
//    $invoicing = new \Drupal\payment_invoice\Plugin\Invoicing($reservation);
//    $invoicing->sendInvoices();
    return [];
  }
}
