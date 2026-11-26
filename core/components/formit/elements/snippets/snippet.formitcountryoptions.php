<?php
/**
 * FormItCountryOptions
 *
 * Automatically generates and outputs a country list for usage in forms
 *
 * @var modX $modx
 * @var array $scriptProperties
 *
 * @package formit
 */

use Sterc\FormIt;

$fi = new FormIt($modx, $scriptProperties);

/** @var fiCountryOptions $co */
$co = $fi->loadModule('fiCountryOptions', 'countryOptions', $scriptProperties);
$co->initialize();
$co->getData();
$co->loadPrioritized();
$co->iterate();

return $co->output();