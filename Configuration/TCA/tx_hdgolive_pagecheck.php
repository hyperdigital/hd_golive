<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_pagecheck',
        'label' => 'page',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'hideTable' => true,
        'rootLevel' => 1,
        'searchFields' => 'page',
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
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_pagecheck.session',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'page' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_pagecheck.page',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'pages',
                'minitems' => 1,
                'maxitems' => 1,
            ],
        ],
        'status' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_pagecheck.status',
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
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_pagecheck.checked',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'checked_time' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_pagecheck.checked_time',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
            ],
        ],
        'checked_by' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_pagecheck.checked_by',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'notes' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_note',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_hdgolive_pagenote',
                'foreign_field' => 'pagecheck',
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
            'showitem' => 'hidden, session, page, status, checked_time, checked_by, notes',
        ],
    ],
];
