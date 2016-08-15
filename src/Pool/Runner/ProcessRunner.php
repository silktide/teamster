<?php

namespace Silktide\Teamster\Pool\Runner;

use Silktide\Teamster\Exception\PidException;
use Silktide\Teamster\Exception\RunnerException;
use Silktide\Teamster\Pool\Pid\PidFactoryInterface;
use Silktide\Teamster\Pool\Pid\PidInterface;

/**
 * Class to set up and run a shell command
 */
class ProcessRunner implements RunnerInterface
{

    const STDIN = 0;
    const STDOUT = 1;
    const STDERR = 2;

    const PROCESS_CHECK_INTERVAL = 250000; // micro seconds
    const DEFAULT_WAIT_TIMEOUT = 20; // time to wait for an existing process to finish, in seconds
    const DEFAULT_PROCESS_TIMEOUT = 20; // time to wait for our new process to finish, in seconds
    const TERMINATE_TIMEOUT = 60; // time to wait for a process to obey sigterm before we sigkill it

    /**
     * @var PidFactoryInterface
     */
    protected $pidFactory;

    /**
     * @var string
     */
    protected $pidFile;

    /**
     * @var array
     */
    protected $descriptorSpec;

    /**
     * @var array
     */
    protected $pipes = [];

    /**
     * @var int
     */
    protected $maxRunCount;

    /**
     * @var resource
     */
    protected $process;

    /**
     * @var int
     */
    protected $waitTimeout;

    /**
     * @var int
     */
    protected $processTimeout;

    /**
     * @param PidFactoryInterface $pidFactory
     * @param array $descriptorSpec
     * @param string $pidFile
     * @param int $maxRunCount
     * @param int $processTimeout
     * @param int $waitTimeout
     */
    public function __construct(
        PidFactoryInterface $pidFactory,
        array $descriptorSpec,
        $pidFile = "",
        $maxRunCount = 0,
        $processTimeout = self::DEFAULT_PROCESS_TIMEOUT,
        $waitTimeout = self::DEFAULT_WAIT_TIMEOUT
    ) {
        $this->pidFactory = $pidFactory;
        $this->pidFile = $pidFile;
        $this->setDescriptorSpec($descriptorSpec);
        $this->maxRunCount = (int) $maxRunCount;
        $this->processTimeout = $processTimeout;
        $this->waitTimeout = $waitTimeout;
    }

