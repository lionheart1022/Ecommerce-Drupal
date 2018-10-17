<?php

namespace Drupal\commerce_klaviyo_review\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Commerce Klaviyo Review entity.
 *
 * @ingroup commerce_klaviyo_review
 *
 * @ContentEntityType(
 *   id = "klaviyo_review",
 *   label = @Translation("Commerce Klaviyo Review"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\commerce_klaviyo_review\CommerceKlaviyoReviewListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\commerce_klaviyo_review\Form\CommerceKlaviyoReviewForm",
 *       "add" = "Drupal\commerce_klaviyo_review\Form\CommerceKlaviyoReviewForm",
 *       "edit" = "Drupal\commerce_klaviyo_review\Form\CommerceKlaviyoReviewForm",
 *       "delete" = "Drupal\commerce_klaviyo_review\Form\CommerceKlaviyoReviewDeleteForm",
 *     },
 *     "access" = "Drupal\commerce_klaviyo_review\CommerceKlaviyoReviewAccessControlHandler",
 *   },
 *   base_table = "klaviyo_review",
 *   admin_permission = "administer commerce klaviyo review entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/commerce/klaviyo_review/{klaviyo_review}",
 *     "add-form" = "/admin/commerce/klaviyo_review/add",
 *     "edit-form" = "/admin/commerce/klaviyo_review/{klaviyo_review}/edit",
 *     "delete-form" = "/admin/commerce/klaviyo_review/{klaviyo_review}/delete",
 *     "collection" = "/admin/commerce/klaviyo_review",
 *   },
 *   field_ui_base_route = "commerce_klaviyo_review.settings"
 * )
 */
class CommerceKlaviyoReview extends ContentEntityBase implements CommerceKlaviyoReviewInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getComments() {
    return $this->get('comments')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function setComments(array $comments) {
    foreach ($comments as $comment) {
      $this->comments[] = $comment;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['order_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Order'))
      ->setDescription(t('The parent order.'))
      ->setSetting('target_type', 'commerce_order')
      ->setReadOnly(TRUE);
    $fields['token'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Token'))
      ->setDescription(t('Security token.'))
      ->setSettings([
        'default_value' => '',
        'max_length' => 64,
      ]);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Commerce Klaviyo Review entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Review status'))
      ->setDescription(t('A boolean indicating whether the parent order is reviewed or not.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -3,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));
    $fields['comments'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Comments'))
      ->setSetting('target_type', 'comment')
      ->setCardinality(-1)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ]);

    return $fields;
  }

}
