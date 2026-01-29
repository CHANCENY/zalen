<?php

/**
 * @file
 * Contains \Drupal\payment_invoice\Plugin\Field\FieldFormatter\PayButtonReservationFieldFormatter.
 */

namespace Drupal\payment_invoice\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\reservation\ReservationInterface;
use Drupal\payment_invoice\Form\PayKey\PayButtonReservationForm;
use Drupal\room_invoice\Entity\InvoicePayment;
use Drupal\Core\Url;

/** *
 * @FieldFormatter(
 *   id = "payment_button_field_default_formatter",
 *   label = @Translation("Formatter for payment button in reservation."),
 *   module = "payment_invoice",
 *   field_types = {
 *     "payment_button_reservation"
 *   }
 * )
 */
class PayButtonReservationFieldFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    //$view_mode = $this->viewMode;//full
    //$user_current = \Drupal::currentUser()->id();
    //$user_customer = $base_entity->getOwnerId();
    //$current_entity = $base_entity->getReservationedEntity();
    //$user_owner = $base_entity->getReservationedEntity()->getOwnerId();
    //$field_name = $this->fieldDefinition->getName();
    //$name = Xss::filter($name, []);

    //Check if the node has an interval for the payment button
    $settings = $this->getFieldSettings();
    if ($settings) {
      //Get a node for this reservation
      /** @var \Drupal\reservation\Entity\Reservation $entity_reservation */
      $entity_reservation = $items->getEntity();
      /** @var \Drupal\node\Entity\Node $entity_node base node */
      $entity_node = $entity_reservation->getReservationedEntity();
      if ($entity_node) {
        $interval_field = $entity_node->hasField($settings['parent_field_availability']);
        //$interval_field = $interval_field ? $entity_node->getFields()[$settings['parent_field_availability']]->getValue() : NULL;
        $interval_field = $interval_field ? $entity_node->get($settings['parent_field_availability'])->getValue() : NULL;
      };
    };

    //Check if the value of the field order and field interval availability for payment is set.
    if (!empty($interval_field) && is_array($interval_field)) {

      $user_current = \Drupal::currentUser()->id();
      $user_owner = $entity_node->getOwnerId();
      $user_customer = $entity_reservation->getOwnerId();

      // Check the current user to check if he has access to the field.
      if ($user_current == $user_owner || $user_current == $user_customer) {

        // Check if there is a paid invoice.
        /**
         * * @var \Drupal\Core\Entity\EntityInterface[] $invois
         */
        $invois = \Drupal::entityTypeManager()->getStorage('invoice_payment')
        ->loadByProperties(['target_type_order' => 'reservation', 'attendees' => $entity_reservation->id()]);
        if (!empty($invois)) {
          /** @var \Drupal\room_invoice\Entity\InvoicePayment $invois */
          $invois = current($invois);
          if ($invois->getPaymentStatus() == 'paid') {
            $element[0] = [
              '#type' => 'link',
              '#title' => $this->t('Booking paid.'),
              '#url' => Url::fromRoute(
                'room_invoice.checkout_invoice',
                ['context_id' => $invois->id(),],
              ),
            ];
            return $element;
          };
        };

        // Let's check if the selected payment provider is available.
        $managerPaymentProvider = \Drupal::service('plugin.manager.payment_provider');
        if (!$managerPaymentProvider->hasDefinition($settings['payment_provider'])) {
          return ['#markup' => $this->t('The payment provider is not available.'),];
        };
        $pluginPaymentProvider = $managerPaymentProvider->createInstance($settings['payment_provider']);

        // Let's check if the seller can accept payments.
        $accept_payments = $pluginPaymentProvider->canAcceptPayments($user_owner);
        if ($accept_payments !== true) {
          //return ['#markup' => $this->t($accept_payments),];
        };

        // Prepare some data from reservation, field order
        $field_name = $this->fieldDefinition->getName();
        /** @var \Drupal\Core\Field\FieldItemList $button_order */
        $button_order = $entity_reservation->get($field_name);
        $button_order_value = $button_order->getValue();
        if (!empty($button_order_value)) {
          $button_order_value = $button_order_value[0]['value'];
        } else {
          $button_order_value = NULL;
        };
        $reservation_created_time = $entity_reservation->getCreatedTime();
        $interval_field = $interval_field[0]['value'];
      } else {
        return ['#markup' => $this->t('Access to payment denied.'),];
      };

      // for test
      //$reservation_created_time = $reservation_created_time*60;
      //$entity_reservation->set($field_name,NULL,FALSE)->save();
      //$user_owner = 27;

      if ($user_current == $user_owner) {
        if ($button_order_value) {
          $message = $this->t('Order confirmed');
        } else {
          $current_time = (new \Drupal\Core\Datetime\DrupalDateTime())->getTimestamp();
          if ($current_time > $reservation_created_time + $interval_field) {
            $message = $this->t('Order confirmation expired');
          } else {
            $initial_config = [
              'currentUserID' => $user_current,
              'ownerID' => $user_owner,
              'customerID' => NULL,
              'field' => $field_name,
              'payment_settings' => $settings,
              'price_to_pay' => $this->getCalculatePrice(),
            ];
            $form = $this->getButtonForm($initial_config, $entity_reservation);
          };
        };

      } else if ($user_current == $user_customer) {
        $current_time = (new \Drupal\Core\Datetime\DrupalDateTime())->getTimestamp();
        if (!$button_order_value) {
          if ($current_time < $reservation_created_time + $interval_field) {
            $message = $this->t('The owner of the room has not yet confirmed the order, Wait term expires until @time', [
              '@time' => \Drupal\Core\Datetime\DrupalDateTime::createFromTimestamp($reservation_created_time + $interval_field)->format('l jS \of F Y h:i:s A'),]);
          } else {
            $message = $this->t('The owner of the room did not confirm the order');
          };
        } else {
          $initial_config = [
            'currentUserID' => $user_current,
            'ownerID' => NULL,
            'customerID' => $user_customer,
            'field' => $field_name,
            'payment_settings' => $settings,
            'price_to_pay' => $this->getCalculatePrice(),
          ];
          $form = $this->getButtonForm($initial_config, $entity_reservation);
        };
      };

    };

    $element = [];

    if (isset($message)) {
      $element[0] = ['#type' => 'markup',];
      $element[0]['#markup'] = $message;
    } else if (isset($form)) {
      $element[0] = $form;
    };

    return $element;
  }

  /* calculate price*/
  public function getCalculatePrice() {
    $money = 12367;
    $price['total'] = $money;
    $price['cost'] = number_format((float)$money/100, 2, '.', '');
    $price['currency'] = strtoupper('EUR');
    return $price;
  }

  /** @param string|int $id reservation @return \Drupal\room_invoice\Entity\InvoicePayment|null */
  public function hasInvois($id) {
    /** @var \Drupal\Core\Entity\EntityInterface[] An array of entity objects indexed by their ids. */
    $invois = \Drupal::entityTypeManager()->getStorage('invoice_payment')->loadByProperties(['attendees' => $id]);
    if (empty($invois)) {
      return FALSE;
    };
    return current($invois);
  }

  /**
   * Returns a form with a pay button for the current order.
   * @param array $initial_config
   * An array of settings that will be available in the form
   * @param \Drupal\reservation\ReservationInterface $entity_reservation
   * The entity in which the buy button is located
   * @return array Form array.
   */
  public function getButtonForm(array $initial_config, ReservationInterface $entity_reservation) {

    // Let's prepare the form
    /**
     * @var \Drupal\Core\Form\FormInterface $prepared_form form
     * @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Form%21FormBase.php/class/FormBase/8.2.x
     */
    $prepared_form = new PayButtonReservationForm($initial_config, $entity_reservation);

    // Let's build a form
    /**
     * @var \Drupal\Core\Form\FormBuilderInterface $form
     * @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Form%21FormBuilder.php/class/FormBuilder/8.2.x
     */
    $form = \Drupal::formBuilder();
    $form = $form->getForm($prepared_form);

    return $form;
  }






}
