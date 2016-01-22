<?php

namespace Silktide\Teamster\Test\Command;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamWrapper;
use Silktide\Teamster\Command\PoolControlCommand;
use Silktide\Teamster\Exception\ProcessException;
use Symfony\Component\Console\Input\InputDefinition;
use Silktide\Teamster\Pool\Runner\RunnerInterface;
use Silktide\Teamster\Pool\Runner\RunnerFactory;
use Silktide\Teamster\Pool\Pid\PidFactoryInterface;
use Silktide\Teamster\Pool\Pid\PidInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class PoolControlCommandTest extends \PHPUnit_Framework_TestCase
{

    protected $testDir = "test";

    /**
     * @var \Mockery\Mock|RunnerFactory
     */
    protected $runnerFactory;

    /**
     * @var \Mockery\Mock|RunnerInterface
     */
    protected $runner;

    /**
     * @var \Mockery\Mock|PidFactoryInterface
     */
    protected $pidFactory;

    /**
     * @var \Mockery\Mock|PidInterface
     */
    protected $pid;

    /**
     * @var \Mockery\Mock|InputDefinition
     */
    protected $inputDefinition;

    /**
     * @var \Mockery\Mock|InputInterface
     */
    protected $input;

    /**
     * @var \Mockery\Mock|OutputInterface
     */
    protected $output;

    public function setup()
    {
        $this->runner = \Mockery::mock("Silktide\\Teamster\\Pool\\Runner\\RunnerInterface");
        $this->runnerFactory = \Mockery::mock("Silktide\\Teamster\\Pool\\Runner\\RunnerFactory");
        $this->runnerFactory->shouldReceive("createRunner")->andReturn($this->runner);

        $this->pid = \Mockery::mock("Silktide\\Teamster\\Pool\\Pid\\PidInterface")->shouldIgnoreMissing(true);
        $this->pidFactory = \Mockery::mock("Silktide\\Teamster\\Pool\\Pid\\PidFactoryInterface");
        $this->pidFactory->shouldReceive("create")->andReturn($this->pid);

        $this->inputDefinition = \Mockery::mock("Symfony\\Component\\Console\\Input\\InputDefinition");

        $this->input = \Mockery::mock("Symfony\\Component\\Console\\Input\\InputInterface");
        $this->output = \Mockery::mock("Symfony\\Component\\Console\\Output\\OutputInterface")->shouldIgnoreMissing();

        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory($this->testDir));
    }

    protected function getPath($path)
    {
        return vfsStream::url($this->testDir . "/" . $path);
    }

    public function testConfigure()
    {
        $pidFile = "pid.pid";
        $poolCommand = "pool:command";
        $command = new PoolControlCommand($this->runnerFactory, $this->pidFactory, $pidFile, $poolCommand);
        $command->setDefinition($this->inputDefinition);
        $this->inputDefinition->shouldReceive("addArgument")->once();

        $command->configure();

    }

    public function testStart()
    {
        $this->input->shouldReceive("getArgument")->andReturn("start");

        $pid = getmypid();
        $this->pid->shouldReceive("getPid")->andReturn($pid);

        $poolCommand = "pool:command";

        // test when pool is already running
        $command = new PoolControlCommand($this->runnerFactory, $this->pidFactory, "pool.pid", $poolCommand);
        try {
            $command->execute($this->input, $this->output);
            $this->fail("Should not be able to start a pool when one is already running");
        } catch (ProcessException $e) {

        }

        // test when pool is stopped
        $noPidFile = "no.pid";

        $noPid = \Mockery::mock("Silktide\\Teamster\\Pool\\Pid\\PidInterface")->shouldIgnoreMissing(true);
        $noPid->shouldReceive("getPid")->atLeast()->times(1)->andThrow("Silktide\\Teamster\\Exception\\PidException");

        $noPidFactory = \Mockery::mock("Silktide\\Teamster\\Pool\\Pid\\PidFactoryInterface");
        $noPidFactory->shouldReceive("create")->withArgs([$noPidFile])->once()->andReturn($noPid);

        $this->runner->shouldReceive("execute")->withArgs([$poolCommand, false])->once();

        $command = new PoolControlCommand($this->runnerFactory, $noPidFactory, $noPidFile, $poolCommand);
        $command->execute($this->input, $this->output);
    }

    public function testStop()
    {
        $this->input->shouldReceive("getArgument")->andReturn("stop");

        // test when pool is running

        // setup process to kill
        $timeout = 50000;
        $pidFilePath = "test.pid";
        $startTime = $this->startChildProcess($timeout, $pidFilePath);

        // create command
        $command = new PoolControlCommand($this->runnerFactory, $this->pidFactory, $this->getPath($pidFilePath) , "pool:command");

        // run the stop command
        $command->execute($this->input, $this->output);

        // check the timing. duration should be less than the process timeout
        $duration = microtime(true) - $startTime;
        $timeout /= 1000000;
        $this->assertLessThan($timeout, $duration, "duration, $duration, was greater than timeout, $timeout");
    }

    public function testRestart()
    {
        $poolCommand = "pool:command";

        // running two tests, both should execute the command
        $this->runner->shouldReceive("execute")->withArgs([$poolCommand, false])->twice();

        $this->input->shouldReceive("getArgument")->andReturn("restart");
        // test when pool is running

        // setup process to kill
        $timeout = 50000;
        $pidFilePath = "test.pid";
        $startTime = $this->startChildProcess($timeout, $pidFilePath);

        // create command
        $command = new PoolControlCommand($this->runnerFactory, $this->pidFactory, $this->getPath($pidFilePath) , $poolCommand);

        // run the stop command
        $command->execute($this->input, $this->output);

        // check the timing. duration should be less than the process timeout
        $duration = microtime(true) - $startTime;
        $timeout /= 1000000;
        $this->assertLessThan($timeout, $duration, "duration, $duration, was greater than timeout, $timeout");


        // test when pool is stopped
        vfsStreamWrapper::getRoot()->removeChild($pidFilePath);
        $command->execute($this->input, $this->output);
    }

    protected function startChildProcess($timeout, $pidFilePath)
    {
        $pipes = [];
        $spec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];

        $pidFile = vfsStream::newFile($pidFilePath, 0777);
        vfsStreamWrapper::getRoot()->addChild($pidFile);

        $startTime = microtime(true);
        $process = proc_open("php -r \"echo \\\"starting\\\\n\\\"; usleep($timeout);echo \\\"done @ \\\" . time() . \\\"\\n\\\";\"", $spec, $pipes);

        // save the pid to the pidFile
        $status = proc_get_status($process);
        if (!$status["running"]) {
            $this->fail("The test process is not running!");
        }
        $pidFile->setContent("" . $status["pid"]);

        return $startTime;
    }

}
 