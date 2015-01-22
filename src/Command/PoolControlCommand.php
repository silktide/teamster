<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Silktide\Teamster\Command;

use Silktide\Teamster\Exception\NotFoundException;
use Silktide\Teamster\Exception\PidException;
use Silktide\Teamster\Exception\ProcessException;
use Silktide\Teamster\Pool\Pid\Pid;
use Silktide\Teamster\Pool\Pid\PidFactory;
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

    /**
     * @var RunnerFactory
     */
    protected $runnerFactory;

    /**
     * @var PidFactory
     */
    protected $pidFactory;

    /**
     * @var string
     */
    protected $poolPidFile;

    /**
     * @var string
     */
    protected $poolCommand;

    /**
     * @var Pid
     */
    private $pid;

    public function __construct(RunnerFactory $runnerFactory, PidFactory $pidFactory, $poolPidFile, $poolCommand)
    {
        $this->runnerFactory = $runnerFactory;
        $this->pidFactory = $pidFactory;
        $this->poolPidFile = $poolPidFile;
        $this->poolCommand = $poolCommand;
        parent::__construct();
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
                    $maxCount = 100;
                    $count = 0;

                    // send the terminate signal
                    $result = posix_kill($pid, SIGUSR1);
                    if ($result === false) {
                        throw new ProcessException("Could not send the terminate command to the pool, $pid");
                    }

                    do {
                        usleep(PoolCommand::DEFAULT_POOL_REFRESH_INTERVAL / 10);
                        ++$count;
                    } while ($this->isPoolRunning() && $count < $maxCount);

                    // check if we exited an infinite loop
                    if ($count >= $maxCount) {
                        throw new ProcessException("Could not stop the pool");
                    }
                    $output->writeln("<info>Pool stopped</info>");
                } else {
                    $output->writeln("<info>The pool was not running</info>");
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
                $runner = $this->runnerFactory->createRunner("console", $this->poolPidFile, 1);
                $runner->execute($this->poolCommand, false);
                $output->writeln("<info>Pool started</info>");
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
        } catch (PidException $e) {
            return false;
        } catch (NotFoundException $e) {
            return false;
        }

        // attempt to get the process exit status, if it hasn't exited this will return zero
        $pcntl = pcntl_waitpid($pid, $status, WNOHANG);
        $posix = false;
        // if we couldn't get the process, try the posix way
        if ($pcntl == -1) {
            $posix = posix_kill($pid, 0);
        }
        return $pcntl == 0 || $posix;
    }

    /**
     * gets the PID number from the PID file and caches it
     *
     * @param bool $skipCache
     * @return int
     */
    protected function getPid($skipCache = false)
    {
        $skipCache = (bool) $skipCache;
        if (!$skipCache && !empty($this->pid) && $this->pid instanceof Pid) {
            return $this->pid->getPid();
        }
        $pid = $this->pidFactory->create($this->poolPidFile);
        $pidNum = $pid->getPid();
        $this->pid = $pid;
        return $pidNum;
    }

} 