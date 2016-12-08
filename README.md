Project Self Update Extension for Yii 2
=======================================

This extension allows automatic project updating in case its source code is maintained via version control system, such
as [GIT](https://git-scm.com/) or [Mercurial](https://mercurial.selenic.com/). Such update includes following steps:
 - check if there are any changes at VSC remote repository
 - link web server web directories to the stubs, while project update is running
 - apply remote VCS changes
 - update 'vendor' directory via Composer
 - clear application cache and temporary directories
 - perform additional actions, like applying database migrations
 - link web server web directories to the project web directories, once update is complete
 - notify developer(s) about update result via email

> Note: this solution is very basic and may not suite for the complex project update workflow. You may consider
  usage of more sophisticated tools like [Phing](https://www.phing.info/). However, this extension may be used as a part
  of such solution.

For license information check the [LICENSE](LICENSE.md)-file.

[![Latest Stable Version](https://poser.pugx.org/yii2tech/selfupdate/v/stable.png)](https://packagist.org/packages/yii2tech/selfupdate)
[![Total Downloads](https://poser.pugx.org/yii2tech/selfupdate/downloads.png)](https://packagist.org/packages/yii2tech/selfupdate)
[![Build Status](https://travis-ci.org/yii2tech/selfupdate.svg?branch=master)](https://travis-ci.org/yii2tech/selfupdate)


Requirements
------------

This extension requires Linux OS.


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist yii2tech/selfupdate
```

or add

```json
"yii2tech/selfupdate": "*"
```

to the require section of your composer.json.


Usage
-----

This extension provides special console controller [[yii2tech\selfupdate\SelfUpdateController]], which allows automatic updating of
the project, if its source code is maintained via version control system.
In order to enable this controller in your project, you should add it to your console application `controllerMap` at
configuration file:

```php
return [
    'controllerMap' => [
        'self-update' => 'yii2tech\selfupdate\SelfUpdateController'
    ],
    // ...
];
```

Now you should able to use 'self-update' command via console:

```
yii self-update
```


## Project preparation <span id="project-preparation"></span>

In order to use 'self-update' command, you should perform several preparations in your project, allowing
certain shell commands to be executed in non-interactive (without user prompt) mode.

First of all, you should clone (checkout) your project from version control system and switch project working copy
to the branch, which should be used at this particular server. Using GIT this actions can be performed via following commands:

```
cd /path/to/my/project
git clone git@my-git-server.com/myproject.git
git checkout production
```

> Attention: you need to configure your VCS (or at least your project working copy) in the way interacting with remote
  repository does NOT require user prompt, like input of username or password! This can be achieved using authentication
  keys or 'remember password' feature.

Then you should make project operational performing all necessary actions for its initial deployment, like running
'composer install', creating necessary directories and so on.


## Using self-update command <span id="using-self-update-command"></span>

Once project is setup you need to create a configuration for its updating. This can be done using 'self-update/config'
command:

```
yii self-update/config @app/config/self-update.php
```

This will generate configuration file, which should be manually adjusted depending on the particular project structure
and server environment. For the common project such configuration file may look like following:

```php
<?php

return [
    // list of email addresses, which should be used to send execution reports
    'emails' => [
        'developer@domain.com',
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
    // list of shell commands, which should be executed after project update
    'afterUpdateCommands' => [
        'php ' . escapeshellarg($_SERVER['SCRIPT_FILENAME']) . ' migrate/up --interactive=0',
    ],
];
```

Please refer to [[\yii2tech\selfupdate\SelfUpdateController]] for particular option information.

Once you have made all necessary adjustments at configuration file, you can run 'self-update/perform' command with it:

```
yii self-update @app/config/self-update.php
```

You may setup default configuration file name inside the `controllerMap` specification via [[yii2tech\selfupdate\SelfUpdateController::$configFile]]:

```php
return [
    'controllerMap' => [
        'self-update' => [
            'class' => 'yii2tech\selfupdate\SelfUpdateController',
            'configFile' => '@app/config/self-update.php',
        ]
    ],
    // ...
];
```

Then invocation of the self-update command will be much more clear:

```
yii self-update
```

> Note: it is not necessary to create a separated configuration file: you can configure all necessary fields of
  [[yii2tech\selfupdate\SelfUpdateController]] inside `controllerMap` specification, but such approach is not recommended.


Self Update Workflow
--------------------

While running, [[yii2tech\selfupdate\SelfUpdateController]] performs following steps:

 - check if there are any changes at VSC remote repository
 - link web server web directories to the stubs, while project update is running
 - apply remote VCS changes
 - update 'vendor' directory via Composer
 - clear application cache and temporary directories
 - perform additional actions, like applying database migrations
 - link web server web directories to the project web directories, once update is complete
 - notify developer(s) about update result via email

At the first stage there is a check for any changes in the remote repository. If there is no changes in remote
repository for the current project VCS working copy branch, no further actions will be performed!

If remote changes detected, the symbolic links pointing to the project '@web' directory will be switched to another
directory, which should contain a 'stub' - some static HTML page, which says something like 'Application is under the
maintenance, please check again later'. Although, usage of such stub it is up to you, it is recommended, because actual
project update may take significant time before being complete.
Project web directory will be linked back instead of stub, only after all update actions are performed.

During update itself VCS remote changes are applied, `vendor` directory is updated via Composer, specified temporary
directories will be cleared and cache flushed.

> Note: in order for Composer be able to apply necessary changes, the 'composer.lock' file should be tracked by version
  control system!
