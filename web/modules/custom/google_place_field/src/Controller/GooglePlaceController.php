<?php

namespace Drupal\google_place_field\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GooglePlaceController {

  public function autocomplete(Request $request) {

    $query = $request->query->get('q');
    if (!$query) {
      return new JsonResponse([]);
    }

    // Get API key from module config
    $api_key = \Drupal::config('google_place_field.settings')->get('api_key');

    $url = "https://places.googleapis.com/v1/places:searchText";

    $data = [
      "textQuery" => $query,
    ];

    $headers = [
      "Content-Type: application/json",
      "X-Goog-Api-Key: $api_key",
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
      return new JsonResponse([]);
    }

    curl_close($ch);

    $results = json_decode($response, true);
    $suggestions = [];

    if (!empty($results['places'])) {
      foreach ($results['places'] as $place) {
        $displayName = $place['displayName']['text'] ?? '';
        $address = $place['formattedAddress'] ?? '';
        $place_id = $place['id'] ?? '';
        $map_url = $place['googleMapsUri'] ?? '';

        $suggestions[] = [
          'value' => $displayName . ' - ' . $address . " (" . $place_id . ")",
          'label' => $displayName . ' - '.$address,
          'place_id' => $place_id,
          'address' => $address,
          'map_url' => $map_url,
        ];
      }
    }

    return new JsonResponse($suggestions);
  }


  public function mapPreview(string $place_id) {
    $api_key = \Drupal::config('google_place_field.settings')->get('api_key');

    if (!$place_id || !$api_key) {
      return [
        '#markup' => '<div>Map not available</div>',
      ];
    }

    // Build the Google Maps Embed URL server-side
    $embed_url = 'https://www.google.com/maps/embed/v1/place?key=' . $api_key . '&q=place_id:' . $place_id;

    return new Response('<iframe src="' . $embed_url . '" width="100%" height="300" allowfullscreen loading="lazy"></iframe>',200,[
      'Content-Type' => 'text/html',
    ]);

  }

}
