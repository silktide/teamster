<?php

namespace Silktide\Teamster\Command;

use Silktide\Teamster\Pool\Runner\RunnerFactory;
use Silktide\Teamster\Pool\Runner\RunnerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run a single command repeatedly, in a separate process
 */
class ThreadCommand extends Command
{

    /**
     * @var string
     */
    protected $commandName;

    /**
     * @var RunnerFactory
     */
    protected $runnerFactory;

    /**
     * @var RunnerInterface
     */
    protected $runner;

    /**
     * @param string $commandName
     * @param RunnerFactory $runnerFactory
     */
    public function __construct($commandName, RunnerFactory $runnerFactory)
    {
        $this->commandName = $commandName;
        $this->runnerFactory = $runnerFactory;
        parent::__construct();

        // setup SIGTERM handler
        pcntl_signal(SIGTERM, [$this, "handleSignal"]);
    }

    /**
     * {@inheritDoc}
     */
    public function configure()
    {
        $this->setName($this->commandName)
            ->setDescription("Run a command in a separate process")
            ->addArgument("threadCommand", InputArgument::REQUIRED, "command to run")
            ->addArgument("type", InputArgument::REQUIRED, "type of runner to create")
            ->addArgument("maxRunCount", InputArgument::REQUIRED, "number of times to run this command");
    }

    /**
     * {@inheritDoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $input->getArgument("threadCommand");
        $type = $input->getArgument("type");
        $maxRunCount = $input->getArgument("maxRunCount");

        $this->runner = $this->runnerFactory->createRunner($type, "", $maxRunCount);

        $this->runner->execute($command);
    }

    /**
     * gracefully shut down when we receive a signal (SIGTERM)
     *
     * @param $signo
     */
    public function handleSignal($signo)
    {
        exit();
    }

    public function __destruct()
    {
        if ($this->runner instanceof RunnerInterface) {
            $this->runner->finish();
        }
    }

} 