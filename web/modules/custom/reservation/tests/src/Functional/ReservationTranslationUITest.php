<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\content_translation\Functional\ContentTranslationUITestBase;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Reservation Translation UI.
 *
 * @group reservation
 */
class ReservationTranslationUITest extends ContentTranslationUITestBase {

  use ReservationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The subject of the test reservation.
   *
   * @var string
   */
  protected $subject;

  /**
   * An administrative user with permission to administer reservations.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {inheritdoc}
   */
  protected $defaultCacheContexts = [
    'languages:language_interface',
    'session',
    'theme',
    'timezone',
    'url.query_args:_wrapper_format',
    'url.query_args.pagers:0',
    'url.site',
    'user.permissions',
    'user.roles',
  ];

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'language',
    'content_translation',
    'node',
    'reservation',
  ];

  protected function setUp(): void {
    $this->entityTypeId = 'reservation';
    $this->nodeBundle = 'article';
    $this->bundle = 'reservation_article';
    $this->testLanguageSelector = FALSE;
    $this->subject = $this->randomMachineName();
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function setupBundle() {
    parent::setupBundle();
    $this->drupalCreateContentType(['type' => $this->nodeBundle, 'name' => $this->nodeBundle]);
    // Add a reservation field to the article content type.
    $this->addDefaultReservationField('node', 'article', 'reservation_article', ReservationItemInterface::OPEN, 'reservation_article');
    // Create a page content type.
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'page']);
    // Add a reservation field to the page content type - this one won't be
    // translatable.
    $this->addDefaultReservationField('node', 'page', 'reservation');
    // Mark this bundle as translatable.
    $this->container->get('content_translation.manager')->setEnabled('reservation', 'reservation_article', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function getTranslatorPermissions() {
    return array_merge(parent::getTranslatorPermissions(), ['post reservations', 'administer reservations', 'access reservations']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity($values, $langcode, $reservation_type = 'reservation_article') {
    if ($reservation_type == 'reservation_article') {
      // This is the article node type, with the 'reservation_article' field.
      $node_type = 'article';
      $field_name = 'reservation_article';
    }
    else {
      // This is the page node type with the non-translatable 'reservation' field.
      $node_type = 'page';
      $field_name = 'reservation';
    }
    $node = $this->drupalCreateNode([
      'type' => $node_type,
      $field_name => [
        ['status' => ReservationItemInterface::OPEN],
      ],
    ]);
    $values['entity_id'] = $node->id();
    $values['entity_type'] = 'node';
    $values['field_name'] = $field_name;
    $values['uid'] = $node->getOwnerId();
    return parent::createEntity($values, $langcode, $reservation_type);
  }

  /**
   * {@inheritdoc}
   */
  protected function getNewEntityValues($langcode) {
    // Reservation subject is not translatable hence we use a fixed value.
    return [
      'subject' => [['value' => $this->subject]],
      'reservation_body' => [['value' => $this->randomMachineName(16)]],
    ] + parent::getNewEntityValues($langcode);
  }

  /**
   * {@inheritdoc}
   */
  protected function doTestPublishedStatus() {
    $entity_type_manager = \Drupal::entityTypeManager();
    $storage = $entity_type_manager->getStorage($this->entityTypeId);

    $storage->resetCache();
    $entity = $storage->load($this->entityId);

    // Unpublish translations.
    foreach ($this->langcodes as $index => $langcode) {
      if ($index > 0) {
        $edit = ['status' => 0];
        $url = $entity->toUrl('edit-form', ['language' => ConfigurableLanguage::load($langcode)]);
        $this->submitForm($url, $edit, $this->getFormSubmitAction($entity, $langcode));
        $storage->resetCache();
        $entity = $storage->load($this->entityId);
        $this->assertFalse($this->manager->getTranslationMetadata($entity->getTranslation($langcode))->isPublished(), 'The translation has been correctly unpublished.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doTestAuthoringInfo() {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($this->entityTypeId);
    $storage->resetCache([$this->entityId]);
    $entity = $storage->load($this->entityId);
    $languages = $this->container->get('language_manager')->getLanguages();
    $values = [];

    // Post different authoring information for each translation.
    foreach ($this->langcodes as $langcode) {
      $url = $entity->toUrl('edit-form', ['language' => $languages[$langcode]]);
      $user = $this->drupalCreateUser();
      $request_time = \Drupal::time()->getRequestTime();
      $values[$langcode] = [
        'uid' => $user->id(),
        'created' => $request_time - mt_rand(0, 1000),
      ];
      /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
      $date_formatter = $this->container->get('date.formatter');
      $edit = [
        'uid' => $user->getAccountName() . ' (' . $user->id() . ')',
        'date[date]' => $date_formatter->format($values[$langcode]['created'], 'custom', 'Y-m-d'),
        'date[time]' => $date_formatter->format($values[$langcode]['created'], 'custom', 'H:i:s'),
      ];
      $this->submitForm($url, $edit, $this->getFormSubmitAction($entity, $langcode));
    }

    $storage->resetCache([$this->entityId]);
    $entity = $storage->load($this->entityId);
    foreach ($this->langcodes as $langcode) {
      $metadata = $this->manager->getTranslationMetadata($entity->getTranslation($langcode));
      $this->assertEquals($values[$langcode]['uid'], $metadata->getAuthor()->id(), 'Translation author correctly stored.');
      $this->assertEquals($values[$langcode]['created'], $metadata->getCreatedTime(), 'Translation date correctly stored.');
    }
  }

  /**
   * Tests translate link on reservation content admin page.
   */
  public function testTranslateLinkReservationAdminPage() {
    $this->adminUser = $this->drupalCreateUser(array_merge(parent::getTranslatorPermissions(), ['access administration pages', 'administer reservations', 'skip reservation approval']));
    $this->drupalLogin($this->adminUser);

    $cid_translatable = $this->createEntity([], $this->langcodes[0]);
    $cid_untranslatable = $this->createEntity([], $this->langcodes[0], 'reservation');

    // Verify translation links.
    $this->drupalGet('admin/content/reservation');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkByHrefExists('reservation/' . $cid_translatable . '/translations');
    $this->assertSession()->linkByHrefNotExists('reservation/' . $cid_untranslatable . '/translations');
  }

  /**
   * {@inheritdoc}
   */
  protected function doTestTranslationEdit() {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($this->entityTypeId);
    $storage->resetCache([$this->entityId]);
    $entity = $storage->load($this->entityId);
    $languages = $this->container->get('language_manager')->getLanguages();

    foreach ($this->langcodes as $langcode) {
      // We only want to test the title for non-english translations.
      if ($langcode != 'en') {
        $options = ['language' => $languages[$langcode]];
        $url = $entity->toUrl('edit-form', $options);
        $this->drupalGet($url);

        $title = t('Edit @type @title [%language translation]', [
          '@type' => $this->entityTypeId,
          '@title' => $entity->getTranslation($langcode)->label(),
          '%language' => $languages[$langcode]->getName(),
        ]);
        $this->assertSession()->responseContains($title);
      }
    }
  }

}
