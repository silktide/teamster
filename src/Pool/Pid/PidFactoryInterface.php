<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Silktide\Teamster\Pool\Pid;

/**
 *
 */
interface PidFactoryInterface 
{

    /**
     * @param string $pidFile
     * @param int|null $pid
     * @return PidInterface
     */
    public function create($pidFile, $pid = null);

    /**
     * @param string $command
     * @return string
     */
    public function generatePidFileName($command);

} 