<?php

namespace Drupal\oauth_authorize\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the Oauth2 token entity.
 *
 * @ContentEntityType(
 *   id = "oauth_authorize_token",
 *   label = @Translation("OAuth2 authorize token"),
 *   label_singular = @Translation("OAuth2 authorize token"),
 *   label_plural = @Translation("OAuth2 authorize tokens"),
 *   label_collection = @Translation("OAuth2 authorize tokens list"),
 *   base_table = "oauth_authorize_token",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "provider",
 *     "owner" = "author",
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
 *       "add" = "Drupal\oauth_authorize\Form\OauthAuthorizeForm",
 *       "edit" = "Drupal\oauth_authorize\Form\OauthAuthorizeForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\oauth_authorize\Controller\OauthAuthorizeListBuilder",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/services/oauth_authorize_token/{oauth_authorize_token}",
 *     "add-form" = "/admin/config/services/oauth_authorize_token/add",
 *     "edit-form" = "/admin/config/services/oauth_authorize_token/manage/{oauth_authorize_token}",
 *     "delete-form" = "/admin/config/services/oauth_authorize_token/manage/{oauth_authorize_token}/delete",
 *     "collection" = "/admin/config/services/oauth_authorize_token",
 *   },
 *   admin_permission = "administer oauth_authorize_token entities",
 *
 * )
 */
class OauthAuthorize extends ContentEntityBase implements EntityOwnerInterface, EntityChangedInterface {//EntityPublishedInterface

  use EntityOwnerTrait, EntityChangedTrait;

  /** @inheritdoc */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // Get the field definitions for 'id' and 'uuid' from the parent.
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    // Get the field definitions for 'author' from the trait.
    $fields += static::ownerBaseFieldDefinitions($entity_type);
    $fields['author']->setDisplayOptions('view', ['label' => 'inline', 'weight' => 2]);

