<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\selfupdate;

use yii\base\ErrorHandler;
use yii\base\Object;

/**
 * ShellResult represents shell command execution result.
 *
 * @property string $output shell command output.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class ShellResult extends Object
{
    /**
     * @var string command being executed.
     */
    public $command;
    /**
     * @var integer shell command execution exit code
     */
    public $exitCode;
    /**
     * @var array shell command output lines.
     */
    public $outputLines = [];

    /**
     * @param string $glue lines glue.
     * @return string shell command output.
     */
    public function getOutput($glue = "\n")
    {
        return implode($glue, $this->outputLines);
    }

    /**
     * @return boolean whether exit code is OK.
     */
    public function isOk()
    {
        return $this->exitCode === 0;
    }

    /**
     * Checks if output contains given string
     * @param string $string needle string.
     * @return boolean whether output contains given string.
     */
    public function isOutputContains($string)
    {
        return stripos($this->getOutput(), $string) !== false;
    }

    /**
     * Checks if output matches give regular expression.
     * @param string $pattern regular expression
     * @return boolean whether output matches given regular expression.
     */
    public function isOutputMatches($pattern)
    {
        return preg_match($pattern, $this->getOutput()) > 0;
    }

    /**
     * @return string string representation of this object.
     */
    public function toString()
    {
        return $this->command . "\n" . $this->getOutput() . "\n" . 'Exit code: ' . $this->exitCode;
    }

    /**
     * PHP magic method that returns the string representation of this object.
     * @return string the string representation of this object.
     */
    public function __toString()
    {
        // __toString cannot throw exception
        // use trigger_error to bypass this limitation
        try {
            return $this->toString();
        } catch (\Exception $e) {
            ErrorHandler::convertExceptionToError($e);
            return '';
        }
    }
}