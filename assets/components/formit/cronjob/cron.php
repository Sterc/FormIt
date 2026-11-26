<?php
/**
 * FormIt Cronjob
 *
 * @package formit
 */
if(!(php_sapi_name() === 'cli')) {
    header("HTTP/1.1 400 Bad Request");
    print 'Only runnable from CLI.';
    exit;
}

require_once dirname(__DIR__, 4) . '/config.core.php';
require_once MODX_CORE_PATH.'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CONNECTORS_PATH . 'index.php';

$modx->formit = $modx->services->get('formit');
$modx->lexicon->load('formit:default');

/* handle request */
$path = $modx->getOption('processorsPath', $modx->formit->config, MODX_CORE_PATH . '/components/formit/src/FormIt/Processors/');
$response = $modx->runProcessor('Sterc\\FormIt\\Processors\\Mgr\\Forms\\Clean', [], [
    'processors_path' => $path
]);

if(!empty($response)){
    if ($response->isError()) {
        print $response->getMessage();
    }
}
