<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\selfupdate;

/**
 * Mercurial
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class Mercurial extends VersionControlSystem
{
    /**
     * @var string path to the 'hg' bin command.
     */
    public $binPath = 'hg';

    /**
     * Checks, if there are some changes in remote repository.
     * @param string $projectRoot VCS project root directory path.
     * @param string $log if parameter passed it will be filled with related log string.
     * @return boolean whether there are changes in remote repository.
     */
    public function hasRemoteChanges($projectRoot, &$log = null)
    {
        $result = Shell::execute('{binPath} incoming {projectRoot}', [
            '{binPath}' => $this->binPath,
            '{projectRoot}' => $projectRoot,
        ]);
        $log = $result->getOutput();
        return $result->isOk();
    }

    /**
     * Applies changes from remote repository.
     * @param string $projectRoot VCS project root directory path.
     * @param string $log if parameter passed it will be filled with related log string.
     * @return boolean whether the changes have been applied successfully.
     */
    public function applyRemoteChanges($projectRoot, &$log = null)
    {
        $result = Shell::execute('(cd {projectRoot}; {binPath} pull -u)', [
            '{binPath}' => $this->binPath,
            '{projectRoot}' => $projectRoot,
        ]);
        $log = $result->getOutput();
        return $result->isOk();
    }
}