<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_note',
        'label' => 'note_text',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'hideTable' => true,
        'rootLevel' => 1,
        'sortby' => 'sorting',
        'searchFields' => 'note_text',
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
        'itemcheck' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_itemcheck',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_hdgolive_itemcheck',
                'minitems' => 1,
                'maxitems' => 1,
            ],
        ],
        'note_text' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_note.note_text',
            'config' => [
                'type' => 'text',
                'rows' => 4,
                'cols' => 40,
                'eval' => 'trim',
            ],
        ],
        'note_status' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_note.note_status',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_note.status.todo',
                        'value' => 0,
                    ],
                    [
                        'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_note.status.solved',
                        'value' => 1,
                    ],
                    [
                        'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_note.status.denied',
                        'value' => 2,
                    ],
                ],
                'default' => 0,
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'hidden, itemcheck, note_status, note_text',
        ],
    ],
];
