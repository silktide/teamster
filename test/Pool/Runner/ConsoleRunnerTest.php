<?php

namespace Silktide\Teamster\Test\Pool\Runner;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamWrapper;
use Silktide\Teamster\Exception\RunnerException;
use Silktide\Teamster\Pool\Runner\ConsoleRunner;

/**
 *
 */
class ConsoleRunnerTest extends \PHPUnit_Framework_TestCase
{

    protected $testDir = "test";

    protected $pidFactory;

    /**
     * @var \Mockery\MockInterface
     */
    protected $pid;

    protected $defaultDescriptorSpec;

    protected $consolePath;

    public function setup()
    {
        $this->pidFactory = \Mockery::mock("Silktide\\Teamster\\Pool\\Pid\\PidFactoryInterface");
        $this->pid = \Mockery::mock("Silktide\\Teamster\\Pool\\Pid\\PidInterface")->shouldIgnoreMissing(true);
        $this->pidFactory->shouldReceive("create")->andReturn($this->pid);
        $this->pid->shouldReceive("getPid")->withArgs([true])->andThrow("Silktide\\Teamster\\Exception\\PidException");

        $this->defaultDescriptorSpec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];

        $this->consolePath = __DIR__ . "/output/console.php";

        vfsStreamWrapper::setRoot(new vfsStreamDirectory($this->testDir));
    }

    protected function getPath($path)
    {
        return vfsStream::url($this->testDir . "/" . $path);
    }

    public function testConstruct()
    {
        // test non existent file
        $nonExistentFilePath = "non/existent/file.php";
        try {
            new ConsoleRunner($nonExistentFilePath, $this->pidFactory, []);
            $this->fail("Should not be able to create a console runner without a console file that exists");
        } catch (RunnerException $e) {
            $this->assertContains($nonExistentFilePath, $e->getMessage());
        }


        // test non executable file
        $nonExecutableFilePath = "nonExecutableFile.php";
        $nonExecutableFile = vfsStream::newFile($nonExecutableFilePath, 0000);
        vfsStreamWrapper::getRoot()->addChild($nonExecutableFile);

        $nonExecutableFilePath = $this->getPath($nonExecutableFilePath);

        try {
            new ConsoleRunner($nonExecutableFilePath, $this->pidFactory, []);
            $this->fail("Should not be able to create a console runner without a console file that is executable");
        } catch (RunnerException $e) {
            $this->assertContains($nonExecutableFilePath, $e->getMessage());
        }

        // test valid file
        $consolePath = "console.php";
        $nonExecutableFile = vfsStream::newFile($consolePath, 0444);
        vfsStreamWrapper::getRoot()->addChild($nonExecutableFile);

        $consolePath = $this->getPath($consolePath);

        $runner = new ConsoleRunner($consolePath, $this->pidFactory, []);

        $this->assertAttributeEquals($consolePath, "consolePath", $runner);
    }

    public function testConsoleExecution()
    {
        // setup file to save stdOut to
        $outFile = __DIR__ . "/output/consoleOutput";
        @unlink($outFile);
        $spec = $this->defaultDescriptorSpec;
        $spec[1] = ["file", $outFile, "w"];

        // create the runner
        $runner = new ConsoleRunner($this->consolePath, $this->pidFactory, $spec, "dud", 1, 5, 5);

        // execute the command and check the output file contents
        $expected = "console command";
        $runner->execute("'$expected'");

        $this->assertEquals($expected, file_get_contents($outFile));

    }

}
 