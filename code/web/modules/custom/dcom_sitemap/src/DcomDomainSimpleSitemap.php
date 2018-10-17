<?php

namespace Drupal\dcom_sitemap;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathValidator;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain_simple_sitemap\DomainSimpleSitemap;
use Drupal\simple_sitemap\Batch;
use Drupal\simple_sitemap\EntityHelper;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorManager;
use Drupal\simple_sitemap\Simplesitemap;
use Drupal\simple_sitemap\SitemapGenerator;

/**
 * Class DomainSimpleSitemap.
 *
 * @package Drupal\dcom_sitemap
 */
class DcomDomainSimpleSitemap extends DomainSimpleSitemap {

  /**
   * Generates the sitemap for all languages and saves it to the db.
   *
   * @param string $from
   *   Can be 'form', 'cron', 'drush' or 'nobatch'.
   *   This decides how the batch process is to be run.
   */
  public function generateSitemap($from = 'form') {

    $this->batch->setBatchSettings([

      'batch_process_limit' => $this->getSetting('batch_process_limit', NULL),
      'max_links' => $this->getSetting('max_links', 2000),
      'skip_untranslated' => $this->getSetting('skip_untranslated', FALSE),
      'remove_duplicates' => $this->getSetting('remove_duplicates', TRUE),
      'excluded_languages' => $this->getSetting('excluded_languages', []),
      'from' => $from,
    ]);

    $plugins = $this->urlGeneratorManager->getDefinitions();

    usort($plugins, function ($a, $b) {
      return $a['weight'] - $b['weight'];
    });

    // Domains where collections plugin makes sense to run.
    $collections_domains = [
      'medipets_domain',
      'mbio_domain',
      'diamondhemp_domain',
      'diamondcbd_domain',
    ];

    // For each chunk/domain generate custom URLs and entities.
    $domains = $this->entityTypeManager->getStorage('domain')->loadMultiple();
    foreach ($domains as $domain) {
      if ($domain->status()) {
        foreach ($plugins as $plugin) {
          if ($plugin['id'] == 'dcom_collections' && !in_array($domain->id(), $collections_domains)) {
            continue;
          }
          if ($plugin['instantiateForEachDataSet']) {
            foreach ($this->urlGeneratorManager
              ->createInstance($plugin['id'])->getDataSets() as $data_sets) {
              $this->batch->addDomainOperation($plugin['id'], $domain, $data_sets);
            }
          }
          else {
            $this->batch->addDomainOperation($plugin['id'], $domain);
          }
        }
      }
    }

    $success = $this->batch->start();
    return $from === 'nobatch' ? $this : $success;
  }

}
