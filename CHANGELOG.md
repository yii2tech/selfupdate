Yii 2 Project Self Update extension Change Log
==============================================

2.0.0 under development
-----------------------

- Chg: Required Yii framework version has been raised to '2.1.0' (klimov-paul)


1.0.3, November 3, 2017
-----------------------

- Bug: Usage of deprecated `yii\base\Object` changed to `yii\base\BaseObject` allowing compatibility with PHP 7.2 (klimov-paul)
- Enh: Usage of deprecated exit code constants of `yii\console\Controller` changed to `yii\console\ExitCode` ones (klimov-paul)


1.0.2, December 8, 2016
-----------------------

- Enh #5: Added `SelfUpdateController::$composerOptions` allowing setup of additional options for `composer install` command (klimov-paul)


1.0.1, November 24, 2016
------------------------

- Enh #4: Added `SelfUpdateController::$reportFrom` allowing setup of the report sender email address (klimov-paul)


1.0.0, February 11, 2016
------------------------

- Initial release.
