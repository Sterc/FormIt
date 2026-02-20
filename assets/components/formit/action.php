<?php
/**
 * FormIt AJAX Endpoint
 *
 * Handles AJAX form submissions via MODX_API_MODE.
 * Retrieves snippet configuration from session/cache by ajaxToken,
 * processes the form through FormIt, and returns JSON response.
 *
 * @package formit
 */

use MODX\Revolution\modResource;
use Sterc\FormIt;

/* Reject non-AJAX requests */
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: /');
    exit;
}

/* Boot MODX in API mode */
define('MODX_API_MODE', true);
require_once dirname(__DIR__, 3) . '/config.core.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_BASE_PATH . 'index.php';

$modx->lexicon->load('formit:default');
header('Content-Type: application/json; charset=UTF-8');

/* Validate ajaxToken */
$ajaxToken = $_POST['ajaxToken'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $ajaxToken)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $modx->lexicon('formit.err_config_ns')
    ]);
    exit;
}

/* Retrieve stored config: session first, then cache fallback */
$config = $_SESSION['formit'][$ajaxToken] ?? null;
if (empty($config)) {
    $config = $modx->cacheManager->get('formit/props_' . $ajaxToken);
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
$pageId = (int) ($config['pageId'] ?? 0);
if ($pageId && $resource = $modx->getObject(modResource::class, $pageId)) {
    $modx->switchContext($resource->get('context_key'));
    $modx->resource = $resource;
}

/* Parser is not auto-initialized in API mode, but required by hooks that process chunks */
$modx->getParser();

/* Run FormIt — same flow as snippet.formit.php */
$fi = new FormIt($modx, $config);
$fi->returnOutput = true;

$fi->initialize($modx->context->get('key'));
$fi->loadRequest();

$fields = $fi->request->prepare();
$output = $fi->request->handle($fields);

/* Build JSON response */
$response = ['success' => true];

if ($fi->hasErrors()) {
    $response['success'] = false;
    $response['errors']  = $fi->getErrors();
}

if ($fi->postHooks) {
    $url = $fi->postHooks->getRedirectUrl();
    if (!empty($url)) {
        $response['redirect_url'] = $url;
    }
}

/* Collect all placeholders for AJAX response */
$prefix    = $fi->config['placeholderPrefix'];
$prefixLen = strlen($prefix);

$placeholders = [];
foreach ($modx->placeholders as $key => $value) {
    if (strpos($key, $prefix) === 0) {
        /* Remove prefix for response */
        $placeholders[substr($key, $prefixLen)] = $value;
    }
}
$response['placeholders'] = $placeholders;

@ob_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
