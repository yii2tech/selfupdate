<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\selfupdate;

use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\caching\Cache;
use yii\console\Controller;
use Yii;
use yii\di\Instance;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\mail\BaseMailer;
use yii\mutex\Mutex;

/**
 * SelfUpdateController performs project update from VCS.
 * You can configure available version control systems via [[versionControlSystems]].
 *
 * Note: in order to work properly this command requires execution of VCS command without any prompt
 * or user input.
 *
 * Usage:
 *
 * 1. Create a configuration file using the `config` action:
 *
 *    yii self-update/config @app/config/selfupdate.php
 *
 * 2. Edit the created config file, adjusting it for your project needs.
 * 3. Run the 'perform' action, using created config:
 *
 *    yii self-update @app/config/selfupdate.php
 *
 * @property string $hostName name of the host, which will be used in reports.
 * @property string $reportFrom email address, which should be used to send report email messages.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class SelfUpdateController extends Controller
{
    /**
     * @var string controller default action ID.
     */
    public $defaultAction = 'perform';
    /**
     * @var array list of email addresses, which should be used to send execution reports.
     */
    public $emails = [];
    /**
     * @var Mutex|array|string the mutex object or the application component ID of the mutex.
     * After the controller object is created, if you want to change this property, you should only assign it
     * with a mutex connection object.
     */
    public $mutex = 'mutex';
    /**
     * @var string path to project root directory, which means VCS root directory. Path aliases can be use here.
     */
    public $projectRootPath = '@app';
    /**
     * @var array project web path stubs configuration.
     * Each path configuration should have following keys:
     *  - 'path' - string - path to web root folder
     *  - 'link' - string - path for the symbolic link, which should point to the web root
     *  - 'stub' - string - path to folder, which contains stub for the web
     * Yii aliases can be used for all these keys.
     * For example:
     *
     * ```php
     * [
     *     [
     *         'path' => '@app/web',
     *         'link' => '@app/httpdocs',
     *         'stub' => '@app/webstub',
     *     ]
     * ]
     * ```
     */
    public $webPaths = [];
    /**
     * @var string|array list of cache application components, for which [[Cache::flush()]] method should be invoked.
     * Component ids, instances or array configurations can be used here.
     */
    public $cache;
    /**
     * @var array list of temporary directories, which should be cleared after project update.
     * Path aliases can be used here. For example:
     *
     * ```php
     * [
     *     '@app/web/assets',
     *     '@runtime/URI',
     *     '@runtime/HTML',
     *     '@runtime/debug',
     * ]
     * ```
     */
    public $tmpDirectories = [];
    /**
     * @var array list of commands, which should be executed before project update begins.
     * If command is a string it will be executed as shell command, otherwise as PHP callback.
     * For example:
     *
     * ```php
     * [
     *     'mysqldump -h localhost -u root myproject > /path/to/backup/myproject.sql'
     * ],
     * ```
     */
    public $beforeUpdateCommands = [];
    /**
     * @var array list of shell commands, which should be executed after project update.
     * If command is a string it will be executed as shell command, otherwise as PHP callback.
     * For example:
     *
     * ```php
     * [
     *     'php /path/to/project/yii migrate/up --interactive=0'
     * ],
     * ```
     */
    public $afterUpdateCommands = [];
    /**
     * @var array list of keywords, which presence in the shell command output is considered as
     * its execution error.
     */
    public $shellResponseErrorKeywords = [
        'error',
        'exception',
        'ошибка',
    ];
    /**
     * @var array list of possible version control systems (VCS) in format: vcsFolderName => classConfig.
     * VCS will be detected automatically based on which folder is available inside [[projectRootPath]]
     */
    public $versionControlSystems = [
        '.git' => [
            'class' => 'yii2tech\selfupdate\Git'
        ],
        '.hg' => [
            'class' => 'yii2tech\selfupdate\Mercurial'
        ],
    ];
    /**
     * @var array composer command options.
     * @see Shell::buildOptions() for valid syntax on specifying this value.
     * For example:
     *
     * ```php
     * [
     *     'prefer-dist',
     *     'no-dev',
     * ]
     * ```
     *
     * Note, that `no-interaction` option will be added automatically to the options list.
     *
     * @since 1.0.2
     */
    public $composerOptions = [];
    /**
     * @var string path to the 'composer' bin command.
     * By default simple 'composer' is used, assuming it available as global shell command.
     * Path alias can be used here. For example: '@app/composer.phar'.
     */
    public $composerBinPath = 'composer';
    /**
     * @var array list of composer install root paths (the ones containing 'composer.json').
     * Path aliases can be used here.
     */
    public $composerRootPaths = [
        '@app'
    ];
    /**
     * @var \yii\mail\MailerInterface|array|string the mailer object or the application component ID of the mailer.
     * It will be used to send notification messages to [[emails]].
     * If not set or sending email via this component fails, the fallback to the plain PHP `mail()` function will be used instead.
     */
    public $mailer;
    /**
     * @var string configuration file name. Settings from this file will be merged with the default ones.
     * Such configuration file can be created, using action 'config'.
     * Path alias can be used here, for example: '@app/config/self-update.php'.
     */
    public $configFile;

    /**
     * @var array list of log entries.
     * @see log()
     */
    private $logLines = [];
    /**
     * @var string name of the host, which will be used in reports
     */
    private $_hostName;
    /**
     * @var string email address, which should be used to send report email messages.
     */
    private $_reportFrom;


    /**
     * Performs project update from VCS.
     * @param string|null $configFile the path or alias of the configuration file.
     * You may use the "config" command to generate
     * this file and then customize it for your needs.
     * @throws Exception on failure
     * @return int CLI exit code
     */
    public function actionPerform($configFile = null)
    {
        if (empty($configFile)) {
            $configFile = $this->configFile;
        }
        if (!empty($configFile)) {
            $configFile = Yii::getAlias($configFile);
            if (!is_file($configFile)) {
                throw new Exception("The configuration file does not exist: $configFile");
            }
            $this->log("Reading configuration from: $configFile");
            Yii::configure($this, require $configFile);
        }

        if (!$this->acquireMutex()) {
            $this->stderr("Execution terminated: command is already running.\n", Console::FG_RED);
            return self::EXIT_CODE_ERROR;
        }

        try {
            $this->normalizeWebPaths();

            $projectRootPath = Yii::getAlias($this->projectRootPath);

            $versionControlSystem = $this->detectVersionControlSystem($projectRootPath);

            $changesDetected = $versionControlSystem->hasRemoteChanges($projectRootPath, $log);
            $this->log($log);

            if ($changesDetected) {
                $this->linkWebStubs();

                $this->executeCommands($this->beforeUpdateCommands);

                $versionControlSystem->applyRemoteChanges($projectRootPath, $log);
                $this->log($log);

                $this->updateVendor();
                $this->flushCache();
                $this->clearTmpDirectories();

                $this->executeCommands($this->afterUpdateCommands);

                $this->linkWebPaths();

                $this->reportSuccess();
            } else {
                $this->log('No changes detected. Project is already up-to-date.');
            }

        } catch (\Exception $exception) {
            $this->log($exception->getMessage());
            $this->reportFail();

            $this->releaseMutex();
            return self::EXIT_CODE_ERROR;
        }

        $this->releaseMutex();
        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Creates a configuration file for the "perform" command.
     *
     * The generated configuration file contains detailed instructions on
     * how to customize it to fit for your needs. After customization,
     * you may use this configuration file with the "perform" command.
     *
     * @param string $fileName output file name or alias.
     * @return int CLI exit code
     */
    public function actionConfig($fileName)
    {
        $fileName = Yii::getAlias($fileName);
        if (file_exists($fileName)) {
            if (!$this->confirm("File '{$fileName}' already exists. Do you wish to overwrite it?")) {
                return self::EXIT_CODE_NORMAL;
            }
        }
        copy(Yii::getAlias('@yii2tech/selfupdate/views/selfUpdateConfig.php'), $fileName);
        $this->stdout("Configuration file template created at '{$fileName}' . \n\n", Console::FG_GREEN);
        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Acquires current action lock.
     * @return bool lock acquiring result.
     */
    protected function acquireMutex()
    {
        $this->mutex = Instance::ensure($this->mutex, Mutex::className());
        return $this->mutex->acquire($this->composeMutexName());
    }

    /**
     * Release current action lock.
     * @return bool lock release result.
     */
    protected function releaseMutex()
    {
        return $this->mutex->release($this->composeMutexName());
    }

    /**
     * Composes the mutex name.
     * @return string mutex name.
     */
    protected function composeMutexName()
    {
        return $this->className() . '::' . $this->action->getUniqueId();
    }

    /**
     * Links web roots to the stub directories.
     * @see webPaths
     */
    protected function linkWebStubs()
    {
        foreach ($this->webPaths as $webPath) {
            if (is_link($webPath['link'])) {
                unlink($webPath['link']);
            }
            symlink($webPath['stub'], $webPath['link']);
        }
    }

    /**
     * Links web roots to the actual web directories.
     * @see webPaths
     */
    protected function linkWebPaths()
    {
        foreach ($this->webPaths as $webPath) {
            if (is_link($webPath['link'])) {
                unlink($webPath['link']);
            }
            symlink($webPath['path'], $webPath['link']);
        }
    }

    /**
     * Normalizes [[webPaths]] value.
     * @throws InvalidConfigException on invalid configuration.
     */
    protected function normalizeWebPaths()
    {
        $rawWebPaths = $this->webPaths;
        $webPaths = [];
        foreach ($rawWebPaths as $rawWebPath) {
            if (!isset($rawWebPath['path'], $rawWebPath['link'], $rawWebPath['stub'])) {
                throw new InvalidConfigException("Web path configuration should contain keys: 'path', 'link', 'stub'");
            }
            $webPath = [
                'path' => Yii::getAlias($rawWebPath['path']),
                'link' => Yii::getAlias($rawWebPath['link']),
                'stub' => Yii::getAlias($rawWebPath['stub']),
            ];
            if (!is_dir($webPath['path'])) {
                throw new InvalidConfigException("'{$webPath['path']}' ('{$rawWebPath['path']}') is not a directory.");
            }
            if (!is_dir($webPath['stub'])) {
                throw new InvalidConfigException("'{$webPath['stub']}' ('{$rawWebPath['stub']}') is not a directory.");
            }
            if (!is_link($webPath['link'])) {
                throw new InvalidConfigException("'{$webPath['link']}' ('{$rawWebPath['link']}') is not a symbolic link.");
            }
            if (!in_array(readlink($webPath['link']), [$webPath['path'], $webPath['stub']])) {
                throw new InvalidConfigException("'{$webPath['link']}' ('{$rawWebPath['link']}') does not pointing to actual web or stub directory.");
            }
            $webPaths[] = $webPath;
        }
        $this->webPaths = $webPaths;
    }

    /**
     * Flushes cache for all components specified at [[cache]].
     */
    protected function flushCache()
    {
        if (!empty($this->cache)) {
            foreach ((array)$this->cache as $cache) {
                $cache = Instance::ensure($cache, Cache::className());
                $cache->flush();
            }
            $this->log('Cache flushed.');
        }
    }

    /**
     * Clears all directories specified via [[tmpDirectories]].
     */
    protected function clearTmpDirectories()
    {
        foreach ($this->tmpDirectories as $path) {
            $realPath = Yii::getAlias($path);
            $this->clearDirectory($realPath);
            $this->log("Directory '{$realPath}' cleared.");
        }
    }

    /**
     * Clears specified directory.
     * @param string $dir directory to be cleared.
     */
    protected function clearDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        if (!($handle = opendir($dir))) {
            return;
        }
        $specialFileNames = [
            '.htaccess',
            '.gitignore',
            '.gitkeep',
            '.hgignore',
            '.hgtkeep',
        ];
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (in_array($file, $specialFileNames)) {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                FileHelper::removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        closedir($handle);
    }

    /**
     * Performs vendors update via Composer at all [[composerRootPaths]].
     */
    protected function updateVendor()
    {
        $options = Shell::buildOptions(array_merge($this->composerOptions, ['no-interaction']));
        foreach ($this->composerRootPaths as $path) {
            $this->execShellCommand('(cd {composerRoot}; {composer} install ' . $options . ')', [
                '{composerRoot}' => Yii::getAlias($path),
                '{composer}' => Yii::getAlias($this->composerBinPath),
            ]);
        }
    }

    /**
     * Detects version control system used for the project.
     * @param string $path project root path.
     * @return VersionControlSystemInterface version control system instance.
     * @throws InvalidConfigException on failure.
     */
    protected function detectVersionControlSystem($path)
    {
        foreach ($this->versionControlSystems as $folderName => $config) {
            if (file_exists($path . DIRECTORY_SEPARATOR . $folderName)) {
                return Yii::createObject($config);
            }
        }
        throw new InvalidConfigException("Unable to detect version control system: neither of '" . implode(', ', array_keys($this->versionControlSystems)) . "' is present under '{$path}'.");
    }

    /**
     * @param string $hostName server hostname.
     */
    public function setHostName($hostName)
    {
        $this->_hostName = $hostName;
    }

    /**
     * @return string server hostname.
     */
    public function getHostName()
    {
        if ($this->_hostName === null) {
            $hostName = @exec('hostname');
            if (empty($hostName)) {
                $this->_hostName = Inflector::slug(Yii::$app->name) . '.com';
            } else {
                $this->_hostName = $hostName;
            }
        }
        return $this->_hostName;
    }

    /**
     * @return string email address, which should be used to send report email messages.
     */
    public function getReportFrom()
    {
        if ($this->_reportFrom === null) {
            $userName = @exec('whoami');
            if (empty($userName)) {
                $userName = Inflector::slug(Yii::$app->name);
            }
            $hostName = $this->getHostName();
            $this->_reportFrom = $userName . '@' . $hostName;
        }
        return $this->_reportFrom;
    }

    /**
     * @param string $reportFrom email address, which should be used to send report email messages.
     */
    public function setReportFrom($reportFrom)
    {
        $this->_reportFrom = $reportFrom;
    }

    /**
     * @return string current date string.
     */
    public function getCurrentDate()
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Logs the message
     * @param string $message log message.
     */
    protected function log($message)
    {
        $this->logLines[] = $message;
        $this->stdout($message . "\n\n");
    }

    /**
     * Flushes log lines, returning them.
     * @return array log lines.
     */
    protected function flushLog()
    {
        $logLines = $this->logLines;
        $this->logLines = [];
        return $logLines;
    }

    /**
     * Executes list of given commands.
     * @param array $commands commands to be executed.
     * @throws InvalidConfigException on invalid commands specification.
     */
    protected function executeCommands(array $commands)
    {
        foreach ($commands as $command) {
            if (is_string($command)) {
                $this->execShellCommand($command);
            } elseif (is_callable($command)) {
                $this->log(call_user_func($command));
            } else {
                throw new InvalidConfigException('Command should be a string or a valid PHP callback');
            }
        }
    }

    /**
     * Executes shell command.
     * @param string $command command text.
     * @return string command output.
     * @param array $placeholders placeholders to be replaced using `escapeshellarg()` in format: placeholder => value.
     * @throws Exception on failure.
     */
    protected function execShellCommand($command, array $placeholders = [])
    {
        $result = Shell::execute($command, $placeholders);
        $this->log($result->toString());

        $output = $result->getOutput();
        if (!$result->isOk()) {
            throw new Exception("Execution of '{$result->command}' failed: exit code = '{$result->exitCode}': \nOutput: \n{$output}");
        }
        foreach ($this->shellResponseErrorKeywords as $errorKeyword) {
            if (stripos($output, $errorKeyword) !== false) {
                throw new Exception("Execution of '{$result->command}' failed! \nOutput: \n{$output}");
            }
        }
        return $output;
    }

    /**
     * Sends report about success.
     */
    protected function reportSuccess()
    {
        $this->reportResult('Update success');
    }

    /**
     * Sends report about failure.
     */
    protected function reportFail()
    {
        $this->reportResult('UPDATE FAILED');
    }

    /**
     * Sends execution report.
     * Report message content will be composed from log messages.
     * @param string $subjectPrefix report message subject.
     */
    protected function reportResult($subjectPrefix)
    {
        $emails = $this->emails;
        if (!empty($emails)) {
            $hostName = $this->getHostName();
            $from = $this->getReportFrom();
            $subject = $subjectPrefix . ': ' . $hostName . ' at ' . $this->getCurrentDate();
            $message = implode("\n", $this->flushLog());
            foreach ($emails as $email) {
                $this->sendEmail($from, $email, $subject, $message);
            }
        }
    }

    /**
     * Sends an email.
     * @param string $from sender email address
     * @param string $email single email address
     * @param string $subject email subject
     * @param string $message email content
     * @return bool success.
     */
    protected function sendEmail($from, $email, $subject, $message)
    {
        if ($this->mailer === null) {
            return $this->sendEmailFallback($from, $email, $subject, $message);
        }

        try {
            /* @var $mailer \yii\mail\MailerInterface|BaseMailer */
            $mailer = Instance::ensure($this->mailer, 'yii\mail\MailerInterface');
            if ($mailer instanceof BaseMailer) {
                $mailer->useFileTransport = false; // ensure mailer is not in test mode
            }
            return $mailer->compose()
                ->setFrom($from)
                ->setTo($email)
                ->setSubject($subject)
                ->setTextBody($message)
                ->send();
        } catch (\Exception $exception) {
            $this->log($exception->getMessage());
            return $this->sendEmailFallback($from, $email, $subject, $message);
        }
    }

    /**
     * Sends an email via plain PHP `mail()` function.
     * @param string $from sender email address
     * @param string $email single email address
     * @param string $subject email subject
     * @param string $message email content
     * @return bool success.
     */
    protected function sendEmailFallback($from, $email, $subject, $message)
    {
        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
        ];
        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $matches = [];
        preg_match_all('/([^<]*)<([^>]*)>/iu', $from, $matches);
        if (isset($matches[1][0],$matches[2][0])) {
            $name = '=?UTF-8?B?' . base64_encode(trim($matches[1][0])) . '?=';
            $from = trim($matches[2][0]);
            $headers[] = "From: {$name} <{$from}>";
        } else {
            $headers[] = "From: {$from}";
        }
        $headers[] = "Reply-To: {$from}";

        return mail($email, $subject, $message, implode("\n", $headers));
    }
}