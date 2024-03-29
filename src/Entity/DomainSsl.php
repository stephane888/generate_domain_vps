<?php

namespace Drupal\generate_domain_vps\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\generate_domain_vps\DomainSslInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Contient les SSLs sui ont été generées..
 *
 * @ContentEntityType(
 *   id = "domain_ssl",
 *   label = @Translation("Domain SSL"),
 *   label_collection = @Translation("Domain SSLs"),
 *   label_singular = @Translation("domain ssl"),
 *   label_plural = @Translation("domain ssls"),
 *   label_count = @PluralTranslation(
 *     singular = "@count domain ssls",
 *     plural = "@count domain ssls",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\generate_domain_vps\DomainSslListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\generate_domain_vps\Form\DomainSslForm",
 *       "edit" = "Drupal\generate_domain_vps\Form\DomainSslForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "domain_ssl",
 *   admin_permission = "administer domain ssl",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/domain-ssl",
 *     "add-form" = "/domain-ssl/add",
 *     "canonical" = "/domain-ssl/{domain_ssl}",
 *     "edit-form" = "/domain-ssl/{domain_ssl}/edit",
 *     "delete-form" = "/domain-ssl/{domain_ssl}/delete",
 *   },
 *   field_ui_base_route = "entity.domain_ssl.settings",
 * )
 */
class DomainSsl extends ContentEntityBase implements DomainSslInterface {
  
  use EntityChangedTrait;
  use EntityOwnerTrait;
  
  /**
   * Le nombre d'essaie max en 1 semaine.
   *
   * @var integer
   */
  protected const NombreEssaieMax = 3;
  
  /**
   *
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
    // On verifie le nombre d'essaie.
    $nbr = $this->getNombreEssaie();
    if ($nbr < self::NombreEssaieMax) {
      $this->set('nombre_essaie', $nbr + 1);
    }
    else
      throw new \Exception("Vous avez atteint la limite de generation de domaine, veiller essayer dans une semaine ");
  }
  
  /**
   * Retourne le status du SSL.
   *
   * @return boolean
   */
  public function getStatusSsl() {
    return $this->get('status_ssl')->value;
  }
  
  /**
   * Active le status su SSL.
   */
  public function setStatusSSL($status = true) {
    $this->set('status_ssl', $status);
  }
  
  /**
   * --
   */
  public function resetRateLimit() {
    $this->set('nombre_essaie', 1);
  }
  
  /**
   * Determine si l'on peut encore essayé de generer le domaine, ( On doit
   * completé cette fonctionne pour quelle tienne en compte le nombre de semaine
   * ).
   */
  public function checkRateLimit() {
    $nombre_essaie = (int) $this->get('nombre_essaie')->value;
    if ($nombre_essaie <= self::NombreEssaieMax)
      return true;
    else
      return false;
  }
  
  /**
   * --
   */
  public function getNombreEssaie() {
    return (int) $this->get('nombre_essaie')->value;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    
    $fields['label'] = BaseFieldDefinition::create('string')->setLabel(t('Label'))->setRequired(TRUE)->setSetting('max_length', 255)->setDisplayOptions('form', [
      'type' => 'string_textfield',
      'weight' => -5
    ])->setDisplayConfigurable('form', TRUE)->setDisplayOptions('view', [
      'label' => 'hidden',
      'type' => 'string',
      'weight' => -5
    ])->setDisplayConfigurable('view', TRUE);
    
    $fields['status'] = BaseFieldDefinition::create('boolean')->setLabel(t('Status'))->setDefaultValue(TRUE)->setSetting('on_label', 'Enabled')->setDisplayOptions('form', [
      'type' => 'boolean_checkbox',
      'settings' => [
        'display_label' => FALSE
      ],
      'weight' => 0
    ])->setDisplayConfigurable('form', TRUE)->setDisplayOptions('view', [
      'type' => 'boolean',
      'label' => 'above',
      'weight' => 0,
      'settings' => [
        'format' => 'enabled-disabled'
      ]
    ])->setDisplayConfigurable('view', TRUE);
    
    $fields['description'] = BaseFieldDefinition::create('text_long')->setLabel(t('Description'))->setDisplayOptions('form', [
      'type' => 'text_textarea',
      'weight' => 10
    ])->setDisplayConfigurable('form', TRUE)->setDisplayOptions('view', [
      'type' => 'text_default',
      'label' => 'above',
      'weight' => 10
    ])->setDisplayConfigurable('view', TRUE);
    
    // On peut utiliser le champs 'changed'
    // $fields['date_expiration'] =
    // BaseFieldDefinition::create('datetime')->setLabel(t('Date
    // expiration'))->setSettings([
    // 'datetime_type' => 'date'
    // ])->setRevisionable(TRUE)->setDisplayOptions('view', [
    // 'label' => 'above',
    // 'type' => 'string',
    // 'weight' => 0
    // ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view',
    // TRUE)->setDisplayOptions('form', [
    // 'type' => 'datetime_default',
    // 'weight' => 0
    // ]);
    
    $fields['status_ssl'] = BaseFieldDefinition::create('boolean')->setLabel(t('Status SSL'))->setSetting('on_label', 'SSL activé')->setDisplayOptions('form', [
      'type' => 'boolean_checkbox',
      'settings' => [
        'display_label' => FALSE
      ],
      'weight' => 0
    ])->setDisplayConfigurable('form', TRUE)->setDisplayOptions('view', [
      'type' => 'boolean',
      'label' => 'above',
      'weight' => 0,
      'settings' => [
        'format' => 'enabled-disabled'
      ]
    ])->setDisplayConfigurable('view', TRUE);
    
    $fields['nombre_essaie'] = BaseFieldDefinition::create('integer')->setLabel(" Nomnbre d'essaie ")->setRevisionable(TRUE)->setSettings([
      'min' => 1,
      'max' => 3
    ])->setDisplayOptions('view', [
      'label' => 'above',
      'type' => 'string',
      'weight' => -4
    ])->setDisplayOptions('form', [
      'type' => 'number'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setRequired(TRUE);
    
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')->setLabel(t('Author'))->setSetting('target_type', 'user')->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')->setDisplayOptions('form', [
      'type' => 'entity_reference_autocomplete',
      'settings' => [
        'match_operator' => 'CONTAINS',
        'size' => 60,
        'placeholder' => ''
      ],
      'weight' => 15
    ])->setDisplayConfigurable('form', TRUE)->setDisplayOptions('view', [
      'label' => 'above',
      'type' => 'author',
      'weight' => 15
    ])->setDisplayConfigurable('view', TRUE);
    
    $fields['created'] = BaseFieldDefinition::create('created')->setLabel(t('Authored on'))->setDescription(t('The time that the domain ssl was created.'))->setDisplayOptions('view', [
      'label' => 'above',
      'type' => 'timestamp',
      'weight' => 20
    ])->setDisplayConfigurable('form', TRUE)->setDisplayOptions('form', [
      'type' => 'datetime_timestamp',
      'weight' => 20
    ])->setDisplayConfigurable('view', TRUE);
    
    $fields['changed'] = BaseFieldDefinition::create('changed')->setLabel(t('Changed'))->setDescription(t('The time that the domain ssl was last edited.'));
    
    return $fields;
  }
  
}
