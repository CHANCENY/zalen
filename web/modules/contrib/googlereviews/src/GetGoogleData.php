<?php

namespace Drupal\googlereviews;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Get data from Google Maps API.
 */
class GetGoogleData implements GetGoogleDataInterface {

  use StringTranslationTrait;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * Constructs a GetGoogleData object.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(ClientInterface $client, LoggerChannelFactoryInterface $logger, MessengerInterface $messenger, ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager, UrlGeneratorInterface $url_generator, TranslationInterface $string_translation) {
    $this->client = $client;
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
    $this->urlGenerator = $url_generator;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Get reviews from Google Maps API.
   *
   * @param array $fields
   *   (optional) The fields which the result should be limited to.
   * @param int $max_reviews
   *   (optional) The max amount of reviews to return.
   * @param string $reviews_sort
   *   (optional) The sorting of the reviews 'newest' or 'most_relevant'.
   * @param string $language
   *   (optional) The language that should be used to translate certain results.
   * @param string|null $google_place_id
   *   (optional) The place ID.
   *
   * @return array
   *   Data from Google Maps API with information about a place_id in an array.
   */
  public function getGoogleReviews(array $fields = [], int $max_reviews = 5, string $reviews_sort = 'newest', string $language = '', ?string $google_place_id = ''): array {
    $config = $this->configFactory->get('googlereviews.settings');
    $auth_key = $config->get('google_auth_key');
    $place_id = !empty($google_place_id) ? $google_place_id : $config->get('google_place_id');
    $api_url = $config->get('google_api_url');

    if ($auth_key == '' || $place_id == '') {
      $link = $this->urlGenerator->generateFromRoute('googlereviews.settings_form');
      $this->messenger->addError($this->t('You need to add credentials on the <a href=":link">Google review settings page</a> to show the reviews.', [':link' => $link]));
      return [];
    }

    $url_parameters = [
      'place_id' => $place_id,
      'key' => $auth_key,
      'reviews_sort' => $reviews_sort,
      'language' => ($language == '') ? $this->languageManager->getCurrentLanguage()->getId() : $language,
    ];

    if (!empty($fields)) {
      $url_parameters['fields'] = implode(',', $fields);
    }

    $result = [];
    try {
      $request = $this->client->get($api_url, ['query' => $url_parameters]);
      $resultArray = json_decode($request->getBody(), TRUE);

      if ($resultArray['status'] !== 'OK') {
        if (isset($resultArray['error_message']) && !empty($resultArray['error_message'])) {
          $this->messenger->addError($this->t('Something went wrong with contacting the Google Maps API. @status, @error', [
            '@status' => $resultArray['status'],
            '@error' => $resultArray['error_message'],
          ]));
        }
        else {
          $this->messenger->addError($this->t('Something went wrong with contacting the Google Maps API: @status', [
            '@status' => $resultArray['status'],
          ]));
        }
      }

      if (isset($resultArray['result']) && !empty($resultArray['result'])) {
        if (isset($resultArray['result']['reviews'])) {
          $resultArray['result']['reviews'] = array_slice($resultArray['result']['reviews'], 0, $max_reviews);
        }

        $result = $resultArray['result'];
        $result['place_id'] = $place_id;
      }
    }
    catch (RequestException $e) {
      $this->logger->get('googlereviews')->error($e);
      $this->messenger->addError($this->t('Something went wrong with contacting the Google Maps API.'));
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    $config = $this->configFactory->get('googlereviews.settings');
    return $config->get('cache_max_age');
  }

}
