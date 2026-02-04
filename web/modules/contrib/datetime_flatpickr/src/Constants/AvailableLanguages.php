<?php

namespace Drupal\datetime_flatpickr\Constants;

/**
 * Class AvailableLanguages
 *
 * This class contains the list of languages supported by Flatpickr.
 * It also includes a mapping for Drupal language codes that differ from
 * Flatpickr's expected codes.
 */
class AvailableLanguages {
  public const LANGUAGES = [
    'ar',
    'at',
    'az',
    'be',
    'bg',
    'bn',
    'bs',
    'at',
    'cat',
    'cs',
    'cy',
    'da',
    'de',
    'eo',
    'es',
    'et',
    'fa',
    'fi',
    'fo',
    'fr',
    'ga',
    'gr',
    'he',
    'hi',
    'hr',
    'hu',
    'id',
    'is',
    'it',
    'ja',
    'ka',
    'km',
    'ko',
    'kz',
    'lt',
    'lv',
    'mk',
    'mn',
    'ms',
    'my',
    'nl',
    'no',
    'pa',
    'pl',
    'pt',
    'ro',
    'ru',
    'si',
    'sk',
    'sl',
    'sq',
    'sr',
    'sv',
    'th',
    'tr',
    'uk',
    'vn',
    'zh',
  ];

  // Language code mapping if Drupal language codes differ from Flatpickr's
  // expected codes.
  public const LANGUAGE_MAPPING = [
    'ca' => 'cat',
    'pt-pt' => 'pt',
    'pt-br' => 'pt',
  ];
}
