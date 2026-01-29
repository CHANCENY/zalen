<?php

namespace Drupal\room_invoice\Field;

use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\Field\EntityReferenceFieldItemList;

class AdjustFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  protected function computeValue() {

    // For FieldFormatter
    // Get entity invoice
    /** @var \Drupal\room_invoice\Entity\InvoicePayment $invoice */
    $invoice = $this->getEntity();
    $target = $invoice->get('target_type_order')[0]->value;

    // Set current target_type
    /** @var \Drupal\Core\Field\TypedData\FieldItemDataDefinition $field */
    $field = $this->getItemDefinition();
    $field->setSetting('target_type', $target);

    // Set current id
    if (!$invoice->isNew()) {
      $target_id = $invoice->get('attendees')[0]->value;
      $this->list[0] = $this->createItem(0, $target_id);
    };

  }


}