    /**
     * @param array $spec
     */
    protected function setDescriptorSpec(array $spec)
    {
        $this->descriptorSpec = [];
        foreach ($spec as $index => $resource) {
            if ($index != (string) (int) $index) {
                // not numeric
                continue;
            }
            $index = (int) $index;
            // check if we have a numeric array with exactly 2 elements
            if (is_array($resource) && isset($resource[0], $resource[1]) && count($resource) <= 3) {
                if (
                    !in_array($resource[0], array("pipe", "file")) ||
                    (
                        $resource[0] == "pipe" &&
                        !in_array($resource[1], array("r", "w"))
                    ) ||
                    (
                        $resource[0] == "file" &&
                        (
                            !is_string($resource[1]) ||
                            !isset($resource[2]) ||
                            !in_array($resource[2], ["r", "r+", "w", "w+", "a", "a+", "x", "x+", "c", "c+"])
                        )
                    )
                ) {
                    // malformed resource. Ignore it
                    continue;
                }
                $this->descriptorSpec[$index] = $resource;
            } elseif (is_resource($resource)) {
                $this->descriptorSpec[$index] = $resource;
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function execute($command, $block = true)
    {

        $block = (bool) $block;

        $runCount = 0;

        while($this->maxRunCount == 0 || $runCount < $this->maxRunCount) {

            $pid = null;
            //////// check if the process is already running ////////
            if (!empty($this->pidFile)) {
                // if we have a PID file, check if it is in use
                $pid = $this->pidFactory->create($this->pidFile);
                $startTime = microtime(true);
                // if another runner is running this process, loop until it has finished
                do {
                    try {
                        $pid->getPid(true);
                        // if this didn't throw an exception then the pid file exists, e.g. process still running
                    } catch (PidException $e) {
                        // process not running, exit the loop
                        break;
                    }
                    usleep(self::PROCESS_CHECK_INTERVAL);
                } while (microtime(true) - $startTime < $this->waitTimeout);

            }

            //////// run the process ////////
            $this->resetPipes();

            if (strpos($command, "exec") === false) {
                // make sure we're not running bash first as it messes with the PID numbers accessible to PHP
                $command = "exec $command";
            }

            $this->process = proc_open($command, $this->descriptorSpec, $this->pipes);


            // if we have a PID file path, get the status for this process and create a PID file from it
            if (!empty($this->pidFile)) {
                $status = proc_get_status($this->process);
                $pid = $this->pidFactory->create($this->pidFile, $status["pid"]);
            }

            // do processing on the pipes that have been created
            $this->processPipes();

            // if we're not waiting for the process to finish (e.g. $block = false), exit the loop
            if (!$block) {
                break;
            }

            //////// loop until the process has finished ////////
            $startTime = microtime(true);
            do {
                // pipes that are "full" can block the process from terminating
                $this->unblockPipes();

                usleep(self::PROCESS_CHECK_INTERVAL);
                pcntl_signal_dispatch();
                $status = proc_get_status($this->process);
            } while (!empty($status["running"]) && microtime(true) - $startTime < $this->processTimeout);

            $this->finish($pid);

            if ($this->maxRunCount > 0) {
                // no need to count runs if we're running the command continuously
                ++$runCount;
            }

        }

    }

    /**
     * @param PidInterface|string $pid
     * @return bool
     */
    public function isRunning($pid = "")
    {
        if (is_resource($this->process)) {

            // check if the process is still running
            $status = proc_get_status($this->process);
            if (                                        // if
                !$status["running"] &&                  // the process has finished
                (                                       // and
                    $pid instanceof PidInterface ||     // we have a PidInterface
                    (                                   // or
                        is_string($pid) &&              // we have a string
                        !empty($pid)                    // that isn't empty
                    )
                )
            ) {
                if (!$pid instanceof PidInterface) {
                    $pid = $this->pidFactory->create($pid);
                }
                // clean PID file if we didn't exit cleanly
                $pid->cleanPid();
            }
            return $status["running"];
        }
        return false;
    }

    /**
     * {@inheritDoc}
     * @throws RunnerException
     */
    public function finish(PidInterface $pid = null)
    {
        if (!is_resource($this->process)) {
            // no process to close, make sure the PID file is removed
            if (!empty($pid)) {
                $pid->cleanPid();
            }
            return;
        }

        // check if the process is still running
        $status = proc_get_status($this->process);
        if ($status["running"]) {
            // terminate the process kindly
            proc_terminate($this->process, SIGTERM);
            $startTime = microtime(true);
            $terminating = false;
            do {
                if (!$terminating && ((microtime(true) - $startTime) > self::TERMINATE_TIMEOUT)) {
                    // terminate the process with extreme prejudice
                    proc_terminate($this->process, SIGKILL);
                    $terminating = true;
                }
                // ... and wait for confirmation
                usleep(self::PROCESS_CHECK_INTERVAL);
                $status = proc_get_status($this->process);
            } while ($status["running"]);
        }

        // close the pipes
        $this->closePipes();

        if (proc_close($this->process) === false) {
            throw new RunnerException("Could not close process");
        }
        $this->process = null;

        if (!empty($pid) && !$pid->cleanPid()) {
            // error deleting the PID file, DO NOT CONTINUE
            throw new RunnerException("Could not release the PID file");
        }
    }


    /**
     * clears all pipes ready for a new run
     */
    protected function resetPipes()
    {
        $this->closePipes();
        $this->pipes = [];
    }

    /**
     * prevent any further communication with the process by closing stdIn and prevent other pipes from blocking
     */
    protected function processPipes()
    {
        foreach ($this->pipes as $index => $pipe) {
            if (is_resource($pipe)) {
                if ($index == self::STDIN) {
                    // prevent any further input from being passed
                    fclose($pipe);
                } else {
                    stream_set_blocking($pipe, 0); // don't block on reading from an empty stream
                }
            }
        }
    }

    /**
     * make sure pipe buffers are not full
     */
    protected function unblockPipes()
    {
        foreach ($this->pipes as $pipe) {
            // prevent the process from being blocked from terminating by a pipe that is "full"
            @stream_get_contents($pipe);
            // Do we need to capture the stream data for logging?
        }
    }

    /**
     * close all open pipes
     */
    protected function closePipes()
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
    }

} 