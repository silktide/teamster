<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Silktide\Teamster\Test\Command;

use Silktide\Teamster\Command\ThreadCommand;
use Silktide\Teamster\Pool\Runner\RunnerFactory;
use Silktide\Teamster\Pool\Runner\RunnerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputDefinition;

/**
 *
 */
class ThreadCommandTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Mockery\Mock|RunnerFactory
     */
    protected $runnerFactory;

    /**
     * @var \Mockery\Mock|RunnerInterface
     */
    protected $runner;

    /**
     * @var \Mockery\Mock|InputDefinition $input
     */
    protected $inputDefinition;

    /**
     * @var \Mockery\Mock|InputInterface $input
     */
    protected $input;

    /**
     * @var \Mockery\Mock|OutputInterface $input
     */
    protected $output;

    protected $argList = [
        "command" => "runner:command",
        "type" => "console",
        "maxRunCount" => 3
    ];

    public function setup()
    {
        $this->runner = \Mockery::mock("Silktide\\Teamster\\Pool\\Runner\\RunnerInterface")->shouldIgnoreMissing();

        $this->runnerFactory = \Mockery::mock("Silktide\\Teamster\\Pool\\Runner\\RunnerFactory");
        $this->runnerFactory->shouldReceive("createRunner")->andReturn($this->runner);

        $this->inputDefinition = \Mockery::mock("Symfony\\Component\\Console\\Input\\InputDefinition");

        $this->input = \Mockery::mock("Symfony\\Component\\Console\\Input\\InputInterface");
        $this->output = \Mockery::mock("Symfony\\Component\\Console\\Output\\OutputInterface");
    }

    public function testSignature()
    {
        $this->inputDefinition->shouldReceive("addArgument")->times(3);

        $commandName = "thread:command";

        $command = new ThreadCommand($commandName, $this->runnerFactory);
        $command->setDefinition($this->inputDefinition);
        $command->configure();
        $this->assertEquals($commandName, $command->getName());
    }

    public function testExecuteCommand()
    {
        foreach ($this->argList as $arg => $value) {
            $this->input->shouldReceive("getArgument")->withArgs([$arg])->once()->andReturn($value);
        }
        $this->runnerFactory->shouldReceive("createRunner")->withArgs([$this->argList["type"], "", $this->argList["maxRunCount"]])->once();
        $this->runner->shouldReceive("execute")->withArgs([$this->argList["command"]])->once();

        $command = new ThreadCommand("thread:command", $this->runnerFactory);
        $command->execute($this->input, $this->output);
    }

}
 