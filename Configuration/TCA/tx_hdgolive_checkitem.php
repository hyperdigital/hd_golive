<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_checkitem',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'rootLevel' => 1,
        'hideTable' => false,
        'sortby' => 'sorting',
        'searchFields' => 'title,item_key',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
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
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_checkitem.title',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'eval' => 'trim,required',
            ],
        ],
        'item_key' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_checkitem.item_key',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,required,alphanum_x',
            ],
        ],
        'description' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_checkitem.description',
            'config' => [
                'type' => 'text',
                'rows' => 6,
                'eval' => 'trim',
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'hidden, title, item_key, description',
        ],
    ],
];
