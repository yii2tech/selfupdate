<?php
/**
 * Configuration file for self-update command.
 *
 * Tip: you can use $_SERVER['SCRIPT_FILENAME'] as a path to yii console entry script.
 *
 * @see yii2tech\selfupdate\SelfUpdateController
 */

return [
    // list of email addresses, which should be used to send execution reports
    'emails' => [
        //'developer@domain.com',
    ],
    // Mailer component to be used
    'mailer' => 'mailer',
    // Mutex component to be used
    'mutex' => 'mutex',
    // path to project root directory (VCS root directory)
    'projectRootPath' => '@app',
    // web path stubs configuration
    'webPaths' => [
        [
            'path' => '@app/web',
            'link' => '@app/httpdocs',
            'stub' => '@app/webstub',
        ],
    ],
    // cache components to be flushed
    'cache' => [
        'cache'
    ],
    // temporary directories, which should be cleared after project update
    'tmpDirectories' => [
        '@app/web/assets',
        '@runtime/URI',
        '@runtime/HTML',
        '@runtime/debug',
    ],
    // list of commands, which should be executed before project update begins
    'beforeUpdateCommands' => [],
    // list of shell commands, which should be executed after project update
    'afterUpdateCommands' => [
        "php {$_SERVER['SCRIPT_FILENAME']} migrate/up --interactive=0",
    ],
    // adjust Composer settings, if necessary :
    /*'composerBinPath' => 'composer',
    'composerRootPaths' => [
        '@app'
    ],*/
];
