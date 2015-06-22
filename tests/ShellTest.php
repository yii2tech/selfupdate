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
}