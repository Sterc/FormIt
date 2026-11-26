<?php
/**
 * FormItIsChecked
 *
 * Custom output filter that returns checked="checked" if the value is set
 *
 * @var string $input
 * @var string $options
 * @var modX $modx
 *
 * @package formit
 */
$output = ' ';
if ($input == $options) {
    $output = ' checked="checked"';
}
$input = strpos($input, '"') ? $modx->fromJSON($input) : explode(',', $input);
if (!empty($input) && is_array($input) && in_array($options,$input)) {
  $output = ' checked="checked"';
}
return $output;