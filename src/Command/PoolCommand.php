<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Silktide\Teamster\Command;

use Silktide\Teamster\Pool\Pid\PidFactoryInterface;
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
     * @var string
     */
    protected $poolPidFile;

    /**
     * @var PidFactoryInterface
     */
    protected $pidFactory;

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
     * @var OutputInterface
     */
    protected $output;

    /**
     * @param string $poolCommandName
     * @param string $threadCommandName
     * @param string $poolPidFile
     * @param PidFactoryInterface $pidFactory
     * @param RunnerFactory $runnerFactory
     * @param array $serviceConfig
     * @param int $poolRefreshInterval
     */
    public function __construct(
        $poolCommandName,
        $threadCommandName,
        $poolPidFile,
        PidFactoryInterface $pidFactory,
        RunnerFactory $runnerFactory,
        array $serviceConfig,
        $poolRefreshInterval = self::DEFAULT_POOL_REFRESH_INTERVAL
    ) {
        $this->poolCommandName = $poolCommandName;
        $this->threadCommandName = $threadCommandName;
        $this->poolPidFile = $poolPidFile;
        $this->pidFactory = $pidFactory;
        $this->runnerFactory = $runnerFactory;
        $this->setServiceConfig($serviceConfig);
        $this->poolRefreshInterval = $poolRefreshInterval * 10;
        parent::__construct();
        // register graceful shutdown handler
        pcntl_signal(SIGUSR1, [$this, "handleSignal"]);
    }

    /**
     * @param array $serviceConfig
     */
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
            ->addOption("testRuns", "t", InputOption::VALUE_REQUIRED, "run the pool a specific number of times");
    }

    /**
     * {@inheritDoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        // save the output for writing to later
        $this->output = $output;

        // setup run counting if required
        $maxRuns = -1; // -1 = run continuously
        if (!empty($input->getOption("testRuns"))) {
            $maxRuns = $input->getOption("testRuns");
        }
        $runCount = 0;

        while($maxRuns == -1 || $runCount < $maxRuns) {

            ++$runCount;
            try {
                foreach ($this->serviceConfig as $service => $config) {
                    // create a pool for this service if necessary
                    if (!isset($this->pool[$service])) {
                        $this->pool[$service] = [];
                    }

                    foreach ($this->pool[$service] as $index => $instance) {
                        /** @var RunnerInterface $runner */
                        $runner = $instance["runner"];
                        if (!$runner->isRunning()) {

                            $output->writeln("<comment>Removing dead instance for $service</comment>");

                            // clean the pid file
                            $pid = $this->pidFactory->create($instance["pidFile"]);
                            $pid->cleanPid();

                            unset($this->pool[$service][$index]);
                        }
                    }

                    // reindex pool so we don't end up with massive index values
                    $this->pool[$service] = array_values($this->pool[$service]);

                    $count = count($this->pool[$service]);
                    $output->writeln("<info>$count instances running for $service</info>");
                    if ($count >= $config["instances"]) {
                        // enough instances of this service for now
                        continue;
                    }

                    // create command as an array
                    $pidFile = $service . "-" . $count . ".pid";
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
                    $output->writeln("<comment>Creating new instance for $service, PID file: $pidFile</comment>");
                    $runner = $this->runnerFactory->createRunner("console", $pidFile, 1);
                    $runner->execute(implode(" ", $command), false);
                    $this->pool[$service][] = ["runner" => $runner, "pidFile" => $pidFile];
                }
            } catch (\Exception $e) {
                $output->writeln("<error>{$e->getMessage()}</error>");
            }

            $output->writeln("<info>Completed run #$runCount of $maxRuns</info>");
            // process any signals (to catch "stop" requests)
            pcntl_signal_dispatch();

            // sleep and loop
            usleep($this->poolRefreshInterval);
        }
    }

    /**
     * processes signals
     * used to terminate the command gracefully
     *
     * @param $signo
     */
    public function handleSignal($signo)
    {
        $this->output->writeln("<comment>Shutting down the pool (signal $signo)</comment>");
        exit();
    }

    /**
     * stop all the runners and remove the pool's PID file
     */
    public function __destruct()
    {
        foreach ($this->pool as $service => $pool) {
            foreach ($pool as $i => $instance) {
                /** @var RunnerInterface $runner */
                $runner = $instance["runner"];
                $pid = $this->pidFactory->create($instance["pidFile"]);
                $this->output->writeln("<comment>Finishing runner $i of $service</comment>");
                $runner->finish($pid);
            }
        }
        if (count($this->pool) > 0) {
            $pid = $this->pidFactory->create($this->poolPidFile);
            $pid->cleanPid();
        }
    }
}