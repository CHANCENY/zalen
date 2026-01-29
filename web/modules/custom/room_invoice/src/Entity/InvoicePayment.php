<?php

namespace Drupal\room_invoice\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\user\Entity\User;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\room_invoice\Field\AdjustFieldItemList;

/**
 * @ContentEntityType(
 *   id = "invoice_payment",
 *   label = @Translation("Invoice payment"),
 *   label_singular = @Translation("invoice"),
 *   label_plural = @Translation("invoices"),
 *   label_collection = @Translation("Invoice payments list"),
 *   base_table = "invoice_payments",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "title",
 *     "owner" = "author",
 *     "published" = "published",
 *   },
 *   handlers = {
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "translation" = "Drupal\content_translation\ContentTranslationHandler",
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "add" = "Drupal\room_invoice\Form\InvoicePaymentForm",
 *       "edit" = "Drupal\room_invoice\Form\InvoicePaymentForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\room_invoice\Controller\InvoicePaymentListBuilder",
 *   },
 *   links = {
 *     "canonical" = "/admin/content/invoice/{invoice_payment}",
 *     "add-form" = "/admin/content/invoices/add",
 *     "edit-form" = "/admin/content/invoices/manage/{invoice_payment}",
 *     "delete-form" = "/admin/content/invoices/manage/{invoice_payment}/delete",
 *     "collection" = "/admin/content/invoices",
 *   },
 *   admin_permission = "administer invoice_payment",
 * 
 * )
 */
class InvoicePayment extends ContentEntityBase implements EntityOwnerInterface, EntityPublishedInterface, EntityChangedInterface {

    use EntityOwnerTrait, EntityPublishedTrait, EntityChangedTrait;

    /**
     * Initial status when creating a new invoice.
     * @var string
     */
    const STATUS_NEW_INVOIS = 'new';

    /** @inheritdoc */
    public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

        // Get the field definitions for 'id' and 'uuid' from the parent.
        /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
        $fields = parent::baseFieldDefinitions($entity_type);

