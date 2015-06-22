<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\selfupdate;

/**
 * Shell is a helper for shell command execution.
 *
 * @see ShellResult
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class Shell
{
    /**
     * Executes shell command.
     * @param string $command command to be executed.
     * @param array $placeholders placeholders to be replaced using `escapeshellarg()` in format: placeholder => value.
     * @return ShellResult execution result.
     */
    public static function execute($command, array $placeholders = [])
    {
        if (!empty($placeholders)) {
            $command = strtr($command, array_map('escapeshellarg', $placeholders));
        }
        $result = new ShellResult();
        $result->command = $command;
        exec($command . ' 2>&1', $result->outputLines, $result->exitCode);
        return $result;
    }
}