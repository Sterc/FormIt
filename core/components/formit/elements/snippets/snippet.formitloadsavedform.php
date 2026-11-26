<?php
use Sterc\FormIt\Model\FormItForm;

/**
 * A custom FormIt prehook for fetching saved form data. - Based on FormItSaveForm
 *
 * @var modX $modx
 * @var array $scriptProperties
 * @var FormIt $formit
 * @var fiHooks $hook
 *
 * @package formit
 */
/* setup default properties */
// If prehook fails do we continue?
$return          = $modx->getOption('returnValueOnFail', $formit->config, true);
$formEncrypt     = $modx->getOption('formEncrypt', $formit->config, false);
$formFields      = $modx->getOption('formFields', $formit->config, false);
$fieldNames      = $modx->getOption('fieldNames', $formit->config, false);
$updateSavedForm = $modx->getOption('updateSavedForm', $formit->config, false); // true, false, '1', '0', or 'values'

// If FormIt config says don't update, don't do it
if (!$updateSavedForm) {
    return $return;
}

// In order to load form values, the user must provide the hash key somehow
// Usually with a $_GET parameter, but a property in $formit->config will override.
$formHashKeyField = $modx->getOption('savedFormHashKeyField', $formit->config, 'savedFormHashKey');
$formHashKey      = '';
if (isset($_GET[$formHashKeyField])) {
    $formHashKey = (string) $_GET[$formHashKeyField];
}

if ($hook->getValue($formHashKeyField)) {
    $formHashKey = (string) $hook->getValue($formHashKeyField);
}

if (isset($formit->config[$formHashKeyField])) {
    $formHashKey = $formit->config[$formHashKeyField];
}

// our hashing methods return 32 chars. if no valid hash key we're done here.
if (strlen($formHashKey) !== 32) {
    return $return;
}

// Try to fetch the saved form
$savedForm = $modx->getObject(FormItForm::class, ['hash' => $formHashKey]);
if (!$savedForm) {
    return $return;
}

if ($formFields) {
    $formFields = explode(',', $formFields);
    foreach($formFields as $k => $v) {
        $formFields[$k] = trim($v);
    }
}

// Initialize the data array
// Handle encryption
if ($formEncrypt) {
    $encrypted_data = $savedForm->get('values');
    $data           = $savedForm->decrypt($encrypted_data);
} else {
    $data = $savedForm->get('values');
}

if (is_string($data)) {
    $data = $modx->fromJSON($data);
}

if (!is_array($data)) {
    return $return;
}

//Change the fieldnames
if ($fieldNames) {
    $newDataArray   = [];
    $fieldLabels    = [];
    $formFieldNames = explode(',', $fieldNames);
    foreach($formFieldNames as $formFieldName){
        list($name, $label) = explode('==', $formFieldName);
        // reverse order from FormItSaveForm snippet
        $fieldLabels[trim($label)] = trim($name);
    }

    foreach ($data as $key => $value) {
        if ($fieldLabels[$key]) {
            $newDataArray[$fieldLabels[$key]] = $value;
        } else {
            $newDataArray[$key] = $value;
        }
    }

    $data = $newDataArray;
}

// Always pass back in the provided hash key
$data[$formHashKeyField] = $formHashKey;

$hook->setValues($data);

return true;