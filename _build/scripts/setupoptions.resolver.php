<?php

use MODX\Revolution\modSystemSetting;
use xPDO\xPDO;
use xPDO\Transport\xPDOTransport;

$package = 'FormIt';

$success = false;
switch ($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:
        $settings = ['user_name', 'user_email'];
        foreach ($settings as $key) {
            if (isset($options[$key])) {
                $settingObject = $transport->xpdo->getObject(
                    modSystemSetting::class,
                    ['key' => strtolower($package) . '.' . $key]
                );

                if ($settingObject) {
                    $settingObject->set('value', $options[$key]);
                    $settingObject->save();
                } else {
                    $error = '[' . $package . '] ' . strtolower($package) . '.' . $key . ' setting could not be found,';
                    $error .= ' so the setting could not be changed.';

                    $transport->xpdo->log(xPDO::LOG_LEVEL_ERROR, $error);
                }
            }
        }

        $success = true;
        break;
    case xPDOTransport::ACTION_UNINSTALL:
        $success = true;
        break;
}

return $success;
