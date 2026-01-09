<?php

use Hyperdigital\HdGolive\Controller\GoLiveController;

return [
    'web_hdgolive' => [
        'parent' => 'web',
        'access' => 'user',
        'iconIdentifier' => 'module-hd-golive',
        'labels' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_module.xlf',
        'path' => '/module/web/hdgolive',
        'extensionName' => 'HdGolive',
        'controllerActions' => [
            GoLiveController::class => [
                'list',
                'itemDetail',
                'startSession',
                'closeSession',
                'reopenSession',
                'updateSessionTitle',
                'removeSession',
                'shareSession',
                'unshareSession',
                'togglePage',
                'toggleItem',
                'exportWizard',
                'exportPdf',
                'startSessionButton',
                'closeSessionButton',
                'reopenSessionButton',
                'removeSessionButton',
                'shareSessionButton',
                'unshareSessionButton',
            ],
        ],
    ],
];
