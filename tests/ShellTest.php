<?php

namespace yii2tech\tests\unit\selfupdate;

use yii2tech\selfupdate\Shell;

class ShellTest extends TestCase
{
    public function testExecute()
    {
        $result = Shell::execute('ls --help');
        $this->assertSame(0, $result->exitCode);
        $this->assertNotEmpty($result->outputLines);

        $result = Shell::execute('ls {dir}', ['{dir}' => __DIR__]);
        $this->assertSame(0, $result->exitCode);
        $this->assertEquals("ls '" . __DIR__ . "'", $result->command);

        $result = Shell::execute('ls {dir}', ['{dir}' => __DIR__ . '/unexisting_dir']);
        $this->assertSame(2, $result->exitCode);
    }

    /**
     * Data provider for [[testBuildOptions()]]
     * @return array test data.
     */
    public function dataProviderBuildOptions()
    {
        return [
            [
                [
                    '--verbose',
                    '--no-interactive'
                ],
                '--verbose --no-interactive'
            ],
            [
                [
                    '--verbose',
                    '--username' => 'root'
                ],
                "--verbose --username='root'"
            ],
            [
                [
                    'verbose',
                    'no-interactive'
                ],
                '--verbose --no-interactive'
            ],
            [
                [
                    '-v',
                    'no-interactive'
                ],
                '-v --no-interactive'
            ],
            [
                [
                    'verbose',
                    'username' => 'root'
                ],
                "--verbose --username='root'"
            ],
        ];
    }

    /**
     * @dataProvider dataProviderBuildOptions
     *
     * @param array $options
     * @param $expectedResult
     */
    public function testBuildOptions(array $options, $expectedResult)
    {
        $optionsString = Shell::buildOptions($options);
        $this->assertEquals($expectedResult, $optionsString);
    }
}