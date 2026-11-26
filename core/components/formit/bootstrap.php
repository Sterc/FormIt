<?php
require_once __DIR__ . '/vendor/autoload.php';

// Add your classes to modx's autoloader
$modx->addPackage('Sterc\FormIt\Model', $namespace['path'] . 'src/', null, 'Sterc\\FormIt\\');

// Register base class in the service container
$modx->services->add('formit', function() use ($modx) {
    return new \Sterc\FormIt($modx);
});
