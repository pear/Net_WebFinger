<?php
require_once 'Net/WebFinger.php';
require_once 'Net/WebFingerTestBase.php';

require_once 'Cache.php';
require_once 'HTTP/Request2.php';
require_once 'HTTP/Request2/Adapter/Mock.php';

class Net_WebFingerCacheTest extends Net_WebFingerTestBase
{
    protected $cacheDir;

    protected function getHostMetaNoLrdd()
    {
        $xrd = new XML_XRD();
        $xrd->subject = 'example.org';
        return $xrd;
    }

    protected function getHostMetaNoLrddExpiry()
    {
        $xrd = new XML_XRD();
        $xrd->subject = 'example.org';
        $xrd->expires = time() + 2;
        return $xrd;
    }


    public function setUp()
    {
        //PEAR's Cache uses realpath() which makes it impossible to use
        // a stream wrapper like vfsStream for cache file testing
        $this->cacheDir = sys_get_temp_dir() . '/Net_WebFingerTest-' . uniqid();
        mkdir($this->cacheDir);
    }

    public function tearDown()
    {
        $iterator = new RecursiveDirectoryIterator($this->cacheDir);
        $itit = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($itit as $file) {
            if ($file->getFilename() == '.' || $file->getFilename() == '..') {
                continue;
            }
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->cacheDir);
    }

    public function testSetCache()
    {
        $this->markTestSkipped('fixme: mocking');
        $wf = $this->getMock('Net_WebFinger', array('loadXrd'));
        //fill cache
        $wf->expects($this->exactly(1))
            ->method('loadXrd')
            ->will($this->returnValue($this->getHostMetaNoLrdd()));

        $wf->setCache(
            new Cache('file', array('cache_dir' => $this->cacheDir))
        );
        $react = $wf->finger('user@example.org');
        $err = $react->error->getMessage();

        //use cache
        $wf->expects($this->never())->method('loadXrd');
        $react = $wf->finger('user@example.org');
        $this->assertTrue($react->describes('user@example.org'));
        $this->assertEquals($err, $react->error->getMessage());
    }

    public function testLoadHostMetaCachedExpiry()
    {
        $this->markTestSkipped('fixme: mocking');
        $wf = $this->getMock('Net_WebFinger', array('loadXrd'));
        //fill cache
        $wf->expects($this->exactly(2))
            ->method('loadXrd')
            ->will($this->returnValue($this->getHostMetaNoLrddExpiry()));

        $wf->setCache(
            new Cache('file', array('cache_dir' => $this->cacheDir))
        );
        $react = $wf->finger('user@example.org');
        $err = $react->error->getMessage();

        //use cache: cache expired
        sleep(3);
        $react = $wf->finger('user@example.org');
        $this->assertTrue($react->describes('user@example.org'));
        $this->assertEquals($err, $react->error->getMessage());
    }
}

?>