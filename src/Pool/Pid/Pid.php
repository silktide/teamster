<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Silktide\Teamster\Pool\Pid;
use Silktide\Teamster\Exception\PidException;

/**
 * Pid
 *
 * manages the storage and retrieval of PIDs from PID files
 */
class Pid implements PidInterface
{

    /**
     * @var int
     */
    protected $pid;

    /**
     * @var string
     */
    protected $file;

    /**
     * @param string $file
     * @param int|null $pid
     */
    public function __construct($file, $pid = null)
    {
        $this->setFile($file, $pid);
    }

    /**
     * @param string $file
     * @param int|null $pid
     */
    protected function setFile($file, $pid = null)
    {
        if (!empty($pid)) {
            // create file and write pid to it
            file_put_contents($file, $pid);
            $this->pid = $pid;
        }
        $this->file = $file;
    }

    /**
     * {@inheritDoc}
     * @throws PidException
     */
    public function getPid($recheck = false)
    {
        $recheck = (bool) $recheck;
        if ($recheck || empty($this->pid)) {
            if (!file_exists($this->file)) {
                throw new PidException("The PID file '{$this->file}' no longer exists");
            }
            // read file and parse PID
            $rawPid = file_get_contents($this->file);
            $pid = (int) $rawPid;
            if (empty($pid) || $pid < 0) {
                throw new PidException("The PID file '{$this->file}' did not contain a valid PID: '$rawPid'");
            }
            $this->pid = $pid;
        }
        return $this->pid;
    }

    /**
     * @return bool
     */
    public function cleanPid()
    {
        return unlink($this->file);
    }

} 