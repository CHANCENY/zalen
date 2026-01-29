<?php

namespace Drupal\room_invoice\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Datetime\DrupalDateTime;

class InvoicePaymentListBuilder extends EntityListBuilder {

  /** @inheritdoc */
  public function buildHeader() {
    $header = [];
    $header['title'] = $this->t('Title');
    $header['date'] = $this->t('Date');
    $header['changed'] = $this->t('Changed');
    $header['author'] = $this->t('Costumer');
    $header['attendees'] = $this->t('Seller');
    $header['target'] = $this->t('Target');
    $header['subject'] = $this->t('Subject');
    $header['status'] = $this->t('Status');
    $header['published'] = $this->t('Published');
    return $header + parent::buildHeader();
  }

  /** @inheritdoc */
  public function buildRow(EntityInterface $invoice) {

    /** @var \Drupal\room_invoice\Entity\InvoicePayment $invoice */
    $row = [];
    $row['title'] = $invoice->toLink();
    $row['date'] = $invoice->getDate()->format('H:i:s j F Y');
    $row['changed'] = DrupalDateTime::createFromTimestamp($invoice->getChangedTime())->format('H:i:s j F Y');
    $row['author']['data'] = [
      '#theme' => 'username',
      '#account' => $invoice->getOwner(),
    ];
    $row['attendees']['data'] = [
      '#theme' => 'username',
      '#account' => $invoice->getRecipient(),
    ];
    $row['target'] = $invoice->getTarget();
    /** @var Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $reference_item */
    $reference_item = $invoice->adjust[0];
    $row['subject']['data'] = $reference_item->view();
    $row['status'] = $invoice->getPaymentStatus() ? $invoice->getPaymentStatus() : $this->t('New');
    $row['published'] = $invoice->isPublished() ? $this->t('Yes') : $this->t('No');

    return $row + parent::buildRow($invoice);

  }
  
  /**
   * {@inheritdoc}
   */
  public function load() {
    $query = $this->storage->getQuery()->sort('date', 'DESC')->pager(20);
    $entity_ids = $query->accessCheck(FALSE)->execute();
    return $this->storage->loadMultiple($entity_ids);
  }

}
