<?php
/**
 * Simple PHP script for generating domains and aliases.
 */

$domains = [
  'chillgummies' => 'ChillGummies.com',
  'relaxgummies' => 'RelaxGummies.com',
  'cbdliquidgold' => 'CBDLiquidGold.com',
  'diamondcbdoil' => 'DiamondCBDOil.com',
  'chongchoicecbd' => 'ChongChoiceCBD.com',
  'dcom_56cbd' => '56cbd.com',
  'bluecbd' => 'BLUECBD.COM',
];

foreach ($domains as $theme => $domain) {
  $host = strtolower($domain);

  $domain_machine_name = $theme . '_domain';

  $domain_record_filename = 'domain.record.' . $domain_machine_name . '.yml';
  $id = (int) rand(10000, 20000);
  $yml = <<<YML
langcode: en
status: true
dependencies: {  }
id: ${domain_machine_name}
domain_id: ${id}
hostname: ${host}
name: ${domain}
scheme: variable
weight: 10
is_default: false

YML;

  file_put_contents($domain_record_filename, $yml);



  $alias_machine_name = 'www_' . $theme;
  $alias_fliename = 'domain_alias.alias.' . $alias_machine_name . '.yml';
  $alias_yml = <<<YML
langcode: en
status: true
dependencies: {  }
id: ${alias_machine_name}
domain_id: ${domain_machine_name}
pattern: www.${host}
redirect: 0
environment: default

YML;
  file_put_contents($alias_fliename, $alias_yml);



  $alias_machine_name = 'dev_' . $theme;
  $alias_fliename = 'domain_alias.alias.' . $alias_machine_name . '.yml';
  $alias_yml = <<<YML
langcode: en
status: true
dependencies: {  }
id: ${alias_machine_name}
domain_id: ${domain_machine_name}
pattern: ${theme}.dev.diamondcommerce.cpldev.com
redirect: 0
environment: development

YML;
  file_put_contents($alias_fliename, $alias_yml);



  $alias_machine_name = 'local_' . $theme;
  $alias_fliename = 'domain_alias.alias.' . $alias_machine_name . '.yml';
  $alias_yml = <<<YML
langcode: en
status: true
dependencies: {  }
id: ${alias_machine_name}
domain_id: ${domain_machine_name}
pattern: ${theme}.diamondcommerce.docker.localhost
redirect: 0
environment: local

YML;
  file_put_contents($alias_fliename, $alias_yml);

}
