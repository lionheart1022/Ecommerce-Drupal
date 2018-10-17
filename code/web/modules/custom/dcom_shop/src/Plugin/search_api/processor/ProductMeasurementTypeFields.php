<?php

namespace Drupal\dcom_shop\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds access checks for profiles.
 *
 * @SearchApiProcessor(
 *   id = "cbd_product_measurement_type_fields",
 *   label = @Translation("Product Measurement Type Fields"),
 *   description = @Translation("Processed cbd_product_measurement_type_fields index of CBD Product."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class ProductMeasurementTypeFields extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $entity_type = $datasource->getEntityTypeId();

      foreach ($this->getEntityTypeFields($entity_type) as $field_name => $field) {
        $definition = [
          'label' => $this->t('Product Measurement Type Field'),
          'description' => $this->t('Processed field with measurement type.'),
          'type' => 'string',
          'processor_id' => $this->getPluginId(),
        ];
        $properties[$field_name . '_string'] = new ProcessorProperty($definition);
      }
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    if ($item->getDatasourceId()) {
      $entity_type = $item->getOriginalObject()->getValue()->getEntityTypeId();
      $fields = $this->getEntityTypeFields($entity_type);
      $object = $item->getOriginalObject()->getValue();

      foreach ($fields as $field_name => $field_def) {
        $number = (string) intval($object->{$field_name}->number);
        $unit = $object->{$field_name}->unit;
        $value = $number == 0 ? NULL : $number . $unit;

        $fields = $this->getFieldsHelper()
          ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), $field_name . '_string');

        foreach ($fields as $field) {
          $field->addValue($value);

        }
      }
    }
  }

  /**
   * Get all entity type fields.
   *
   * @param string $entity_type
   *   Entity type id.
   *
   * @return array
   *   Array of fields with specific type.
   */
  protected function getEntityTypeFields($entity_type) {
    $fields = [];
    $bundles = \Drupal::service('entity_type.bundle.info')->getAllBundleInfo();
    if (isset($bundles[$entity_type])) {
      foreach ($bundles[$entity_type] as $bundle => $bundle_info) {
        $entity_fields = \Drupal::service('entity.manager')
          ->getFieldDefinitions($entity_type, $bundle);
        foreach ($entity_fields as $field) {
          if ($field->getType() == 'physical_measurement') {
            $fields[$field->getName()] = $field;
          }
        }
      }
    }

    return $fields;
  }

}
