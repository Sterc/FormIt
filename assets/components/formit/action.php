<?php
/**
 * FormIt AJAX Endpoint
 *
 * Handles AJAX form submissions via MODX_API_MODE.
 * Retrieves snippet configuration from session/cache by hash,
 * processes the form through FormIt, and returns JSON response.
 *
 * @package formit
 */

/* Reject non-AJAX requests */
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: ' . '/');
    exit;
}

/* Boot MODX in API mode */
define('MODX_API_MODE', true);
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.core.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_BASE_PATH . 'index.php';

$modx->getParser();

header('Content-Type: application/json; charset=UTF-8');

/* Validate formProperties hash */
$hash = isset($_POST['formProperties']) ? $_POST['formProperties'] : '';
if (!preg_match('/^[a-f0-9]{32}$/', $hash)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $modx->lexicon('formit.err_config_ns')
    ]);
    exit;
}

/* Retrieve stored config: session first, then cache fallback */
$config = null;
if (!empty($_SESSION['FormIt'][$hash])) {
    $config = $_SESSION['FormIt'][$hash];
} else {
    $config = $modx->cacheManager->get('formit/props_' . $hash);
}

if (empty($config) || !is_array($config)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $modx->lexicon('formit.err_config_expired')
    ]);
    exit;
}

/* Set up resource context from stored pageId */
$pageId = isset($config['pageId']) ? (int) $config['pageId'] : 0;
unset($config['pageId']);

if ($pageId) {
    $resource = $modx->getObject('modResource', $pageId);
    if ($resource) {
        $context = $resource->get('context_key');
        if ($context !== 'web') {
            $modx->switchContext($context);
        }
        $modx->resource = $resource;
    }
}

/* Load FormIt lexicon */
$modx->lexicon->load('formit:default');

/* Load and run FormIt */
$corePath = $modx->getOption('formit.core_path', null,
    $modx->getOption('core_path') . 'components/formit/');
$modelPath = $corePath . 'model/formit/';
$modx->loadClass('FormIt', $modelPath, true, true);

$fi = new FormIt($modx, $config);
$fi->initialize($modx->context->get('key'));

$response = $fi->processForm();

@ob_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
