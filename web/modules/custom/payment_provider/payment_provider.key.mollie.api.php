<?php

/**
 * @file
 * Payment key @see API documentation.
 */

/**
 * @return array $keyValye
 * A key acsses.
 */
//function data_key_mollie(): array {
// // Keys for Mollie will be added.
// $key['mollie_settings'] = [
//   'live_key' => 'live_YouRMollIeLIVeAPIkeY',
//   'test_key' => 'test_6VSamsnRjQwKqWwwCxqa87sS7ppVh9',
//   'partner_id' => '13133258',
//   'profile_id' => 'pfl_Tqy26gECRu',
//   // organization_access_token
//   'name_organization_access' => 'testorg',
//   'token_organization_access' => 'access_zJHeW7d55G28Qbut5n4Hz9CrPxNkSqsyRzmK5NQe',
//   'token_organization_2' => 'access_QDBAdNaCFECmzKhGjjhhwa4HVJbJJySDKMF8hc4U',//Test. Is it possible to create a second one.
//   // app OAuth2 Configuring (Authorization URL, Access token URL, Resource owner URL - is default)
//   'app_redirect_url' => 'https://d3a5-46-96-172-57.ngrok.io'.'/druzal/authorize/mollie',
//   'client_id' => 'app_zsrdp3GPJ9Px4SywK4Nkb8fK',
//   'client_secret' => 'yVvr4z7hxcNxhwm2345drkCJesycbSnW3spQHtbf',
// ];
// return $key;
//}

function data_key_mollie(): array {
  // Keys for Mollie will be added.
  $key['mollie_settings'] = [
    'live_key' => 'live_YouRMollIeLIVeAPIkeY',
    'test_key' => 'test_5Nuh8AF9E2dDFGSecT7BdvPaP8xAnf',
    'partner_id' => 'org_19040809',
    'profile_id' => 'pfl_xxanKdDZk4',
    // organization_access_token
    'name_organization_access' => 'Belba T.I. BV',
    'token_organization_access' => 'access_VTQxsG3AUTFPCNwSqBMdHdDx2CwBpH3BWTG35JVs',
//   'token_organization_2' => 'access_QDBAdNaCFECmzKhGjjhhwa4HVJbJJySDKMF8hc4U',//Test. Is it possible to create a second one.
    // app OAuth2 Configuring (Authorization URL, Access token URL, Resource owner URL - is default)
    'app_redirect_url' => 'https://white-eland-548649.hostingersite.com/web/authorize/mollie',
    'client_id' => 'app_TYqonVG9Y89kx6Dj4HPDy94n',
    'client_secret' => 'jRW3DeVBUfbhxrswEa2UGeqvHsDNG9webaSdtnP6',
  ];
  return $key;
}


