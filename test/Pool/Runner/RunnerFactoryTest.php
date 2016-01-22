<?php

namespace Silktide\Teamster\Test\Pool\Runner;

use Silktide\Teamster\Exception\RunnerException;
use Silktide\Teamster\Pool\Runner\RunnerFactory;

/**
 *
 */
class RunnerFactoryTest extends \PHPUnit_Framework_TestCase
{

    protected $pidFactory;

    protected $consolePath;

    public function setup()
    {
        $this->pidFactory = \Mockery::mock("Silktide\\Teamster\\Pool\\Pid\\PidFactoryInterface")->shouldIgnoreMissing();
        $this->consolePath = __DIR__ . "/output/console.php";
    }

    public function testSetters()
    {
        $factory = new RunnerFactory($this->pidFactory, $this->consolePath);

        $spec = [0, 1, 2, 3];
        $processTimeout = 30;
        $waitTimeout = 10;

        $factory->setDescriptorSpec($spec);
        $this->assertAttributeEquals($spec, "descriptorSpec", $factory);

        $factory->setProcessTimeout($processTimeout);
        $this->assertAttributeEquals($processTimeout, "processTimeout", $factory);

        $factory->setWaitTimeout($waitTimeout);
        $this->assertAttributeEquals($waitTimeout, "waitTimeout", $factory);
    }

    public function testSetTimeoutExceptions()
    {
        $factory = new RunnerFactory($this->pidFactory, $this->consolePath);

        foreach (["setProcessTimeout", "setWaitTimeout"] as $setter) {
            // test non integer
            try {
                $factory->{$setter}("NaN");
                $this->fail("Should not be able to run $setter with a non integer value");
            } catch (RunnerException $e) {

            }

            // test non positive integer
            try {
                $factory->{$setter}(-10);
                $this->fail("Should not be able to run $setter with a non positive integer value");
            } catch (RunnerException $e) {

            }
        }
    }

    /**
     * @dataProvider modifySpecProvider
     *
     * @param array $originalSpec
     * @param mixed $index
     * @param mixed $definition
     * @param bool $catchExceptions
     * @param array $expectedSpec
     */
    public function testModifyingDescriptorSpec(array $originalSpec, $index, $definition, $catchExceptions, array $expectedSpec = [])
    {
        $factory = new RunnerFactory($this->pidFactory, $this->consolePath);

        $factory->setDescriptorSpec($originalSpec);
        if ($catchExceptions) {
            try {
                $factory->modifyDescriptorSpec($index, $definition);
                $this->fail("Should not be able to modify the descriptor spec with the index '$index' and the definition: '" . json_encode($definition). "'");
            } catch (RunnerException $e) {

            }
        } else {
            $factory->modifyDescriptorSpec($index, $definition);
            $this->assertAttributeEquals($expectedSpec, "descriptorSpec", $factory);
        }
    }

    public function modifySpecProvider()
    {
        return [
            [ // bad index
                [],
                "NaN",
                [0, 1, 2],
                true
            ],
            [ // bad definition
                [],
                0,
                "Not an array",
                true
            ],
            [ // adding (with numeric string index)
                [],
                "1",
                [1, 2, 3],
                false,
                [1 => [1, 2, 3]]
            ],
            [ // replacing
                [0 => [1, 2, 3]],
                0,
                [4, 5, 6],
                false,
                [0 => [4, 5, 6]]
            ],
            [ // removing
                [0 => [1, 2, 3]],
                0,
                [],
                false,
                []
            ]
        ];
    }

    public function testCreateProcessRunner()
    {
        $timeout = 10;
        $factory = new RunnerFactory($this->pidFactory, $this->consolePath);
        $factory->setProcessTimeout($timeout);

        $runner = $factory->createRunner("process");

        $this->assertInstanceOf("Silktide\\Teamster\\Pool\\Runner\\ProcessRunner", $runner);
        $this->assertAttributeEquals($timeout, "processTimeout", $runner);
    }

    public function testCreateConsoleRunner()
    {
        $timeout = 10;
        $factory = new RunnerFactory($this->pidFactory, $this->consolePath);
        $factory->setProcessTimeout($timeout);

        $runner = $factory->createRunner("console");

        $this->assertInstanceOf("Silktide\\Teamster\\Pool\\Runner\\ConsoleRunner", $runner);
        $this->assertAttributeEquals($timeout, "processTimeout", $runner);
        $this->assertAttributeEquals($this->consolePath, "consolePath", $runner);
    }

    public function testUndefinedRunnertype()
    {
        $factory = new RunnerFactory($this->pidFactory, $this->consolePath);

        $type = "unknown";
        try {
            $factory->createRunner($type);
        } catch (RunnerException $e) {
            $this->assertContains($type, $e->getMessage());
        }
    }

}
 