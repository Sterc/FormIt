<?php
use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;

if ($transport->xpdo) {
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            $modx =& $transport->xpdo;

            $path = $modx->getOption('formit.assets_path', null, MODX_ASSETS_PATH . 'components/formit/') . 'cronjob/cron.php';

            $modx->log(modX::LOG_LEVEL_INFO, 'You can setup a cronjob for automatically cleaning up of old forms, please use the following path: ' . $path);

            break;
    }
}

return true;
