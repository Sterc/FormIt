<?php
/**
 * FormIt Connector
 *
 * @package formit
 */
require_once dirname(__DIR__, 3) . '/config.core.php';
require_once MODX_CORE_PATH.'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CONNECTORS_PATH . 'index.php';

$modx->formit = $modx->services->get('formit');

$modx->lexicon->load('formit:default');

/* handle request */
$path = $modx->getOption('processorsPath', $modx->formit->config, MODX_CORE_PATH . '/components/formit/src/FormIt/Processors/');
$modx->request->handleRequest([
    'processors_path' => $path,
    'location'        => ''
]);
