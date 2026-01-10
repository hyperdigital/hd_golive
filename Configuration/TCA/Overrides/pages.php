<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$columns = [
    'tx_hdgolive_exclude_from_list' => [
        'exclude' => true,
        'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:pages.tx_hdgolive_exclude_from_list',
        'displayCond' => 'USER:Hyperdigital\\HdGolive\\Tca\\DisplayCondition->showExcludeCheckbox',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'items' => [
                ['label' => '', 'value' => 1],
            ],
        ],
    ],
    'tx_hdgolive_include_in_list' => [
        'exclude' => true,
        'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:pages.tx_hdgolive_include_in_list',
        'displayCond' => 'USER:Hyperdigital\\HdGolive\\Tca\\DisplayCondition->showIncludeCheckbox',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'items' => [
                ['label' => '', 'value' => 1],
            ],
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('pages', $columns);
ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    'tx_hdgolive_exclude_from_list,tx_hdgolive_include_in_list',
    '',
    'after:nav_hide'
);
