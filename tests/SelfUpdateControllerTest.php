<?php

namespace yii2tech\tests\unit\selfupdate;

use Yii;
use yii\helpers\FileHelper;
use yii2tech\selfupdate\SelfUpdateController;

class SelfUpdateControllerTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $testFilePath = $this->getTestFilePath();
        FileHelper::createDirectory($testFilePath);
    }

    protected function tearDown()
    {
        $testFilePath = $this->getTestFilePath();
        FileHelper::removeDirectory($testFilePath);

        parent::tearDown();
    }

    /**
     * Returns the test file path.
     * @return string file path.
     */
    protected function getTestFilePath()
    {
        $filePath = Yii::getAlias('@yii2tech/tests/unit/selfupdate/runtime') . DIRECTORY_SEPARATOR . getmypid();
        return $filePath;
    }

    /**
     * @param array $config controller configuration.
     * @return SelfUpdateControllerMock
     */
    protected function createController($config = [])
    {
        $controller = new SelfUpdateControllerMock('self-update', Yii::$app, $config);
        $controller->interactive = false;
        return $controller;
    }

    /**
     * Emulates running of the self-update controller action.
     * @param  string $actionID id of action to be run.
     * @param  array  $args action arguments.
     * @return string command output.
     */
    protected function runMessageControllerAction($actionID, array $args = [])
    {
        $controller = $this->createController();
        $controller->run($actionID, $args);
        return $controller->flushStdOutBuffer();
    }

    // Tests :

    public function testActionConfig()
    {
        $testPath = $this->getTestFilePath();
        $configFileName = $testPath . DIRECTORY_SEPARATOR . 'testActionConfig.php';

        $this->runMessageControllerAction('config', [$configFileName]);

        $this->assertFileExists($configFileName);
        $config = require $configFileName;
        $this->assertTrue(is_array($config));
    }

    public function testWebStubs()
    {
        $testPath = $this->getTestFilePath();
        $linkPath = $testPath . DIRECTORY_SEPARATOR . 'httpdocs';
        $webPath = $testPath . DIRECTORY_SEPARATOR . 'web';
        $stubPath = $testPath . DIRECTORY_SEPARATOR . 'webstub';
        FileHelper::createDirectory($webPath);
        FileHelper::createDirectory($stubPath);
        symlink($webPath, $linkPath);

        $controller = $this->createController([
            'webPaths' => [
                [
                    'link' => $linkPath,
                    'path' => $webPath,
                    'stub' => $stubPath,
                ]
            ],
        ]);
        $this->invoke($controller, 'linkWebStubs');

        $this->assertTrue(is_link($linkPath));
        $this->assertEquals($stubPath, readlink($linkPath));

        $this->invoke($controller, 'linkWebPaths');

        $this->assertTrue(is_link($linkPath));
        $this->assertEquals($webPath, readlink($linkPath));

        $this->invoke($controller, 'linkWebStubs');

        $this->assertTrue(is_link($linkPath));
        $this->assertEquals($stubPath, readlink($linkPath));
    }
}

class SelfUpdateControllerMock extends SelfUpdateController
{
    /**
     * @var string output buffer.
     */
    private $stdOutBuffer = '';

    public function stdout($string)
    {
        $this->stdOutBuffer .= $string;
    }

    public function flushStdOutBuffer()
    {
        $result = $this->stdOutBuffer;
        $this->stdOutBuffer = '';
        return $result;
    }
}