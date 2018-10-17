<?php

namespace Drupal\dcom_odoo_migrate\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use finfo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates URI for images imported from Odoo.
 *
 * @code
 * process:
 *   uri:
 *     plugin: dcom_odoo_image_uri
 *     source:
 *       - x_image
 *       - '@destination_full_path'
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_odoo_image_uri"
 * )
 */
class DcomOdooImageUri extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Image mime type to image extension mapping.
   *
   * @var array
   */
  protected static $mimeExtensionMapping = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
  ];

  /**
   * Constructs a DcomOdooImageUri object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $id_map = $row->getIdMap();

    $file_uri = NULL;
    if ($binary_data = base64_decode(array_shift($value))) {
      $valid_extension = $this->validateImage($binary_data);

      // Return existing file URI if possible.
      if (!empty($id_map['destid1'])
        && $file = $this->entityTypeManager->getStorage('file')
          ->load($id_map['destid1'])) {
        // Updates existed file.
        $file_uri = $file->uri->value;

        // Removes old file if extension is different.
        $path_info = pathinfo($file_uri);
        if ($path_info['extension'] != $valid_extension) {
          $this->fileSystem->unlink($file_uri);
          $file_uri = $path_info['dirname'] . '/' . $path_info['filename'] . '.' . $valid_extension;
        }
      }
      elseif ($file_destination = array_shift($value)) {
        // Creates new file.
        $file_uri = "{$file_destination}.{$valid_extension}";
      }

      if ($file_uri) {
        file_put_contents($file_uri, $binary_data);
      }
    }

    return $file_uri;
  }

  /**
   * Validate image file.
   *
   * @param string $binary_data
   *   File binary.
   *
   * @return mixed
   *   Image file extension. Ex: "jpg", "png", etc.
   *
   * @throws \Drupal\migrate\MigrateSkipRowException
   *   Skip row if file is not valid.
   */
  protected function validateImage($binary_data) {
    $mime_type = $this->getFileMimeType($binary_data);
    if (array_key_exists($mime_type, self::$mimeExtensionMapping)) {
      return self::$mimeExtensionMapping[$mime_type];
    }

    throw new MigrateSkipRowException("The image file is not valid. Mime type: {$mime_type}");
  }

  /**
   * Find file mime type.
   *
   * @param string $binary_data
   *   File binary.
   *
   * @return mixed
   *   Mime type. Ex: "image/jpeg".
   */
  protected function getFileMimeType($binary_data) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    return $finfo->buffer($binary_data);
  }

}