        $fields['title'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Title'))->setDescription(new TranslatableMarkup('Title for invoice'))
          ->setRequired(TRUE)
          ->setDisplayOptions('form', ['weight' => 0]);

        $fields['date'] = BaseFieldDefinition::create('timestamp')
          ->setLabel(new TranslatableMarkup('Date'))->setDescription(new TranslatableMarkup('Date created invoice'))
          ->setRequired(TRUE)->setSetting('unsigned', TRUE)
          ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'timestamp', 'weight' => 6,])
          ->setDisplayOptions('form', ['type' => 'datetime_timestamp', 'weight' => 10]);

        $fields['description'] = BaseFieldDefinition::create('text_long')
          ->setLabel(new TranslatableMarkup('Description'))->setDescription(new TranslatableMarkup('Description invoice (text long)'))
          ->setDisplayOptions('view', ['label' => 'above', 'weight' => 10,])
          ->setDisplayOptions('form', ['weight' => 20]);

        $fields['recipient'] = BaseFieldDefinition::create('entity_reference')
          ->setLabel(new TranslatableMarkup('Recipient'))->setDescription(new TranslatableMarkup('User ID of the beneficiary of the payment'))
          ->setSetting('target_type', 'user')->setSetting('unsigned', TRUE)->setReadOnly(TRUE)
          ->setDisplayOptions('form', ['weight' => 20])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 9]);

        // Get the field definitions for 'author' and 'published' from the trait.
        $fields += static::ownerBaseFieldDefinitions($entity_type);
        $fields += static::publishedBaseFieldDefinitions($entity_type);
        $fields['author']->setDisplayOptions('view', ['label' => 'inline', 'weight' => 8]);
        $fields['published']->setDisplayOptions('form', ['settings' => ['display_label' => TRUE,], 'weight' => 35,])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 35]);

        $fields['target_type_order'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Target type for order'))->setDescription(new TranslatableMarkup('Type entity to order'))
          ->setSetting('is_ascii', TRUE)->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH)
          ->setRequired(TRUE)
          ->setDefaultValueCallback(static::class . '::getDefaultTargetType')
          ->setDisplayOptions('form', ['weight' => 21])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 20]);

        $fields['attendees'] = BaseFieldDefinition::create('integer')
          ->setLabel(new TranslatableMarkup('ID the target object for order'))->setDescription(new TranslatableMarkup('ID the target entity order'))
          ->setReadOnly(TRUE)->setSetting('unsigned', TRUE)->setRequired(TRUE)
          ->addConstraint('InvoiceAttendeeCount')->addConstraint('UniqueInvoiceAttendee')
          ->setDisplayOptions('form', ['weight' => 22])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 20]);
        $fields['adjust'] = BaseFieldDefinition::create('entity_reference')
          ->setLabel(new TranslatableMarkup('The target object for order'))->setDescription(new TranslatableMarkup('The target object for order'))
          ->setComputed(TRUE)->setClass(AdjustFieldItemList::class)
          ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 22]);

        $fields['path'] = BaseFieldDefinition::create('path')
          ->setLabel(new TranslatableMarkup('Path'))->setDescription(new TranslatableMarkup('The custom link invoice'))
          ->setComputed(TRUE)
          ->setDisplayOptions('form', ['weight' => 5]);

        $fields['changed'] = BaseFieldDefinition::create('changed')
          ->setLabel(new TranslatableMarkup('Changed'))->setDescription(new TranslatableMarkup('The date changed invoice'))
          ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 7]);

        $fields['money'] = BaseFieldDefinition::create('integer')
          ->setLabel(new TranslatableMarkup('Amount money of invoice'))->setDescription(new TranslatableMarkup('The base amount money of invoice'))
          ->setRequired(TRUE)->setSetting('min', 0)
          ->setDisplayOptions('form', ['weight' => 17])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 15]);
        $fields['currency'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Payment currency'))->setDescription(new TranslatableMarkup('The currency of invoice'))
          ->setRequired(TRUE)->setSetting('is_ascii', TRUE)->setSetting('max_length', 3)
          ->setDisplayOptions('form', ['weight' => 18])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 16]);

        $fields['payment_method'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Payment gateway provider and payment method'))->setDescription(new TranslatableMarkup('The provider and payment method invoice'))
          ->setRequired(TRUE)
          ->setDisplayOptions('form', ['weight' => 23])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 26]);
        $fields['connection_method'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Payment provider API access method.'))->setDescription(new TranslatableMarkup('Method for accessing the payment providers API when creating a payment.'))
          ->setTranslatable(FALSE)->setSetting('max_length', 32)
          ->setDisplayOptions('form', ['weight' => 38])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 38]);
        $fields['payment_mode'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Payment mode'))->setDescription(new TranslatableMarkup('Payment mode: test or other'))
          ->setRequired(TRUE)
          ->setDisplayOptions('form', ['weight' => 24])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 27]);

        $fields['payment_status'] = BaseFieldDefinition::create('invoice_status')
          ->setLabel(new TranslatableMarkup('Invoice status'))->setDescription(new TranslatableMarkup('The status payment invoice'))
          ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)->setDefaultValueCallback(static::class . '::getDefaultInvoiceStatus')
          ->setDisplayOptions('form', ['weight' => 25])->setDisplayOptions('view', ['label' => 'above', 'weight' => 23]);

        //This is for the payment provider like "mollie" to look up the requested invoices from the webhook.
        $fields['transaction_id'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Id payment transaction'))->setDescription(new TranslatableMarkup('Id payment if available in the provider API'))
          ->setTranslatable(FALSE)->setSetting('is_ascii', TRUE)->setSetting('max_length', 32)
          ->setDisplayOptions('form', ['weight' => 31])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 31]);
        $fields['sequence_type'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Payment sequence type'))->setDescription(new TranslatableMarkup('Current payment sequence type.'))
          ->setTranslatable(FALSE)->setSetting('max_length', 32)
          ->setDisplayOptions('form', ['weight' => 38])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 38]);
        $fields['customers_id'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('The id of customer'))->setDescription(new TranslatableMarkup('The id of the current customer if this is a recursive payment.'))
          ->setTranslatable(FALSE)->setSetting('max_length', 32)
          ->setDisplayOptions('form', ['weight' => 39])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 39]);
        $fields['payment_flows'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Id payment flow'))->setDescription(new TranslatableMarkup('If the payment consists of several transactions Id payment flow if available in the provider API'))
          ->setTranslatable(FALSE)->setSetting('is_ascii', TRUE)->setSetting('max_length', 32)
          ->setDisplayOptions('form', ['weight' => 32])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 32]);
        $fields['status_flow'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Status payment flow'))->setDescription(new TranslatableMarkup('The state of the current flow at the moment.'))
          ->setTranslatable(FALSE)->setSetting('max_length', 32)
          ->setDisplayOptions('form', ['weight' => 37])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 37]);
        $fields['current_step'] = BaseFieldDefinition::create('integer')
          ->setLabel(new TranslatableMarkup('Current step'))->setDescription(new TranslatableMarkup('Current step for flows'))
          ->setSetting('min', 0)->setSetting('unsigned', TRUE)->setSetting('size', 'small')
          ->setDisplayOptions('form', ['weight' => 36])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 36]);
        $fields['streams_line_pitch'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Current step of the payment flow'))->setDescription(new TranslatableMarkup('The current flow step if the payment consists of several transactions. If available in the provider API'))
          ->setTranslatable(FALSE)->setSetting('max_length', 64)
          ->setDisplayOptions('form', ['weight' => 33])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 33]);
        $fields['purpose_payment'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Purpose of payment'))->setDescription(new TranslatableMarkup('An arbitrary description of the purpose of the payment to clarify what will group payments by type'))
          ->setTranslatable(FALSE)->setSetting('max_length', 64)
          ->setDisplayOptions('form', ['weight' => 34])->setDisplayOptions('view', ['label' => 'inline', 'weight' => 34]);

        return $fields;
    }


    /** @return string */
    public function getTitle() {return $this->get('title')->value;}
    /** @param string $title @return $this */
    public function setTitle($title) {return $this->set('title', $title);}


    /** @return \Drupal\Core\Datetime\DrupalDateTime */
    public function getDate() {return DrupalDateTime::createFromTimestamp($this->get('date')->value);}
    /** @param int $value @return $this */
    public function setDate(int $value) {return $this->set('date', $value);}


    /** @return \Drupal\filter\Render\FilteredMarkup */
    public function getDescription() {return $this->get('description')->processed;}
    /** @param string $description @param string $format @return $this */
    public function setDescription($description, $format) {
      return $this->set('description', ['value' => $description, 'format' => $format,]);
    }
    /** @return array */
    public function getDescriptionSeparator() {return ['name-value' => ': _:_ ', 'string' => '<br>'];}
    /** @param \Drupal\Core\Session\AccountInterface $account @return array possible values ["basic_html", "restricted_html", "full_html", "plain_text",] */
    public function getDescriptionAllowedFormats(\Drupal\Core\Session\AccountInterface $account = null) {
      if (!$account) {
        /** @var \Drupal\Core\Session\AccountProxy $account */
        $account = \Drupal::service('current_user');
      };
      /** @var \Drupal\Core\TypedData\DataDefinition $item */
      $item = (new \Drupal\Core\TypedData\DataDefinition)->create('text_long');//filter_format//string
      /** @var \Drupal\filter\Plugin\DataType\FilterFormat $allowed_formats */
      $allowed_formats = (new \Drupal\filter\Plugin\DataType\FilterFormat($item))->getSettableOptions($account);
      if (!empty($allowed_formats) && is_array($allowed_formats)) {
        return array_keys($allowed_formats);
      };
      return ['basic_html'];
    }
    /** @return array */
    public function getDescriptionToArray() {
      if (!$description = $this->get('description')->value) {return [];};
      $separator = $this->getDescriptionSeparator();
      $data_string = explode($separator['string'], $description);
      $data_array = []; $line = null;
      foreach ($data_string as $v) {
        $line = explode($separator['name-value'], $v);
        $data_array[$line[0]] = $line[1];
      };
      return $data_array;
    }
    /** @param array $data_description @param string $format @return $this */
    public function setDescriptionFromArray(array $data_description, string $format = 'basic_html') {
      if (empty($data_description)) {return $this;};
      $separator = $this->getDescriptionSeparator();
      $description = '';
      foreach ($data_description as $k => $v) {
        $description .= $k . $separator['name-value'] . $v . $separator['string'];
      };
      //fix last aded string separator
      $description = mb_substr($description, 0, strlen($description) - strlen($separator['string']));
      return $this->set('description', ['value' => $description, 'format' => $format,]);
    }


    /** @return string */
    public function getTarget() {return $this->get('target_type_order')->value;}
    /** @param string $target @return $this */
    public function setTarget($target) {return $this->set('target_type_order', $target);}


    /** @return int */
    public function getAttendees() {return $this->get('attendees')->value;}
    /** @param string|int $attendees @return $this */
    public function setAttendees($attendees) {return $this->set('attendees', intval($attendees));}


    /** @return array */
    public function getPaymentMethod() {
      $payment_method['method'] = $this->get('payment_method')->value;
      $payment_method['mode'] = $this->get('payment_mode')->value;
      return $payment_method;
    }
    /** @param string $method @param string $mode @return $this */
    public function setPaymentMethod($method, $mode) {
      $this->set('payment_method', $method);
      $this->set('payment_mode', $mode);
      return $this;
    }
    /** @return string */
    public function getConnectionMethod() {return $this->get('connection_method')->value;}
    /** @param string $connection_method @return $this */
    public function setConnectionMethod($connection_method) {return $this->set('connection_method', $connection_method);}


    /** @return \Drupal\user\UserInterface */
    public function getRecipient() {return User::load($this->get('recipient')->target_id);}
    /** @return int */
    public function getRecipientID() {return $this->get('recipient')->target_id;}
    /** @param string|int $recipient @return $this */
    public function setRecipientID($recipient) {return $this->set('recipient', intval($recipient));}


    /** @return int */
    public function getAmountValue() {return $this->get('money')->value;}
    /** @param string|int $money @return $this */
    public function setAmountValue($money) {return $this->set('money', intval($money));}


    /** @return string */
    public function getPaymentCurrency() {return $this->get('currency')->value;}
    /** @param string $currency @return $this */
    public function setPaymentCurrency(string $currency) {
      trim ($currency);
      if (strlen($currency) == 3) {
        return $this->set('currency', strtoupper($currency));
      };
    }


    /** @return string */
    public function getPaymentStatus() {
      $list = $this->get('payment_status');
      $count = $list->count();
      $value = $count ? $value = $list[$count-1]->getValue()['meaning'] : '';
      return $value;
    }
    /** @param string $payment_status @return $this */
    public function setPaymentStatus(string $payment_status) {
      $status['date'] = (new \Drupal\Core\Datetime\DrupalDateTime)->getTimestamp();
      $status['meaning'] = $payment_status;
      $this->get('payment_status')->appendItem($status);
      //return $this->set('payment_status', $payment_status);
      return $this;
    }
    /** @param string $payment_status @return $this */
    public function updateLastPaymentStatus(string $payment_status) {
      $list = $this->get('payment_status');
      $count = $list->count();
      if ($count) {
        /** @var Drupal\room_invoice\Plugin\Field\FieldType\InvoiceStatusFieldItem $field */
        $field = $list[$count-1];
        $data['meaning'] = $payment_status;
        $data['date'] = (new \Drupal\Core\Datetime\DrupalDateTime)->getTimestamp();
        $field->setValue($data);
      };
      return $this;
    }

    /** @return string */
    public function getTransactionId() {return $this->get('transaction_id')->value;}
    /** @param string $transaction_id @return $this */
    public function setTransactionId($transaction_id) {return $this->set('transaction_id', $transaction_id);}
    /** @return string */
    public function getSequenceType() {return $this->get('sequence_type')->value;}
    /** @return string */
    public function getCustomersId() {return $this->get('customers_id')->value;}
    /** @param string $customers_id @return $this */
    public function setCustomersId($customers_id) {return $this->set('customers_id', $customers_id);}
    /** @return string */
    public function getPaymentFlowsId() {return $this->get('payment_flows')->value;}
    /** @param string $payment_flows_id @return $this */
    public function setPaymentFlowsId($payment_flows_id) {return $this->set('payment_flows', $payment_flows_id);}
    /** @return string */
    public function getStatusFlow() {return $this->get('status_flow')->value;}
    /** @param string $status_flow @return $this */
    public function setStatusFlow($status_flow) {return $this->set('status_flow', $status_flow);}
    /** @return string */
    public function getCurrentStep() {return $this->get('current_step')->value;}
    /** @param string $current_step @return $this */
    public function setCurrentStep($current_step) {return $this->set('current_step', $current_step);}
    /** @return string */
    public function getStreamStep() {return $this->get('streams_line_pitch')->value;}
    /** @param string $stream_step @return $this */
    public function setStreamStep($stream_step) {return $this->set('streams_line_pitch', $stream_step);}
    /** @return string */
    public function getPurposePayment() {return $this->get('purpose_payment')->value;}
    /** @param string $purpose_payment @return $this */
    public function setPurposePayment($purpose_payment) {return $this->set('purpose_payment', $purpose_payment);}

    
    /**
     * Returns the default value target type entity.
     * @return string The target type order.
     */
    public static function getDefaultTargetType() {

      /** @var Symfony\Component\HttpFoundation\ParameterBag */
      $current_parameters = \Drupal::routeMatch()->getParameters();

      if ($current_parameters->get('entity_type_id') == 'invoice_payment') {
        return [['value' => 'reservation'],];
      } else if (($current_node = $current_parameters->get('node')) instanceof \Drupal\node\NodeInterface) {
        /** @var \Drupal\node\Entity\Node $current_node */
        $defined_fields = $current_node->getFieldDefinitions();
        foreach ($defined_fields as $field) {
          if ($field->getType() == 'reservation') {
            $value = 'reservation';
            break;
          };
        };
      };
      return [['value' => isset($value) ? $value : 'user'],];
    }
    /**
     * Returns the default value invoice_status field for entity.
     * @return array The default value of the invoice_status field for an order.
     */
    public static function getDefaultInvoiceStatus() {
      $current_date = (new \Drupal\Core\Datetime\DrupalDateTime)->getTimestamp();
      return ['date' => $current_date, 'meaning' => 'new'];
    }

    /**
     * Load invoices by their property values.
     * @param array $properties
     * An associative array where the keys are the property names
     * and the values are the values those properties must have.
     * @return \Drupal\Core\Entity\EntityInterface[]
     * An array of 'invoice_payment' entity objects indexed by their ids.
     */
    public static function loadInvoiceByProperties(array $properties = []) {
      /** @var \Drupal\Core\Entity\EntityInterface[] $invoices An array of entity objects indexed by their ids. */
      $invoices = \Drupal::entityTypeManager()->getStorage('invoice_payment')->loadByProperties($properties);
      return $invoices;
    }
    /**
     * Load last current step invoices by their property values.
     * @param array $properties
     * An associative array where the keys are the property names
     * and the values are the values those properties must have.
     * @return \Drupal\Core\Entity\EntityInterface[]
     * An array of 'invoice_payment' entity object indexed by his id.
     */
    public static function loadLastInvoiceByProperties(array $properties = []) {

      /** @var \Drupal\Core\Entity\Query\Sql\Query $query */
      $query = \Drupal::entityQuery('invoice_payment');
      foreach ($properties as $k => $v) {
        $query->condition($k, $v, '=');
      };
      $ids = $query->sort('current_step', 'DESC')->pager(1)->accessCheck(FALSE)->execute();
      $invoices = InvoicePayment::loadMultiple($ids);

      return $invoices;
    }
    /**
     * Processes the object to detect constraints.
     * @return int|null
     * Number of restrictions detected or NULL in case of error.
     */
    public static function invokeInvoiceValidation(InvoicePayment $invoice): ?int {
      try {
        /** @var \Drupal\Core\Entity\EntityConstraintViolationList $validate_invoice */
        $validate_invoice = $invoice->validate();
      } catch (\Drupal\Core\Entity\Query\QueryException | \Drupal\Core\Database\InvalidQueryException $e) {
        \Drupal::logger('room_invoice')->error('During the validation of the invoice entity, a query error occurred in the database: '.htmlspecialchars($e->getMessage()).'<br>'.$e->getTraceAsString());
        \Drupal::messenger()->addError(new TranslatableMarkup('Payment invoice processing error.'));
        return null;
      };
      $validate_count = $validate_invoice->count();
      if ($validate_count) {
        for ($i=0; $i<$validate_count; $i++) {
          /** @var \Symfony\Component\Validator\ConstraintViolation $violation */
          $violation = $validate_invoice->get($i);
          $arguments = [
            '@property path' => $violation->getPropertyPath(),
            '@invalid_value' => '(' . is_string($violation->getInvalidValue()) ? $violation->getInvalidValue() : $violation->getInvalidValue()->getName() . ')',//->getString(),//->isEmpty()
            '@message' => $violation->getMessage(),
          ];

          \Drupal::logger('room_invoice')->warning('An error occurred while subscribing to the VIP account. Violation consists of 1.Property path: @property path. 2.Invalid value: @invalid_value. 3.Message: @message', $arguments);
          \Drupal::messenger()->addWarning(new TranslatableMarkup('Payment error, invoice processing failed. @message', ['@message' => $arguments['@message']]));
        };
        return $validate_count;
      };

      return $validate_count;

    }



    
}


