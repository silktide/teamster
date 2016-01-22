<?php

namespace Silktide\Teamster\Pool\Runner;

use Silktide\Teamster\Pool\Pid\PidInterface;

/**
 * Common interface for runners
 */
interface RunnerInterface
{

    /**
     * @param string $command - the command to run
     * @param bool $block - prevent the script from continuing until the process has finished
     */
    public function execute($command, $block = true);

    /**
     * @return bool
     */
    public function isRunning();

    /**
     * @param PidInterface|null $pid
     */
    public function finish(PidInterface $pid = null);

} 