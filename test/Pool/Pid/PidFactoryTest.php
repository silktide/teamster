<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Silktide\Teamster\Test\Pool\Pid;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamWrapper;
use Silktide\Teamster\Exception\NotFoundException;
use Silktide\Teamster\Exception\PidException;
use Silktide\Teamster\Pool\Pid\PidFactory;

/**
 *
 */
class PidFactoryTest extends \PHPUnit_Framework_TestCase
{

    protected $testDir = "test";

    public function setup()
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory($this->testDir, 0777));
    }

    protected function getPath($path)
    {
        return vfsStream::url($this->testDir . "/" . $path);
    }

    public function testCreatingPidDir()
    {
        $pidDir = "pid";
        $pidDirPath = $this->getPath($pidDir);

        // test missing dir is created
        new PidFactory($pidDirPath);
        $this->assertFileExists($pidDirPath);
    }

    public function testSettingPidDir()
    {
        $pidDir = "pid";
        $realPidDir = "real_pid";
        $pidDirPath = $this->getPath($pidDir);

        $vfs = vfsStreamWrapper::getRoot();

        // test non writable directory
        $dir = new vfsStreamDirectory($pidDir, 0444);
        $vfs->addChild($dir);
        try {
            $factory = new PidFactory($pidDirPath);
            $this->fail("Should not be able to continue with a non-writable PID directory");
        } catch (NotFoundException $e) {
            $this->assertContains($pidDir, $e->getMessage());
        }

        // test normal directory
        $dir = new vfsStreamDirectory($realPidDir, 0777);
        $vfs->addChild($dir);

        $realPidDirPath = $this->getPath($realPidDir);
        $factory = new PidFactory($realPidDirPath);
        $this->assertAttributeEquals($realPidDirPath, "pidDir", $factory);
    }

    /**
     * Not technically a unit test as we have a dependency on the Pid class, but this is a factory after all
     */
    public function testCreatePid()
    {
        $pidDir = "pid";
        $pidFile = "test.pid";
        $pid = 12345;

        $dir = new vfsStreamDirectory($pidDir, 0777);
        vfsStreamWrapper::getRoot()->addChild($dir);

        $factory = new PidFactory($this->getPath($pidDir));
        $factory->create($pidFile, $pid);

        $this->assertTrue($dir->hasChild($pidFile));
        $this->assertEquals($pid, $dir->getChild($pidFile)->getContent());
    }

    public function testGeneratePidFileName()
    {
        $path = "must/not:contain@invalid;characters#in\$a path";
        $expected = "mustnotcontaininvalidcharactersinapath";
        $pidDir = "pid";

        $dir = new vfsStreamDirectory($pidDir, 0777);
        vfsStreamWrapper::getRoot()->addChild($dir);

        $factory = new PidFactory($this->getPath($pidDir));

        $this->assertContains($expected, $factory->generatePidFileName($path));
    }

}
 