<?php
/**
 * FormIt
 *
 * A dynamic form processing Snippet for MODx Revolution.
 *
 * @var modX $modx
 * @var array $scriptProperties
 *
 * @package formit
 */
use Sterc\FormIt;

$fi = new FormIt($modx, $scriptProperties);

$fi->initialize($modx->context->get('key'));
$fi->loadRequest();

$fields = $fi->request->prepare();

return $fi->request->handle($fields);