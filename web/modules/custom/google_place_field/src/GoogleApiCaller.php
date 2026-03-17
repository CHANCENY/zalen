<?php

namespace Drupal\google_place_field;

use Drupal\Core\Config\ImmutableConfig;

class GoogleApiCaller {

  /**
   * @var array|mixed|null
   */
  private mixed $apiKey;

  /**
   * @var array|mixed|null
   */
  private mixed $fields;

  public function __construct(ImmutableConfig $config) {
    $this->apiKey = $config->get('api_key');
    $this->fields = $config->get('field_masks');
  }

  public function searchLocation(string $query) {

    $url = "https://places.googleapis.com/v1/places:searchText";

    $data = [
      "textQuery" => $query,
    ];

    $headers = [
      "Content-Type: application/json",
      "X-Goog-Api-Key: $this->apiKey",
      "X-Goog-FieldMask: places.displayName,places.formattedAddress,places.id,places.googleMapsUri",
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => json_encode($data),
      CURLOPT_HTTPHEADER => $headers,
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
      \Drupal::logger('google_place_field')->error('Curl error: @error', ['@error' => curl_error($ch)]);
      return [];
    }

    curl_close($ch);

    $results = json_decode($response, true);
    return !empty($results) ? $results : [];
  }

  public function getPlaceDetails(string $placeId) {

    $url = "https://places.googleapis.com/v1/places/{$placeId}";
    $fields = !empty($this->fields) ? implode(',', $this->fields) : "id,rating,formattedAddress,displayName";

    // Initialize cURL
    $ch = curl_init($url);

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      "X-Goog-Api-Key: {$this->apiKey}",
      "X-Goog-FieldMask: {$fields}",
    ]);

    // Execute request
    $response = curl_exec($ch);

    // Check for errors
    if ($response === FALSE) {
      $error = curl_error($ch);
      curl_close($ch);
      return [];
    }

    // Close cURL
    curl_close($ch);

    // Decode JSON response
    $data = json_decode($response, TRUE);

    return !empty($data) ? $data : [];
  }

  public function getGoogleMapEmbedUrl(string $placeId): string {
    return 'https://www.google.com/maps/embed/v1/place?key=' . $this->apiKey . '&q=place_id:' . $placeId;
  }

}
