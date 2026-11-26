<?php
/**
 * FormItStateOptions
 *
 * Automatically generates and outputs a U.S. state list for usage in forms
 *
 * @var modX $modx
 * @var array $scriptProperties
 *
 * @package formit
 */
use Sterc\FormIt;

$fi = new FormIt($modx, $scriptProperties);

/** @var fiStateOptions $so */
$so = $fi->loadModule('fiStateOptions', 'stateOptions', $scriptProperties);
$so->initialize();
$so->getData();
$so->iterate();

return $so->output();