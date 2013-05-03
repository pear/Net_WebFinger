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

        //ignore PEAR::isError() strict warnings
        error_reporting(error_reporting() & ~E_STRICT);
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
        $wf = new Net_WebFinger();
        $this->addHttpMock($wf);
        $this->addHttpResponse(
            $this->getWebfinger(),
            'https://example.org/.well-known/webfinger?resource=acct%3Auser%40example.org'
        );

        $wf->setCache(
            new Cache('file', array('cache_dir' => $this->cacheDir))
        );

        //fill cache
        $react = $wf->finger('user@example.org');
        $this->assertUrlList(
            'https://example.org/.well-known/webfinger?resource=acct%3Auser%40example.org'
        );
        $this->assertDescribes('acct:user@example.org', $react);

        //use cache
        $react = $wf->finger('user@example.org');
        $this->assertUrlList(
            'https://example.org/.well-known/webfinger?resource=acct%3Auser%40example.org'
        );
        $this->assertDescribes('acct:user@example.org', $react);
    }
}

?>