<?php
/**
 * FormItRetriever
 *
 * Retrieves a prior form submission that was stored with the &store property
 * in a FormIt call.
 *
 * @var modX $modx
 * @var array $scriptProperties
 *
 * @package formit
 */

use Sterc\FormIt;

$fi = new FormIt($modx, $scriptProperties);

/* setup properties */
$placeholderPrefix    = $modx->getOption('placeholderPrefix', $scriptProperties, 'fi.');
$eraseOnLoad          = $modx->getOption('eraseOnLoad', $scriptProperties, false);
$redirectToOnNotFound = $modx->getOption('redirectToOnNotFound', $scriptProperties, false);

/* fetch data from cache and set to placeholders */
$fi->loadRequest();
$fi->request->loadDictionary();

$data = $fi->request->dictionary->retrieve();
if (!empty($data)) {
    /* set data to placeholders */
    foreach ($data as $k => $v) {
        /*checkboxes & other multi-values are stored as arrays, must be imploded*/
        if (is_array($v)) {
            $data[$k] = implode(',', $v);
        }
    }

    $modx->toPlaceholders($data, $placeholderPrefix, '');

    /* if set, erase the data on load, otherwise depend on cache expiry time */
    if ($eraseOnLoad) {
        $fi->request->dictionary->erase();
    }
/* if the data's not found, and we want to redirect somewhere if so, do here */
} elseif (!empty($redirectToOnNotFound)) {
    $modx->sendRedirect($modx->makeUrl($redirectToOnNotFound));
}

return '';