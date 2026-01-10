<?php

use Hyperdigital\HdGolive\Controller\GoLiveAjaxController;

return [
    'hd_golive_toggle_page' => [
        'path' => '/hdgolive/toggle-page',
        'target' => GoLiveAjaxController::class . '::togglePage',
    ],
    'hd_golive_toggle_page_inclusion' => [
        'path' => '/hdgolive/toggle-page-inclusion',
        'target' => GoLiveAjaxController::class . '::togglePageInclusion',
    ],
    'hd_golive_toggle_page_module' => [
        'path' => '/hdgolive/module/toggle-page',
        'target' => GoLiveAjaxController::class . '::togglePageModule',
    ],
    'hd_golive_toggle_item_module' => [
        'path' => '/hdgolive/module/toggle-item',
        'target' => GoLiveAjaxController::class . '::toggleItemModule',
    ],
    'hd_golive_page_module_entries' => [
        'path' => '/hdgolive/module/page-entries',
        'target' => GoLiveAjaxController::class . '::pageModuleEntries',
    ],
];
