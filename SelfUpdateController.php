<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\selfupdate;

use yii\base\Action;
use yii\base\Exception;
use yii\console\Controller;
use Yii;
use yii\di\Instance;
use yii\mutex\Mutex;

/**
 * SelfUpdateController performs project update from VCS.
 * This command assumes project is store under Mercurial (.hg) version control system.
 *
 * Note: in order to work properly this command requires execution of VCS command without any prompt
 * or user input.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class SelfUpdateController extends Controller
{
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
     * @var array list of log entries.
     * @see log()
     */
    private $logLines = [];
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
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->mutex = Instance::ensure($this->mutex, Mutex::className());
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        $mutexName = $this->composeMutexName($action);
        if (!$this->mutex->acquire($mutexName)) {
            $this->stderr("Execution terminated: command is already running.\n");
            return false;
        }
        return parent::beforeAction($action);
    }

    /**
     * @inheritdoc
     */
    public function afterAction($action, $result)
    {
        $mutexName = $this->composeMutexName($action);
        $this->mutex->release($mutexName);
        return parent::afterAction($action, $result);
    }

    /**
     * Composes the mutex name.
     * @param Action $action command action.
     * @return string mutex name.
     */
    protected function composeMutexName($action)
    {
        return $this->className() . '::' . $action->getUniqueId();
    }

    /**
     * @return string project VCS root path
     */
    public function getProjectRootPath()
    {
        return Yii::getAlias('@app');
    }

    /**
     * @return string server hostname.
     */
    public function getHostName()
    {
        @$hostName = exec('hostname');
        if (empty($hostName)) {
            $hostName = Yii::$app->name . '.com';
        }
        return $hostName;
    }

    /**
     * @return string current date string.
     */
    public function getCurrentDate()
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Performs project update from VCS.
     */
    public function actionIndex()
    {
        try {
            $projectRootPath = $this->getProjectRootPath();
            $output = $this->execShellCommand('(cd ' . escapeshellarg($projectRootPath) . '; hg pull)');

            if (strpos($output, 'hg update') !== false) {
                $this->execShellCommand('(cd ' . escapeshellarg($projectRootPath) . '; hg update --clean)');
                $this->execShellCommand('php ' . escapeshellarg($projectRootPath . '/protected/yiic') . ' migrate up --interactive=0');
                Yii::$app->getCache()->flush();
                $this->log('Cache flushed.');
                $this->reportSuccess();
            }
        } catch (\Exception $exception) {
            $this->log($exception->getMessage());
            $this->reportFail();
        }
    }

    /**
     * Logs the message
     * @param string $message log message.
     */
    protected function log($message)
    {
        $this->logLines[] = $message;
        echo $message . "\n\n";
    }

    /**
     * Flushes log lines, returning them.
     * @return array log lines.
     */
    protected function flushLog()
    {
        $logLines = $this->logLines;
        $this->logLines = array();
        return $logLines;
    }

    /**
     * Executes shell command.
     * @param string $command command text.
     * @return string command output.
     * @throws Exception on failure.
     */
    protected function execShellCommand($command)
    {
        $outputLines = array();
        $this->log($command);
        exec($command . ' 2>&1', $outputLines, $responseCode);
        $output = implode("\n", $outputLines);
        if ($responseCode != 0) {
            throw new Exception("Execution of '{$command}' failed: response code = '{$responseCode}': \nOutput: \n{$output}");
        }
        foreach ($this->shellResponseErrorKeywords as $errorKeyword) {
            if (stripos($output, $errorKeyword) !== false) {
                throw new Exception("Execution of '{$command}' failed! \nOutput: \n{$output}");
            }
        }
        $this->log($output);
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
            @$userName = exec('whoami');
            if (empty($userName)) {
                $userName = $this->getName();
            }
            @$hostName = exec('hostname');
            if (empty($hostName)) {
                $hostName = preg_replace('/[^a-z0-1_]/s', '_', strtolower(Yii::$app->name)) . '.com';
            }
            $from = $userName . '@' . $hostName;
            $subject = $subjectPrefix . ': ' . $this->getHostName() . ' at ' . $this->getCurrentDate();
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
     */
    protected function sendEmail($from, $email, $subject, $message)
    {
        $headers = array(
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
        );
        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $matches = array();
        preg_match_all('/([^<]*)<([^>]*)>/iu', $from, $matches);
        if (isset($matches[1][0],$matches[2][0])) {
            $name = $this->utf8 ? '=?UTF-8?B?' . base64_encode(trim($matches[1][0])) . '?=' : trim($matches[1][0]);
            $from = trim($matches[2][0]);
            $headers[] = "From: {$name} <{$from}>";
        } else {
            $headers[] = "From: {$from}";
        }
        $headers[] = "Reply-To: {$from}";

        mail($email, $subject, $message, implode("\n", $headers));
    }
}