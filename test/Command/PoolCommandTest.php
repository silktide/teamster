<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Silktide\Teamster\Test\Command;

use Silktide\Teamster\Command\PoolCommand;
use Silktide\Teamster\Pool\Runner\RunnerFactory;
use Silktide\Teamster\Pool\Runner\RunnerInterface;
use Silktide\Teamster\Pool\Pid\PidFactoryInterface;
use Silktide\Teamster\Pool\Pid\PidInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputDefinition;

/**
 *
 */
class PoolCommandTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Mockery\Mock|PidFactoryInterface
     */
    protected $pidFactory;

    /**
     * @var \Mockery\Mock|PidInterface
     */
    protected $pid;

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

        $this->pid = \Mockery::mock("Silktide\\Teamster\\Pool\\Pid\\PidInterface")->shouldIgnoreMissing(true);
        $this->pidFactory = \Mockery::mock("Silktide\\Teamster\\Pool\\Pid\\PidFactoryInterface");
        $this->pidFactory->shouldReceive("create")->andReturn($this->pid);

        $this->inputDefinition = \Mockery::mock("Symfony\\Component\\Console\\Input\\InputDefinition");

        $this->input = \Mockery::mock("Symfony\\Component\\Console\\Input\\InputInterface");
        $this->output = \Mockery::mock("Symfony\\Component\\Console\\Output\\OutputInterface")->shouldIgnoreMissing();
    }

    public function testConstruct()
    {
        $poolCommand = "pool:command";
        $pidFile = "pool.pid";

        $this->inputDefinition->shouldReceive("addOption")->once();

        $command = new PoolCommand($poolCommand, "thread:command", $pidFile, $this->pidFactory, $this->runnerFactory, []);
        $command->setDefinition($this->inputDefinition);
        $command->configure();
        $this->assertEquals($poolCommand, $command->getName());
    }

    /**
     * @dataProvider serviceConfigProvider
     *
     * @param $serviceConfig
     * @param $expectedConfig
     */
    public function testServiceConfig($serviceConfig, $expectedConfig)
    {
        $command = new PoolCommand("pool:command", "thread:command", "pool.pid", $this->pidFactory, $this->runnerFactory, $serviceConfig);
        $this->assertAttributeEquals($expectedConfig, "serviceConfig", $command);
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
            foreach ($list as $i => $expectedRunner) {
                switch ($expectedRunner["runner"]) {
                    case "@runner":
                        $runner = $this->runner;
                        break;
                    case "@completed":
                        $runner = $this->completedRunner;
                        break;
                    default;
                        $runner = null;
                }
                $expectedPool[$command][$i]["runner"] = $runner;
            }
        }

        // set up # test runs
        $this->input->shouldReceive("hasOption")->andReturn(true);
        $this->input->shouldReceive("getOption")->andReturn($testRuns);

        // run the command
        $command = new PoolCommand("pool:command", "thread:command", "pool.pid", $this->pidFactory, $this->runnerFactory, $serviceConfig, 50000);
        $command->execute($this->input, $this->output);

        // check the state of the pool
        $this->assertAttributeEquals($expectedPool, "pool", $command);

    }

    public function testDestruct()
    {
        $instances = 3;

        $serviceConfig = [
            "command" => [
                "instances" => $instances,
                "command" => "command",
                "type" => "console"
            ]
        ];

        // set up # test runs
        $this->input->shouldReceive("hasOption")->andReturn(true);
        $this->input->shouldReceive("getOption")->andReturn($instances);

        // run the command
        $command = new PoolCommand("pool:command", "thread:command", "pool.pid", $this->pidFactory, $this->runnerFactory, $serviceConfig, 50000);
        $command->execute($this->input, $this->output);

        $this->runner->shouldReceive("finish")->times(3);
        $command->__destruct();
    }

    public function serviceConfigProvider()
    {
        return [
            [ // test valid command config (with element reordering)
                [
                    "command" => [ // minimal
                        "type" => "type",
                        "command" => "command",
                        "instances" => 1
                    ],
                    "command with max runs" => [ // inc maxRunCount
                        "command" => "command",
                        "instances" => 1,
                        "type" => "type",
                        "maxRunCount" => "10"
                    ]
                ],
                [
                    "command" => [
                        "command" => "command",
                        "instances" => 1,
                        "type" => "type"
                    ],
                    "command with max runs" => [
                        "command" => "command",
                        "instances" => 1,
                        "type" => "type",
                        "maxRunCount" => 10
                    ]
                ]
            ],
            [ // test invalid command configs
                [
                    "no command" => [
                        "instances" => 1,
                        "type" => "type"
                    ],
                    "no type" => [
                        "command" => "command",
                        "instances" => 1
                    ],
                    "no instances" => [
                        "command" => "command",
                        "type" => "type"
                    ],
                    "bad instances" => [
                        "command" => "command",
                        "instances" => "NaN",
                        "type" => "type"
                    ]
                ],
                []
            ],
            [
                [
                    "removing other elements" => [
                        "command" => "command",
                        "removing" => "removing",
                        "instances" => 1,
                        "other" => "other",
                        "type" => "type",
                        "elements" => "elements",
                        "maxRunCount" => 10,

                    ]
                ],
                [
                    "removing other elements" => [
                        "command" => "command",
                        "instances" => 1,
                        "type" => "type",
                        "maxRunCount" => 10
                    ]
                ]
            ]
        ];
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
                        [
                            "runner" => "@runner",
                            "pidFile" => "command-0.pid"
                        ]
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
                        [
                            "runner" => "@runner",
                            "pidFile" => "command-0.pid"
                        ],
                        [
                            "runner" => "@runner",
                            "pidFile" => "command-1.pid"
                        ]
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
                        [
                            "runner" => "@runner",
                            "pidFile" => "command1-0.pid"
                        ]
                    ],
                    "command2" => [
                        [
                            "runner" => "@runner",
                            "pidFile" => "command2-0.pid"
                        ]
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
                        [
                            "runner" => "@completed",
                            "pidFile" => "completed-0.pid"
                        ]
                    ]
                ]
            ],

        ];
    }

}
 