<?php
/**
 * FormIt plugin
 *
 * @package formit
 */
use Sterc\FormIt;

switch ($modx->event->name) {
    case 'OnManagerPageBeforeRender':
        // If migration status is false, show migrate alert message bar in manager
        if (method_exists('Sterc\FormIt', 'encryptionMigrationStatus')) {
            $formit = new FormIt($modx, $scriptProperties);

            if (!$formit->encryptionMigrationStatus()) {
                $modx->lexicon->load('formit:mgr');

                $properties = ['message' => $modx->lexicon('formit.migrate_alert')];
                $chunk      = $formit->_getTplChunk('migrate/alert');
                if ($chunk) {
                    $modx->regClientStartupHTMLBlock($chunk->process($properties));
                    $modx->regClientCSS($formit->config['css_url'] . 'migrate.css');
                }
            }
        }

    break;
}