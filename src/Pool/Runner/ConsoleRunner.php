<?php

namespace Silktide\Teamster\Pool\Runner;

use Silktide\Teamster\Exception\RunnerException;
use Silktide\Teamster\Pool\Pid\PidFactoryInterface;

/**
 * Runs a console command in a separate process
 */
class ConsoleRunner extends ProcessRunner
{

    /**
     * @var string
     */
    protected $consolePath;

    /**
     * @param string $consolePath
     * @param PidFactoryInterface $pidFactory
     * @param array $descriptorSpec
     * @param string $pidFile
     * @param int $maxRunCount
     * @param int $processTimeout
     * @param int $waitTimeout
     */
    public function __construct(
        $consolePath,
        PidFactoryInterface $pidFactory,
        array $descriptorSpec,
        $pidFile = "",
        $maxRunCount = 0,
        $processTimeout = self::DEFAULT_PROCESS_TIMEOUT,
        $waitTimeout = self::DEFAULT_WAIT_TIMEOUT
    ) {
        $this->setConsolePath($consolePath);
        parent::__construct($pidFactory, $descriptorSpec, $pidFile, $maxRunCount, $processTimeout, $waitTimeout);
    }

    /**
     * @param $consolePath
     * @throws RunnerException
     */
    protected function setConsolePath($consolePath)
    {
        if (!file_exists($consolePath) || !is_readable($consolePath)) {
            throw new RunnerException("The console path '$consolePath' does not link to a valid PHP file");
        }
        $this->consolePath = $consolePath;
    }

    /**
     * @param string $command
     * @param bool $block
     */
    public function execute($command, $block = true)
    {
        $consoleCommand = "php {$this->consolePath} $command";
        parent::execute($consoleCommand, $block);
    }

} 