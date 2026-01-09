<?php

return [
    'dependencies' => ['backend'],
    'tags' => ['backend.contextmenu', 'backend.module'],
    'imports' => [
        '@hyperdigital/hd-golive/' => 'EXT:hd_golive/Resources/Public/JavaScript/',
        '@hyperdigital/hd-golive/context-menu.js' => 'EXT:hd_golive/Resources/Public/JavaScript/context-menu.js',
        '@hyperdigital/hd-golive/module.js' => 'EXT:hd_golive/Resources/Public/JavaScript/module.js',
        '@hyperdigital/hd-golive/page-module.js' => 'EXT:hd_golive/Resources/Public/JavaScript/page-module.js',
    ],
];
