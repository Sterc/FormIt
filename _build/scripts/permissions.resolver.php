<?php
use xPDO\Transport\xPDOTransport;
use MODX\Revolution\modAccessPolicy;
use MODX\Revolution\modAccessPolicyTemplate;
use MODX\Revolution\modAccessPermission;

$package = 'FormIt';

$permissions = [
    [
        'name'          => 'formit',
        'description'   => 'To view the formit package.',
        'templates'     => ['AdministratorTemplate']
    ], [
        'name'          => 'formit_encryptions',
        'description'   => 'To view the formit package, encriptions part.',
        'templates'     => ['AdministratorTemplate'],
        'policies'      => ['Administrator']
    ]
];

$success = false;
if ($transport->xpdo) {
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            $modx =& $transport->xpdo;

            $templateNamesById = [];
            foreach ($modx->getCollection(modAccessPolicyTemplate::class) as $accessTemplate) {
                $templateNamesById[$accessTemplate->get('id')] = $accessTemplate->get('name');

                foreach ($permissions as $permission) {
                    if (!isset($permission['templates']) || in_array($accessTemplate->get('name'), $permission['templates'])) {
                        $accessPermission = $modx->getObject(modAccessPermission::class, [
                            'name'      => $permission['name'],
                            'template'  => $accessTemplate->get('id')
                        ]);

                        if (!$accessPermission) {
                            $accessPermission = $modx->newObject(modAccessPermission::class);

                            if ($accessPermission) {
                                $accessPermission->fromArray(array_merge($permission, [
                                    'template'  => $accessTemplate->get('id'),
                                    'value'     => 1
                                ]));

                                $accessPermission->save();
                            }
                        }
                    }
                }
            }

            foreach ($modx->getCollection(modAccessPolicy::class) as $accessPolicy) {
                $data = $accessPolicy->get('data');
                $templateName = $templateNamesById[$accessPolicy->get('template')] ?? null;

                foreach ($permissions as $permission) {
                    /* skip policies whose template never got this permission bit added above */
                    if (isset($permission['templates']) && !in_array($templateName, $permission['templates'], true)) {
                        continue;
                    }

                    if (isset($permission['policies'])) {
                        $data[$permission['name']] = in_array($accessPolicy->get('name'), $permission['policies'], true);
                    } else {
                        $data[$permission['name']] = true;
                    }
                }

                $accessPolicy->set('data', $data);
                $accessPolicy->save();
            }

            $success = true;

            break;
        case xPDOTransport::ACTION_UNINSTALL:
            $success = true;

            break;
    }
}

return $success;
