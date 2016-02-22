<?php

namespace Silktide\Teamster\Test\Pool\Runner;

use Silktide\Teamster\Pool\Runner\ProcessRunner;
use Silktide\Teamster\Pool\Pid\PidInterface;

/**
 *
 */
class ProcessRunnerTest extends \PHPUnit_Framework_TestCase
{

    protected $testDir = "test";

    protected $pidFactory;

    /**
     * @var \Mockery\MockInterface|PidInterface
     */
    protected $pid;

    protected $defaultDescriptorSpec;

    public function setup()
    {
        $this->pidFactory = \Mockery::mock("Silktide\\Teamster\\Pool\\Pid\\PidFactoryInterface");
        $this->pid = \Mockery::mock("Silktide\\Teamster\\Pool\\Pid\\PidInterface")->shouldIgnoreMissing(true);
        $this->pidFactory->shouldReceive("create")->andReturn($this->pid);

        $this->defaultDescriptorSpec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];

    }

    protected function setupOutFile($outFile)
    {
        @unlink($outFile);
        $spec = $this->defaultDescriptorSpec;
        $spec[1] = ["file", $outFile, "w"];
        return $spec;
    }

    /**
     * @dataProvider descriptorSpecProvider
     *
     * @param string $testName
     * @param array $descriptorSpec
     * @param array $expectedSpec
     */
    public function testConstruct($testName, $descriptorSpec, $expectedSpec)
    {
        $runner = new ProcessRunner($this->pidFactory, $descriptorSpec);
        $this->assertAttributeEquals($expectedSpec, "descriptorSpec", $runner, $testName . " has failed");
    }

    public function descriptorSpecProvider()
    {
        $resource = fopen(__FILE__, "r");
        return [
            [ // no spec
                "Blank spec",
                [], // descriptor spec
                []  // expected spec
            ],
            [ // well formed spec
                "Well formed spec",
                [
                    0 => ["pipe", "r"],
                    1 => ["file", __FILE__, "r"],
                    2 => $resource
                ],
                [
                    0 => ["pipe", "r"],
                    1 => ["file", __FILE__, "r"],
                    2 => $resource
                ]
            ],
            [ // bad spec keys
                "Bad spec keys",
                [
                    "0" => ["pipe", "r"],
                    "one" => ["pipe", "w"]
                ],
                [
                    0 => ["pipe", "r"]
                ]
            ],
            [ // bad spec definition
                "Bad spec definition",
                [
                    0 => ["dud", "r"], // bad type
                    1 => ["pipe", "x"], // bad mode
                    2 => ["file", []], // bad file path
                    3 => ["file", "path", "dud"], // bad file mode
                    4 => new \stdClass()
                ],
                []
            ]
        ];
    }

    public function testSuccessfulExecution()
    {
        // seems we need to use a real file and can't mock the filesystem
        $outFile = __DIR__ . "/output/output";
        $spec = $this->setupOutFile($outFile);

        $this->pid->shouldReceive("getPid")->withArgs([true])->andThrow("Silktide\\Teamster\\Exception\\PidException");
        $this->pid->shouldReceive("cleanPid")->once()->andReturn(true);

        $runner = new ProcessRunner($this->pidFactory, $spec, "dud", 1, 5, 5);
        $expected = "output";
        $runner->execute("php -r \"usleep(5000); echo '$expected';\"");

        $this->assertEquals($expected, file_get_contents($outFile));
    }

    /**
     * @dataProvider nonBlockingProvider
     *
     * @param $timeout
     * @param bool $wait
     * @param bool $finish
     * @param string $output
     * @param string $expectedOutput
     */
    public function testNonBlocking($timeout, $wait, $finish, $output, $expectedOutput)
    {
        $this->pid->shouldReceive("getPid")->withArgs([true])->andThrow("Silktide\\Teamster\\Exception\\PidException");
        $this->pid->shouldReceive("cleanPid")->once()->andReturn(true);

        // seems we need to use a real file and can't mock the filesystem
        $outFile = __DIR__ . "/output/output";
        $spec = $this->setupOutFile($outFile);

        // setup command
        $command = "php -r \"usleep($timeout); echo '$output';\"";

        // do the test
        $runner = new ProcessRunner($this->pidFactory, $spec, "dud", 1, 5, 5);
        $runner->execute($command, false);

        $this->assertTrue($runner->isRunning($this->pid));
        if ($wait) {
            usleep($timeout * 2);
        }
        if ($finish) {
            $runner->finish($this->pid);
        }
        $this->assertFalse($runner->isRunning($this->pid));
        $this->assertEquals($expectedOutput, file_get_contents($outFile));
    }

    public function nonBlockingProvider()
    {
        return [
            [ // naturally ending process
                200000,
                true,   // wait for it to end
                false,  // don't try to finish
                "output",
                "output"
            ],
            [ // forceably end process
                1000000,
                false,  // don't wait
                true,   // call finish
                "output",
                ""      // expect no output
            ],
            [ // clean up process
                200000,
                true,   // wait
                true,   // and call finish
                "output",
                "output"
            ]
        ];
    }

}
 