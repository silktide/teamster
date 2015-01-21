<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */

namespace Silktide\Teamster\Command;

use Silktide\Teamster\Pool\Runner\RunnerFactory;
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
     * @param string $commandName
     * @param RunnerFactory $runnerFactory
     */
    public function __construct($commandName, RunnerFactory $runnerFactory)
    {
        $this->commandName = $commandName;
        $this->runnerFactory = $runnerFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function configure()
    {
        $this->setName($this->commandName)
            ->setDescription("Run a command in a separate process")
            ->addArgument("command", InputArgument::REQUIRED, "command to run")
            ->addArgument("type", InputArgument::REQUIRED, "type of runner to create")
            ->addArgument("maxRunCount", InputArgument::REQUIRED, "number of times to run this command");
    }

    /**
     * {@inheritDoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $input->getArgument("command");
        $type = $input->getArgument("type");
        $maxRunCount = $input->getArgument("maxRunCount");

        $runner = $this->runnerFactory->createRunner($type, "", $maxRunCount);

        $runner->execute($command);
    }

} 