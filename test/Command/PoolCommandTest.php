<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Silktide\Teamster\Test\Command;

use Silktide\Teamster\Command\PoolCommand;
use Silktide\Teamster\Pool\Runner\RunnerFactory;
use Silktide\Teamster\Pool\Runner\RunnerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputDefinition;

/**
 *
 */
class PoolCommandTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var \Mockery\Mock|RunnerFactory
     */
    protected $runnerFactory;

    /**
     * @var \Mockery\Mock|RunnerInterface
     */
    protected $runner;

    /**
     * @var \Mockery\Mock|RunnerInterface
     */
    protected $completedRunner;

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

    public function setup()
    {
        $this->runner = \Mockery::mock("Silktide\\Teamster\\Pool\\Runner\\RunnerInterface")->shouldIgnoreMissing();
        $this->runner->shouldReceive("isRunning")->andReturn(true);

        $this->completedRunner = \Mockery::mock("Silktide\\Teamster\\Pool\\Runner\\RunnerInterface")->shouldIgnoreMissing();
        $this->completedRunner->shouldReceive("isRunning")->andReturn(false);

        $this->runnerFactory = \Mockery::mock("Silktide\\Teamster\\Pool\\Runner\\RunnerFactory");
        $this->runnerFactory->shouldReceive("createRunner")->withArgs(["console", "/^(?!completed-).*/", \Mockery::type("int")])->andReturn($this->runner);
        $this->runnerFactory->shouldReceive("createRunner")->withArgs(["console", "/^completed-/", \Mockery::type("int")])->andReturn($this->completedRunner);

        $this->inputDefinition = \Mockery::mock("Symfony\\Component\\Console\\Input\\InputDefinition");

        $this->input = \Mockery::mock("Symfony\\Component\\Console\\Input\\InputInterface");
        $this->output = \Mockery::mock("Symfony\\Component\\Console\\Output\\OutputInterface");
    }

    public function testConstruct()
    {
        $poolCommand = "pool:command";

        $this->inputDefinition->shouldReceive("addOption")->once();

        $command = new PoolCommand($poolCommand, "thread:command", $this->runnerFactory, []);
        $command->setDefinition($this->inputDefinition);
        $command->configure();
        $this->assertEquals($poolCommand, $command->getName());
    }

    /**
     * @dataProvider executeProvider
     *
     * @param $testRuns
     * @param $serviceConfig
     * @param $expectedPool
     */
    public function testExecuteCommand($testRuns, $serviceConfig, $expectedPool)
    {
        // insert runners into the expected pool (can't do this in the provider as it is called before setup())
        foreach ($expectedPool as $command => $list) {
            foreach ($list as $i => $runnerName) {
                switch ($runnerName) {
                    case "@runner":
                        $runner = $this->runner;
                        break;
                    case "@completed":
                        $runner = $this->completedRunner;
                        break;
                    default;
                        $runner = null;
                }
                $expectedPool[$command][$i] = $runner;
            }
        }

        // set up # test runs
        $this->input->shouldReceive("hasOption")->andReturn(true);
        $this->input->shouldReceive("getOption")->andReturn($testRuns);

        // run the command
        $command = new PoolCommand("pool:command", "thread:command", $this->runnerFactory, $serviceConfig, 50000);
        $command->execute($this->input, $this->output);

        // check the state of the pool
        $this->assertAttributeEquals($expectedPool, "pool", $command);

    }

    public function executeProvider()
    {
        return [
            [ // simple test
                1,
                [
                    "command" => [
                        "instances" => 1,
                        "type" => "console",
                        "command" => "command"
                    ]
                ],
                [
                    "command" => [
                        "@runner"
                    ]
                ]
            ],
            [ // test multiple instances and instance limitation
                4,
                [
                    "command" => [
                        "instances" => 2,
                        "type" => "console",
                        "command" => "command"
                    ]
                ],
                [
                    "command" => [
                        "@runner",
                        "@runner"
                    ]
                ]
            ],
            [ // test multiple commands
                1,
                [
                    "command1" => [
                        "instances" => 1,
                        "type" => "console",
                        "command" => "command"
                    ],
                    "command2" => [
                        "instances" => 1,
                        "type" => "console",
                        "command" => "command"
                    ]
                ],
                [
                    "command1" => [
                        "@runner"
                    ],
                    "command2" => [
                        "@runner"
                    ]
                ]
            ],
            [ // test removing completed instances
                3,
                [
                    "completed" => [
                        "instances" => 3,
                        "type" => "console",
                        "command" => "command"
                    ]
                ],
                [
                    "completed" => [
                        "@completed"
                    ]
                ]
            ],

        ];
    }

}
 