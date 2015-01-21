<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Silktide\Teamster\Command;

use Silktide\Teamster\Pool\Runner\RunnerFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Silktide\Teamster\Pool\Runner\RunnerInterface;

/**
 *
 */
class PoolCommand extends Command
{

    const DEFAULT_POOL_REFRESH_INTERVAL = 500000;

    /**
     * @var string
     */
    protected $poolCommandName;

    /**
     * @var string
     */
    protected $threadCommandName;

    /**
     * @var RunnerFactory
     */
    protected $runnerFactory;

    /**
     * @var array
     */
    protected $serviceConfig;

    /**
     * @var array
     */
    protected $pool = [];

    /**
     * @var int
     */
    protected $poolRefreshInterval;

    /**
     * @param string $poolCommandName
     * @param string $threadCommandName
     * @param RunnerFactory $runnerFactory
     * @param array $serviceConfig
     * @param int $poolRefreshInterval
     */
    public function __construct(
        $poolCommandName,
        $threadCommandName,
        RunnerFactory $runnerFactory,
        array $serviceConfig,
        $poolRefreshInterval = self::DEFAULT_POOL_REFRESH_INTERVAL
    ) {
        $this->poolCommandName = $poolCommandName;
        $this->threadCommandName = $threadCommandName;
        $this->runnerFactory = $runnerFactory;
        $this->setServiceConfig($serviceConfig);
        $this->poolRefreshInterval = $poolRefreshInterval;
    }

    protected function setServiceConfig(array $serviceConfig)
    {
        $this->serviceConfig = [];
        foreach ($serviceConfig as $command => $config) {
            if (empty($config["type"]) || empty($config["command"]) || empty($config["instances"]) || (int) $config["instances"] <= 0) {
                // malformed config, ignore
                continue;
            }
            // create final config array for this command
            $finalConfig = [
                "command" => $config["command"],
                "instances" => (int) $config["instances"],
                "type" => $config["type"]
            ];
            // handle max run count
            if (isset($config["maxRunCount"])) {
                $finalConfig["maxRunCount"] = $config["maxRunCount"];
            }
            // add to the service config
            $this->serviceConfig[$command] = $finalConfig;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function configure()
    {
        $this->setName($this->poolCommandName)
            ->setDescription("Runs a suite of services in a thread pool")
            ->addOption("testRuns", "t", InputOption::VALUE_REQUIRED, "run the pool a specific number of times", 1);
    }

    /**
     * {@inheritDoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $maxRuns = -1; // -1 = run continuously
        if ($input->hasOption("testRuns")) {
            $maxRuns = $input->getOption("testRuns");
        }
        $runCount = 0;

        while($maxRuns == -1 || $runCount < $maxRuns) {
            ++$runCount;
            foreach ($this->serviceConfig as $service => $config) {
                // create a pool for this service if necessary
                if (!isset($this->pool[$service])) {
                    $this->pool[$service] = [];
                }

                foreach ($this->pool[$service] as $index => $instance) {
                    /** @var RunnerInterface $instance */
                    if (!$instance->isRunning()) {
                        unset($this->pool[$service][$index]);
                    }
                }
                // reindex pool so we don't end up with massive index values
                $this->pool[$service] = array_values($this->pool[$service]);

                $count = count($this->pool[$service]);
                if ($count >= $config["instances"]) {
                    // enough instances of this service for now
                    continue;
                }

                // create command as an array
                $pidFile = $service . "-" . $count;
                $type = $config["type"];
                $maxRunCount = empty($config["maxRunCount"])? 0: (int) $config["maxRunCount"];

                // running a command that runs a command
                $command = [
                    $this->threadCommandName,
                    $config["command"],
                    $type,
                    $maxRunCount
                ];

                // create and execute runner, then add to the pool
                $runner = $this->runnerFactory->createRunner("console", $pidFile, 1);
                $runner->execute(implode(" ", $command), false);
                $this->pool[$service][] = $runner;
            }
            usleep($this->poolRefreshInterval);
        }
    }

} 