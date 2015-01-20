<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Silktide\Teamster\Test\Pool\Pid;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamWrapper;
use org\bovigo\vfs\vfsStreamFile;
use Silktide\Teamster\Exception\PidException;
use Silktide\Teamster\Pool\Pid\Pid;

/**
 *
 */
class PidTest extends \PHPUnit_Framework_TestCase
{

    protected $testDir = "test";

    public function setup()
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory($this->testDir, 0777));
    }

    protected function getPath($file)
    {
        return vfsStream::url($this->testDir . "/" . $file);
    }

    public function testPidFromExistingFile()
    {
        $testFile = "test.pid";
        $expectedPid = 12345;

        // create virtual file
        $file = vfsStream::newFile($testFile, 0777);
        $file->write($expectedPid);
        vfsStreamWrapper::getRoot()->addChild($file);

        $pid = new Pid($this->getPath($testFile));
        $this->assertEquals($expectedPid, $pid->getPid());
    }

    public function testExceptionOnBadPid()
    {
        $testFile = "test.pid";

        $pid = new Pid($this->getPath($testFile));

        try {
            $pid->getPid();
            $this->fail("Should not be able to get a PID from an non-existent file");
        } catch (PidException $e) {
            $this->assertContains($testFile, $e->getMessage());
        }

        $badPid = "abcde";
        $file = vfsStream::newFile($testFile, 0777);
        $file->write($badPid);
        vfsStreamWrapper::getRoot()->addChild($file);

        $pid = new Pid($this->getPath($testFile));

        try {
            $pid->getPid();
            $this->fail("Should not be able to continue with an invalid PID");
        } catch (PidException $e) {
            $this->assertContains($testFile, $e->getMessage());
        }
    }

    public function testWritingPidToFile()
    {
        $testFile = "test.pid";
        $expectedPid = 12345;

        $pid = new Pid($this->getPath($testFile), $expectedPid);
        $this->assertEquals($expectedPid, $pid->getPid());

        $vfs = vfsStreamWrapper::getRoot();
        $this->assertTrue($vfs->hasChild($testFile));

        $this->assertEquals($expectedPid, $vfs->getChild($testFile)->getContent());
    }

    public function testRecheck()
    {
        $testFile = "test.pid";
        $initialPid = 12345;

        $pid = new Pid($this->getPath($testFile), $initialPid);
        $this->assertEquals($initialPid, $pid->getPid()); // check the pid set properly

        $finalPid = 67890;

        /** @var vfsStreamFile $file */
        $file = vfsStreamWrapper::getRoot()->getChild($testFile);
        $file->setContent("$finalPid");

        $this->assertEquals($initialPid, $pid->getPid()); // check it doesn't update automatically
        $this->assertEquals($finalPid, $pid->getPid(true)); // check a recheck pull the latest value
    }

    public function testClean()
    {
        $testFile = "test.pid";
        $pidNumber = 12345;

        $pid = new Pid($this->getPath($testFile), $pidNumber);

        $vfs = vfsStreamWrapper::getRoot();
        $this->assertTrue($vfs->hasChild($testFile));

        $pid->cleanPid();

        $this->assertFalse($vfs->hasChild($testFile));

    }

} 