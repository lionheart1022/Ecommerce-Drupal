<?php

/**
 * @file
 * Provides views integration.
 */

/**
 * Implements hook_views_data_alter().
 */
function dcom_shop_views_data_alter(array &$data) {
  // Filter handler for SearchAPI index.
  if (isset($data['search_api_index_products_index']['field_visibility_option'])) {
    $visibility_filter_rewrites = [
      'title' => t('DCOM @label (@name)', [
        '@label' => 'Visibility option',
        '@name' => 'field_visibility_option',
      ]),
      'title short' => 'DCOM Visibility option',
      'filter' => [
        'id' => 'dcom_product_visibility_filter',
        'real field' => 'field_visibility_option',
      ],
    ];

    $data['search_api_index_products_index']['dcom_field_visibility_option'] = $data['search_api_index_products_index']['field_visibility_option'];
    $data['search_api_index_products_index']['dcom_field_visibility_option']['title'] = $visibility_filter_rewrites['title'];
    $data['search_api_index_products_index']['dcom_field_visibility_option']['title short'] = $visibility_filter_rewrites['title short'];
    $data['search_api_index_products_index']['dcom_field_visibility_option']['filter'] = array_merge($data['search_api_index_products_index']['dcom_field_visibility_option']['filter'], $visibility_filter_rewrites['filter']);
  }

  // Filter handler for Drupal DataBase.
  if (isset($data['commerce_product__field_visibility_option']['field_visibility_option_value'])) {
    $visibility_filter_rewrites = [
      'title' => t('DCOM @label (@name)', [
        '@label' => 'Visibility option',
        '@name' => 'field_visibility_option',
      ]),
      'title short' => 'DCOM Visibility option',
      'filter' => [
        'id' => 'dcom_product_visibility_filter',
        'field' => 'field_visibility_option_value',
      ],
    ];
    $data['commerce_product__field_visibility_option']['dcom_field_visibility_option_value'] = $data['commerce_product__field_visibility_option']['field_visibility_option_value'];
    $data['commerce_product__field_visibility_option']['dcom_field_visibility_option_value']['title'] = $visibility_filter_rewrites['title'];
    $data['commerce_product__field_visibility_option']['dcom_field_visibility_option_value']['title short'] = $visibility_filter_rewrites['title short'];
    $data['commerce_product__field_visibility_option']['dcom_field_visibility_option_value']['filter'] = array_merge($data['commerce_product__field_visibility_option']['dcom_field_visibility_option_value']['filter'], $visibility_filter_rewrites['filter']);
  }
}
