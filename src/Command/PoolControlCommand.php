<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Silktide\Teamster\Command;

use Silktide\Teamster\Exception\NotFoundException;
use Silktide\Teamster\Exception\ProcessException;
use Silktide\Teamster\Pool\Runner\ProcessRunner;
use Silktide\Teamster\Pool\Runner\RunnerFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class PoolControlCommand extends Command
{

    protected $runnerFactory;

    protected $poolPidFile;

    protected $poolCommand;

    private $pid;

    public function __construct(RunnerFactory $runnerFactory, $poolPidFile, $poolCommand)
    {
        $this->runnerFactory = $runnerFactory;
        $this->poolPidFile = $poolPidFile;
        $this->poolCommand = $poolCommand;
    }

    public function configure()
    {
        $this->setName("silktide:teamster:control")
            ->setDescription("Control command for the teamster service")
            ->addArgument("action", InputArgument::REQUIRED, "service action");
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $action = $input->getArgument("action");

        switch ($action) {
            case "restart":
            case "stop":
                if ($this->isPoolRunning()) {
                    // KILL, KILL, KILL!!
                    $pid = $this->getPid();

                    // counter to prevent infinite loops
                    $maxCount = 200;
                    $count = 0;

                    // send the terminate signal
                    posix_kill($pid, SIGTERM);

                    do {
                        usleep(ProcessRunner::PROCESS_CHECK_INTERVAL);
                        ++$count;
                    } while ($this->isPoolRunning() && $count < $maxCount);

                    // check if we exited an infinite loop
                    if ($count >= $maxCount) {
                        throw new ProcessException("Could not stop the pool");
                    }
                }
                if ($action == "stop") {
                    break;
                }
                // if "restart" then fall through
                // no break
            case "start":
                // start the pool in a new process
                if ($this->isPoolRunning()) {
                    throw new ProcessException("Pool is already running");
                }
                $runner = $this->runnerFactory->createRunner("console", $this->poolPidFile);
                $runner->execute($this->poolCommand, false);
                break;

        }

    }

    /**
     * @return bool
     */
    protected function isPoolRunning()
    {
        try {
            $pid = $this->getPid();
        } catch (NotFoundException $e) {
            return false;
        }

        // attempt to get the process exit status, if it hasn't exited this will return zero
        return pcntl_waitpid($pid, $status, WNOHANG) == 0;
    }

    /**
     * @param bool $skipCache
     * @throws \Silktide\Teamster\Exception\ProcessException
     * @throws \Silktide\Teamster\Exception\NotFoundException
     * @return int
     */
    protected function getPid($skipCache = false)
    {
        $skipCache = (bool) $skipCache;
        if (!$skipCache && !empty($this->pid)) {
            return $this->pid;
        }
        // check if the pid file exists
        if (!file_exists($this->poolPidFile)) {
            throw new NotFoundException("The PID file doesn't exist");
        }

        // read pid file and check if the process is still running
        $rawPid = file_get_contents($this->poolPidFile);
        $pid = (int) $rawPid;
        if (empty($pid)) {
            throw new ProcessException("The Teamster pool PID file did not contain a valid PID: '$rawPid'");
        }
        $this->pid = $pid;
        return $pid;
    }

} 