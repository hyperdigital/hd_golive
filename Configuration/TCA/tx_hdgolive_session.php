<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_session',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'hideTable' => true,
        'searchFields' => 'title,site_identifier',
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
        'title' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_session.title',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'eval' => 'trim,required',
            ],
        ],
        'site_identifier' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_session.site_identifier',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'eval' => 'trim,required',
            ],
        ],
        'shared' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_session.shared',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
            ],
        ],
        'closed' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_session.closed',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
            ],
        ],
        'closed_time' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_session.closed_time',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
            ],
        ],
        'closed_by' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_session.closed_by',
            'config' => [
                'type' => 'input',
                'eval' => 'int',
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'hidden, title, site_identifier, shared, closed, closed_time, closed_by',
        ],
    ],
];
