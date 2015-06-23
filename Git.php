<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\selfupdate;

use yii\base\Exception;

/**
 * Git represents GIT version control system.
 *
 * @see https://git-scm.com/
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class Git extends VersionControlSystem
{
    /**
     * @var string path to the 'git' bin command.
     */
    public $binPath = 'git';
    /**
     * @var string name of the GIT remote, which should be used to get changes.
     */
    public $remoteName = 'origin';


    /**
     * Returns currently active GIT branch name for the project.
     * @param string $projectRoot VCS project root directory path.
     * @return string branch name.
     * @throws Exception on failure.
     */
    public function getCurrentBranch($projectRoot)
    {
        $result = Shell::execute('(cd {projectRoot}; {binPath} branch)', [
            '{binPath}' => $this->binPath,
            '{projectRoot}' => $projectRoot,
        ]);
        foreach ($result->outputLines as $line) {
            if (($pos = stripos($line, '* ')) === 0) {
                return trim(substr($line, $pos + 2));
            }
        }
        throw new Exception('Unable to detect current GIT branch: ' . $result->toString());
    }

    /**
     * Checks, if there are some changes in remote repository.
     * @param string $projectRoot VCS project root directory path.
     * @param string $log if parameter passed it will be filled with related log string.
     * @return boolean whether there are changes in remote repository.
     */
    public function hasRemoteChanges($projectRoot, &$log = null)
    {
        $branchName = $this->getCurrentBranch($projectRoot);
        $result = Shell::execute("(cd {projectRoot}; {binPath} diff --summary {$this->remoteName}/{$branchName})", [
            '{binPath}' => $this->binPath,
            '{projectRoot}' => $projectRoot,
        ]);
        $log = $result->toString();
        return ($result->isOk() && !$result->isOutputEmpty());
    }

    /**
     * Applies changes from remote repository.
     * @param string $projectRoot VCS project root directory path.
     * @param string $log if parameter passed it will be filled with related log string.
     * @return boolean whether the changes have been applied successfully.
     */
    public function applyRemoteChanges($projectRoot, &$log = null)
    {
        $branchName = $this->getCurrentBranch($projectRoot);
        $result = Shell::execute("(cd {projectRoot}; {binPath} pull {$this->remoteName}/{$branchName})", [
            '{binPath}' => $this->binPath,
            '{projectRoot}' => $projectRoot,
        ]);
        $log = $result->toString();
        return $result->isOk();
    }
}