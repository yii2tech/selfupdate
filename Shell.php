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

    /**
     * Builds shell command options string from array.
     * Option, which does not use any value should be specified as an array value, option with value should
     * be specified as key-value pair, where key is an option name and value - option value.
     * Option name will be automatically prefixed with `--` in case it has not already.
     *
     * For example:
     *
     * ```php
     * [
     *     'verbose',
     *     'username' => 'root'
     * ]
     * ```
     *
     * @param array $options options specification.
     * @return string options string.
     * @since 1.0.2
     */
    public static function buildOptions(array $options)
    {
        $parts = [];
        foreach ($options as $key => $value) {
            if (is_int($key)) {
                $parts[] = self::normalizeOptionName($value);
            } else {
                $parts[] = self::normalizeOptionName($key) . '=' . escapeshellarg($value);
            }
        }
        return implode(' ', $parts);
    }

    /**
     * Normalizes shell command option name, adding leading `-` if necessary.
     * @param string $name raw option name.
     * @return string normalized option name.
     * @since 1.0.2
     */
    private static function normalizeOptionName($name)
    {
        if (strpos($name, '-') !== 0) {
            return '--' . $name;
        }
        return $name;
    }
}