    $fields['provider'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Provider'))->setDescription(new TranslatableMarkup('The resource to which we connect.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 3,])
      ->setDisplayOptions('form', ['weight' => 3]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'))->setDescription(new TranslatableMarkup('Date created'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 5,])
      ->setDisplayOptions('form', ['weight' => 5]);

    // Get the field definitions for 'changed' from the trait.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))->setDescription(new TranslatableMarkup('Date changed'))
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 6,]);

    $fields['scopes'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Scopes'))->setDescription(new TranslatableMarkup('The scopes for this Access Token OAuth2.'))
      ->setTranslatable(FALSE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('allowed_values', self::getDefaultScopesAllowedValues())
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 15,])
      ->setDisplayOptions('form', ['type' => 'options_buttons', 'weight' => 15,]);

    $fields['value'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Token'))->setDescription(new TranslatableMarkup('The token value.'))
      ->setSettings(['max_length' => 128,'text_processing' => 0,])
      ->setRequired(TRUE)
      ->setDisplayOptions('form', ['label' => 'inline', 'weight' => 20,])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 20,]);

    $fields['refresh_value'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Token refresh'))->setDescription(new TranslatableMarkup('The token refresh value.'))
      ->setSettings(['max_length' => 128, 'text_processing' => 0,])
      ->setRequired(TRUE)
      ->setDisplayOptions('form', ['label' => 'inline', 'weight' => 21,])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 21,]);

    $fields['expire'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Expire'))->setDescription(new TranslatableMarkup('The time when the token expires.'))
      ->setDisplayOptions('form', ['type' => 'datetime_timestamp', 'weight' => 22,])
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'timestamp', 'weight' => 22,])
      ->setRequired(TRUE);

    $fields['organization_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Organization ID'))->setDescription(new TranslatableMarkup('The organization id value.'))
      ->setSettings(['max_length' => 128,'text_processing' => 0,])
      ->setDisplayOptions('form', ['label' => 'inline', 'weight' => 23,])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 23,]);

    return $fields;
  }


  /** @return string provider */
  public function getProvider() {return $this->get('provider')->value;}
  /** @param string $provider @return $this */
  public function setProvider($provider) {return $this->set('provider', $provider);}


  /** @return int timestamp Date created */
  public function getCreatedTime() {return $this->get('created')->value;}
  /** @param int $timestamp @return $this */
  public function setCreatedTime($timestamp) {return $this->set('created', (int) $timestamp);}


  /** @return string token value */
  public function getTokenValue() {return $this->get('value')->value;}
  /** @param string $token value @return $this */
  public function setTokenValue($token) {return $this->set('value', $token);}
  /** @return string refresh token value */
  public function getRefreshToken() {return $this->get('refresh_value')->value;}
  /** @param string $refreshToken value @return $this */
  public function setRefreshToken($refreshToken) {return $this->set('refresh_value', $refreshToken);}


  /** @return int time expire token value in seconds */
  public function getExpireTime() {return $this->get('expire')->value;}
  /** @return int timestamp Date expire token value */
  public function getExpireDate() {return $this->get('expire')->value + $this->getChangedTime();}
  /** @param int $time @return $this */
  public function setExpireTime($time) {return $this->set('expire', (int) $time);}

  /** @return string organization_id token value */
  public function getOrganizationId() {return $this->get('organization_id')->value;}
  /** @param string $organizationId value @return $this */
  public function setOrganizationId($organizationId) {return $this->set('organization_id', $organizationId);}

  /** @return array list scopes */
  public function getScopesList() {
    $list = $this->get('scopes')->getValue();
    $values = [];
    foreach ($list as $item) {
      $values[] = $item['value'];
    };
    return $values;//$this->get('scopes')->getValue;
  }
  /** @param array $scopes @return $this */
  public function setScopesList(array $scopes) {return $this->set('scopes', $scopes);}

  /**
   * Returns the default allowed values scopes
   * @return array The scopes allowed values.
   */
  public static function getDefaultScopesAllowedValues() {
    $allowed_values = [
      'payments.read' => new TranslatableMarkup('Payments API - View the merchant’s payments, chargebacks and payment methods.'),
      'payments.write' => new TranslatableMarkup('Payments API - Create payments for the merchant. The received payment will be added to the merchant’s balance.'),
      'refunds.read' => new TranslatableMarkup('Refunds API - View the merchant’s refunds.'),
      'refunds.write' => new TranslatableMarkup('Refunds API - Create or cancel refunds.'),
      'customers.read' => new TranslatableMarkup('Customers API - View the merchant’s customers.'),
      'customers.write' => new TranslatableMarkup('Customers API - Manage the merchant’s customers.'),
      'mandates.read' => new TranslatableMarkup('Mandates API - View the merchant’s mandates.'),
      'mandates.write' => new TranslatableMarkup('Mandates API - Manage the merchant’s mandates.'),
      'subscriptions.read' => new TranslatableMarkup('Subscriptions API - View the merchant’s subscriptions.'),
      'subscriptions.write' => new TranslatableMarkup('Subscriptions API - Manage the merchant’s subscriptions.'),
      'profiles.read' => new TranslatableMarkup('Profiles API - View the merchant’s website profiles.'),
      'profiles.write' => new TranslatableMarkup('Profiles API - Manage the merchant’s website profiles.'),
      'invoices.read' => new TranslatableMarkup('Invoices API - View the merchant’s invoices.'),
      'settlements.read' => new TranslatableMarkup('Settlements API - View the merchant’s settlements.'),
      'orders.read' => new TranslatableMarkup('Orders API - View the merchant’s orders.'),
      'orders.write' => new TranslatableMarkup('Orders API - Manage the merchant’s orders.'),
      'shipments.read' => new TranslatableMarkup('Shipments API - View the merchant’s order shipments.'),
      'shipments.write' => new TranslatableMarkup('Shipments API - Manage the merchant’s order shipments.'),
      'organizations.read' => new TranslatableMarkup('Organizations API - View the merchant’s organizational details.'),
      'organizations.write' => new TranslatableMarkup('Organizations API - Change the merchant’s organizational details.'),
      'onboarding.read' => new TranslatableMarkup('Onboarding API - View the merchant’s onboarding status.'),
      'onboarding.write' => new TranslatableMarkup('Onboarding API - Submit onboarding data for the merchant.'),
    ];

    return  $allowed_values;
  }


  //Avoiding caching
  /** {@inheritdoc} */
  public function getCacheTagsToInvalidate() {
    // It's feasible there are millions of OAuth2 tokens in rotation; they're
    // used only for authentication, not for computing output. Hence it does not
    // make sense for an OAuth2 token to be a cacheable dependency. Consequently
    // generating a unique cache tag for every OAuth2 token entity should be
    // avoided. Therefore a single cache tag is used for all OAuth2 token
    // entities, including for lists.
    return ['oauth2_token'];
  }
  /** {@inheritdoc} */
  public function getCacheTags() {
    // Same reasoning as in ::getCacheTagsToInvalidate().
    return static::getCacheTagsToInvalidate();
  }




}


