<?php

namespace Drupal\dcom_back_in_stock\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\dcom_back_in_stock\StockNotificationInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Entity\EntityChangedTrait;

/**
 * Defines the ContentEntityExample entity.
 *
 * @ingroup content_entity_example
 *
 * This is the main definition of the entity type. From it, an EntityType object
 * is derived. The most important properties in this example are listed below.
 *
 * id: The unique identifier of this entity type. It follows the pattern
 * 'moduleName_xyz' to avoid naming conflicts.
 *
 * label: Human readable name of the entity type.
 *
 * handlers: Handler classes are used for different tasks. You can use
 * standard handlers provided by Drupal or build your own, most probably derived
 * from the ones provided by Drupal. In detail:
 *
 * - view_builder: we use the standard controller to view an instance.
 *
 * - list_builder: We derive our own list builder class from EntityListBuilder
 *   to control the presentation.
 *
 * - form: We derive our own forms to add functionality like additional fields,
 *   redirects etc. These forms are used when the route specifies an
 *   '_entity_form' default for the entity type. Depending on the suffix
 *   (.add/.edit/.delete) of the '_entity_form' default, the form specified in
 *   the annotation is used.
 *
 * - access: Our own access controller, where we determine access rights based
 *   on permissions.
 *
 * More properties:
 *
 *  - base_table: Define the name of the table used to store the data. Make sure
 *    it is unique. The schema is automatically determined from the
 *    BaseFieldDefinitions below. The table is automatically created during
 *    installation.
 *
 *  - fieldable: Can additional fields be attached to the entity via the GUI?
 *    Can the user add fields, like they would to a node?
 *
 *  - entity_keys: How to access the fields. Specify fields from
 *    baseFieldDefinitions() which can be used as keys.
 *
 *  - links: Provide links to do standard tasks. The 'edit-form' and
 *    'delete-form' links are added to the list built by the
 *    entityListController. They will show up as action buttons in an additional
 *    column.
 *
 * There are many more properties to be used in an entity type definition. For
 * a complete overview, please refer to the '\Drupal\Core\Entity\EntityType'
 * class definition.
 *
 * The following construct is the actual definition of the entity type which
 * is read and cached. Don't forget to clear cache after changes.
 *
 * @ContentEntityType(
 *   id = "stock_notification",
 *   label = @Translation("Notice Availability"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\dcom_back_in_stock\Entity\Controller\StockNotificationListBuilder",
 *     "form" = {
 *       "add" = "Drupal\dcom_back_in_stock\Form\StockNotificationForm",
 *       "edit" = "Drupal\dcom_back_in_stock\Form\StockNotificationForm",
 *       "delete" = "Drupal\dcom_back_in_stock\Form\StockNotificationDeleteForm",
 *     },
 *     "access" = "Drupal\dcom_back_in_stock\StockNotificationAccessControlHandler",
 *   },
 *   list_cache_contexts = { "user" },
 *   base_table = "dcom_stock_notification",
 *   admin_permission = "administer stock_notification entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/commerce/config/stock_notification/{stock_notification}",
 *     "edit-form" = "/admin/commerce/config/stock_notification/{stock_notification}/edit",
 *     "delete-form" = "/admin/commerce/config/stock_notification/{stock_notification}/delete",
 *     "collection" = "/admin/commerce/config/stock_notification/list"
 *   },
 *   field_ui_base_route = "stock_notification.settings",
 * )
 *
 * The 'links' above are defined by their path. For core to find the
 * corresponding route, the route name must follow the correct pattern:
 *
 * entity.<entity-name>.<link-name> (replace dashes with underscores)
 *
 * See routing file above for the corresponding implementation
 *
 * Being derived from the ContentEntityBase class, we can override the methods
 * we want. In our case we want to provide access to the standard fields about
 * creation and changed time stamps.
 *
 * The most important part is the definitions of the field properties for this
 * entity type. These are of the same type as fields added through the GUI, but
 * they can by changed in code. In the definition we can define if the user with
 * the rights privileges can influence the presentation (view, edit) of each
 * field.
 *
 * The class also uses the EntityChangedTrait trait which allows it to record
 * timestamps of save operations.
 */
class StockNotification extends ContentEntityBase implements StockNotificationInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime() {
    return $this->get('changed')->value;
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
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the StockNotification entity.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the StockNotification entity.'))
      ->setReadOnly(TRUE);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email Address'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'email_default',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['session_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Session id for anonymous user'))
      ->setSettings([
        'max_length' => 128,
      ])
      ->setDefaultValue('')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'email_default',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User Name'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['product_variation'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Product variation.'))
      ->setSetting('target_type', 'commerce_product_variation')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['email_sent'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t("Email with notify was sent (yes/no)"))
      ->setRequired(FALSE)
      ->setDefaultValue(0)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => array(
          'display_label' => TRUE,
        ),
        'weight' => '0',
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['email_created'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Email Created'))
      ->setRequired(FALSE)
      ->setDescription(t('The time that the email was created.'));

    $fields['last_check'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last check'))
      ->setRequired(FALSE)
      ->setDescription(t('The last check time.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the StockNotification entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the StockNotification entity was last edited.'));

    $fields['theme_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Theme ID'))
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDefaultValue('diamondcbd')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => '-1',
        'label' => 'above',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
