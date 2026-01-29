<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\reservation\Entity\ReservationType;
use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\ReservationInterface;
use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;

/**
 * Provides setup and helper methods for reservation tests.
 */
abstract class ReservationTestBase extends BrowserTestBase {

  use ReservationTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'block',
    'reservation',
    'node',
    'history',
    'field_ui',
    'datetime',
  ];

  /**
   * An administrative user with permission to configure reservation settings.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A normal user with permission to post reservations.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * A test node to which reservations will be posted.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  protected function setUp() {
    parent::setUp();

    // Create an article content type only if it does not yet exist, so that
    // child classes may specify the standard profile.
    $types = NodeType::loadMultiple();
    if (empty($types['article'])) {
      $this->drupalCreateContentType(['type' => 'article', 'name' => t('Article')]);
    }

    // Create two test users.
    $this->adminUser = $this->drupalCreateUser([
      'administer content types',
      'administer reservations',
      'administer reservation types',
      'administer reservation fields',
      'administer reservation display',
      'skip reservation approval',
      'post reservations',
      'access reservations',
      // Usernames aren't shown in reservation edit form autocomplete unless this
      // permission is granted.
      'access user profiles',
      'access content',
     ]);
    $this->webUser = $this->drupalCreateUser([
      'access reservations',
      'post reservations',
      'create article content',
      'edit own reservations',
      'skip reservation approval',
      'access content',
    ]);

    // Create reservation field on article.
    $this->addDefaultReservationField('node', 'article');

    // Create a test node authored by the web user.
    $this->node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1, 'uid' => $this->webUser->id()]);
    $this->drupalPlaceBlock('local_tasks_block');
  }

  /**
   * Posts a reservation.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   Node to post reservation on or NULL to post to the previously loaded page.
   * @param string $reservation
   *   Reservation body.
   * @param string $subject
   *   Reservation subject.
   * @param string $contact
   *   Set to NULL for no contact info, TRUE to ignore success checking, and
   *   array of values to set contact info.
   * @param string $field_name
   *   (optional) Field name through which the reservation should be posted.
   *   Defaults to 'reservation'.
   *
   * @return \Drupal\reservation\ReservationInterface|null
   *   The posted reservation or NULL when posted reservation was not found.
   */
  public function postReservation($entity, $reservation, $subject = '', $contact = NULL, $field_name = 'reservation') {
    $edit = [];
    $edit['reservation_body[0][value]'] = $reservation;

    if ($entity !== NULL) {
      $field = FieldConfig::loadByName($entity->getEntityTypeId(), $entity->bundle(), $field_name);
    }
    else {
      $field = FieldConfig::loadByName('node', 'article', $field_name);
    }
    $preview_mode = $field->getSetting('preview');

    // Must get the page before we test for fields.
    if ($entity !== NULL) {
      $this->drupalGet('reservation/reply/' . $entity->getEntityTypeId() . '/' . $entity->id() . '/' . $field_name);
    }

    // Determine the visibility of subject form field.
    $display_repository = $this->container->get('entity_display.repository');
    if ($display_repository->getFormDisplay('reservation', 'reservation')->getComponent('subject')) {
      // Subject input allowed.
      $edit['subject[0][value]'] = $subject;
    }
    else {
      $this->assertSession()->fieldNotExists('subject[0][value]');
    }

    if ($contact !== NULL && is_array($contact)) {
      $edit += $contact;
    }
    switch ($preview_mode) {
      case DRUPAL_REQUIRED:
        // Preview required so no save button should be found.
        $this->assertSession()->buttonNotExists(t('Save'));
        $this->submitForm($edit, 'Preview');
        // Don't break here so that we can test post-preview field presence and
        // function below.
      case DRUPAL_OPTIONAL:
        $this->assertSession()->buttonExists(t('Preview'));
        $this->assertSession()->buttonExists(t('Save'));
        $this->submitForm($edit, 'Save');
        break;

      case DRUPAL_DISABLED:
        $this->assertSession()->buttonNotExists(t('Preview'));
        $this->assertSession()->buttonExists(t('Save'));
        $this->submitForm($edit, 'Save');
        break;
    }
    $match = [];
    // Get reservation ID
    preg_match('/#reservation-([0-9]+)/', $this->getURL(), $match);

    // Get reservation.
    if ($contact !== TRUE) {
      // If true then attempting to find error message.
      if ($subject) {
        $this->assertSession()->pageTextContains($subject);
      }
      $this->assertSession()->pageTextContains($reservation);
      // Check the reservation ID was extracted.
      $this->assertArrayHasKey(1, $match);
    }

    if (isset($match[1])) {
      \Drupal::entityTypeManager()->getStorage('reservation')->resetCache([$match[1]]);
      return Reservation::load($match[1]);
    }
  }

  /**
   * Checks current page for specified reservation.
   *
   * @param \Drupal\reservation\ReservationInterface $reservation
   *   The reservation object.
   * @param bool $reply
   *   Boolean indicating whether the reservation is a reply to another reservation.
   *
   * @return bool
   *   Boolean indicating whether the reservation was found.
   */
  public function reservationExists(ReservationInterface $reservation = NULL, $reply = FALSE) {
    if ($reservation) {
      $reservation_element = $this->cssSelect('.reservation-wrapper ' . ($reply ? '.indented ' : '') . 'article#reservation-' . $reservation->id());
      if (empty($reservation_element)) {
        return FALSE;
      }

      $reservation_title = $reservation_element[0]->find('xpath', 'div/h3/a');
      if (empty($reservation_title) || $reservation_title->getText() !== $reservation->getSubject()) {
        return FALSE;
      }

      $reservation_body = $reservation_element[0]->find('xpath', 'div/div/p');
      if (empty($reservation_body) || $reservation_body->getText() !== $reservation->reservation_body->value) {
        return FALSE;
      }

      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Deletes a reservation.
   *
   * @param \Drupal\reservation\ReservationInterface $reservation
   *   Reservation to delete.
   */
  public function deleteReservation(ReservationInterface $reservation) {
    $this->submitForm('reservation/' . $reservation->id() . '/delete', [], 'Delete');
    $this->assertSession()->pageTextContains('The reservation and all its replies have been deleted.');
  }

  /**
   * Sets the value governing whether the subject field should be enabled.
   *
   * @param bool $enabled
   *   Boolean specifying whether the subject field should be enabled.
   */
  public function setReservationSubject($enabled) {
    $form_display = $this->container->get('entity_display.repository')
      ->getFormDisplay('reservation', 'reservation');

    if ($enabled) {
      $form_display->setComponent('subject', [
        'type' => 'string_textfield',
      ]);
    }
    else {
      $form_display->removeComponent('subject');
    }
    $form_display->save();
  }

  /**
   * Sets the value governing the previewing mode for the reservation form.
   *
   * @param int $mode
   *   The preview mode: DRUPAL_DISABLED, DRUPAL_OPTIONAL or DRUPAL_REQUIRED.
   * @param string $field_name
   *   (optional) Field name through which the reservation should be posted.
   *   Defaults to 'reservation'.
   */
  public function setReservationPreview($mode, $field_name = 'reservation') {
    switch ($mode) {
      case DRUPAL_DISABLED:
        $mode_text = 'disabled';
        break;

      case DRUPAL_OPTIONAL:
        $mode_text = 'optional';
        break;

      case DRUPAL_REQUIRED:
        $mode_text = 'required';
        break;
    }
    $this->setReservationSettings('preview', $mode, new FormattableMarkup('Reservation preview @mode_text.', ['@mode_text' => $mode_text]), $field_name);
  }

  /**
   * Sets the value governing whether the reservation form is on its own page.
   *
   * @param bool $enabled
   *   TRUE if the reservation form should be displayed on the same page as the
   *   reservations; FALSE if it should be displayed on its own page.
   * @param string $field_name
   *   (optional) Field name through which the reservation should be posted.
   *   Defaults to 'reservation'.
   */
  public function setReservationForm($enabled, $field_name = 'reservation') {
    $this->setReservationSettings('form_location', ($enabled ? ReservationItemInterface::FORM_BELOW : ReservationItemInterface::FORM_SEPARATE_PAGE), 'Reservation controls ' . ($enabled ? 'enabled' : 'disabled') . '.', $field_name);
  }

  /**
   * Sets the value governing restrictions on anonymous reservations.
   *
   * @param int $level
   *   The level of the contact information allowed for anonymous reservations:
   *   - 0: No contact information allowed.
   *   - 1: Contact information allowed but not required.
   *   - 2: Contact information required.
   */
  public function setReservationAnonymous($level) {
    $this->setReservationSettings('anonymous', $level, new FormattableMarkup('Anonymous reservationing set to level @level.', ['@level' => $level]));
  }

  /**
   * Sets the value specifying the default number of reservations per page.
   *
   * @param int $number
   *   Reservations per page value.
   * @param string $field_name
   *   (optional) Field name through which the reservation should be posted.
   *   Defaults to 'reservation'.
   */
  public function setReservationsPerPage($number, $field_name = 'reservation') {
    $this->setReservationSettings('per_page', $number, new FormattableMarkup('Number of reservations per page set to @number.', ['@number' => $number]), $field_name);
  }

  /**
   * Sets a reservation settings variable for the article content type.
   *
   * @param string $name
   *   Name of variable.
   * @param string $value
   *   Value of variable.
   * @param string $message
   *   Status message to display.
   * @param string $field_name
   *   (optional) Field name through which the reservation should be posted.
   *   Defaults to 'reservation'.
   */
  public function setReservationSettings($name, $value, $message, $field_name = 'reservation') {
    $field = FieldConfig::loadByName('node', 'article', $field_name);
    $field->setSetting($name, $value);
    $field->save();
  }

  /**
   * Checks whether the reservationer's contact information is displayed.
   *
   * @return bool
   *   Contact info is available.
   */
  public function reservationContactInfoAvailable() {
    return (bool) preg_match('/(input).*?(name="name").*?(input).*?(name="mail").*?(input).*?(name="homepage")/s', $this->getSession()->getPage()->getContent());
  }

  /**
   * Performs the specified operation on the specified reservation.
   *
   * @param \Drupal\reservation\ReservationInterface $reservation
   *   Reservation to perform operation on.
   * @param string $operation
   *   Operation to perform.
   * @param bool $approval
   *   Operation is found on approval page.
   */
  public function performReservationOperation(ReservationInterface $reservation, $operation, $approval = FALSE) {
    $edit = [];
    $edit['operation'] = $operation;
    $edit['reservations[' . $reservation->id() . ']'] = TRUE;
    $this->submitForm('admin/content/reservation' . ($approval ? '/approval' : ''), $edit, 'Update');

    if ($operation == 'delete') {
      $this->submitForm([], 'Delete');
      $this->assertSession()->responseContains(\Drupal::translation()->formatPlural(1, 'Deleted 1 reservation.', 'Deleted @count reservations.'));
    }
    else {
      $this->assertSession()->pageTextContains('The update has been performed.');
    }
  }

  /**
   * Gets the reservation ID for an unapproved reservation.
   *
   * @param string $subject
   *   Reservation subject to find.
   *
   * @return int
   *   Reservation id.
   */
  public function getUnapprovedReservation($subject) {
    $this->drupalGet('admin/content/reservation/approval');
    preg_match('/href="(.*?)#reservation-([^"]+)"(.*?)>(' . $subject . ')/', $this->getSession()->getPage()->getContent(), $match);

    return $match[2];
  }

  /**
   * Creates a reservation reservation type (bundle).
   *
   * @param string $label
   *   The reservation type label.
   *
   * @return \Drupal\reservation\Entity\ReservationType
   *   Created reservation type.
   */
  protected function createReservationType($label) {
    $bundle = ReservationType::create([
      'id' => $label,
      'label' => $label,
      'description' => '',
      'target_entity_type_id' => 'node',
    ]);
    $bundle->save();
    return $bundle;
  }

}
