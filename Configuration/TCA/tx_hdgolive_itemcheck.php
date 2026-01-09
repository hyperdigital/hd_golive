<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_itemcheck',
        'label' => 'item_key',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'hideTable' => true,
        'rootLevel' => 1,
        'searchFields' => 'item_key',
        'iconfile' => 'EXT:hd_golive/Resources/Public/Icons/Extension.svg',
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
            ],
        ],
        'session' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_itemcheck.session',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'item_key' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_itemcheck.item_key',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,required',
            ],
        ],
        'status' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_itemcheck.status',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_status.pending',
                        'value' => 0,
                    ],
                    [
                        'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_status.pass',
                        'value' => 1,
                    ],
                    [
                        'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_status.failed',
                        'value' => 2,
                    ],
                ],
                'default' => 0,
            ],
        ],
        'checked' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_itemcheck.checked',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'checked_time' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_itemcheck.checked_time',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
            ],
        ],
        'checked_by' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_itemcheck.checked_by',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'notes' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_note',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_hdgolive_note',
                'foreign_field' => 'itemcheck',
                'appearance' => [
                    'expandSingle' => true,
                    'useSortable' => true,
                    'newRecordLinkAddTitle' => true,
                ],
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'hidden, session, item_key, status, checked_time, checked_by, notes',
        ],
    ],
];
