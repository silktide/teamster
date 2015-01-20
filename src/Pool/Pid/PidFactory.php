<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Silktide\Teamster\Pool\Pid;
use Silktide\Teamster\Exception\NotFoundException;

/**
 * Factory class for Pid objects
 */
class PidFactory implements PidFactoryInterface
{

    protected $pidDir;

    protected $invalidFileNameChars = ['/', ':', '@', ';', '#', '$', ' '];

    /**
     * @param $pidDir
     */
    public function __construct($pidDir)
    {
        $this->setPidDir($pidDir);
    }

    /**
     * @param $pidDir
     * @throws NotFoundException
     */
    protected function setPidDir($pidDir)
    {
        if (!is_dir($pidDir) || !is_writable($pidDir)) {
            throw new NotFoundException("Cannot write to the PID directory, '$pidDir'");
        }
        $this->pidDir = $pidDir;
    }

    /**
     * @inheritDoc
     */
    public function create($pidFile, $pid = null)
    {

        if ($pidFile[0] != "/") {
            $pidFile = $this->pidDir . "/" . $pidFile;
        }
        return new Pid($pidFile, $pid);
    }

    /**
     * {@inheritDoc}
     */
    public function generatePidFileName($command)
    {
        return str_replace($this->invalidFileNameChars, "", $command);
    }

} 