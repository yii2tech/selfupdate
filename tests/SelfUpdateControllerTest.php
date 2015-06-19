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
     * @return SelfUpdateController
     */
    protected function createController($config = [])
    {
        return new SelfUpdateController('self-update', Yii::$app, $config);
    }

    // Tests :

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