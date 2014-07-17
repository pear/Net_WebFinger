<?php
require_once 'Net/WebFinger.php';
require_once 'Net/WebFingerTestBase.php';

class Net_WebFingerTest extends Net_WebFingerTestBase
{
    public function setUp()
    {
        $this->wf = new Net_WebFinger();
        $this->addHttpMock($this->wf);
    }

    public function testFingerFetchesWebfingerFirst()
    {
        $this->addHttpResponse(
            $this->getWebfinger(),
            'https://example.org/.well-known/webfinger?resource=acct%3Auser%40example.org'
        );

        $react = $this->wf->finger('user@example.org');

        $this->assertUrlList(
            'https://example.org/.well-known/webfinger?resource=acct%3Auser%40example.org'
        );
        $this->assertDescribes('acct:user@example.org', $react);
    }

    public function testFingerXmpp()
    {
        $this->addHttpResponse(
            $this->getWebfingerXmpp(),
            'https://example.org/.well-known/webfinger?resource=xmpp%3Auser%40example.org'
        );

        $react = $this->wf->finger('xmpp:user@example.org');

        $this->assertUrlList(
            'https://example.org/.well-known/webfinger?resource=xmpp%3Auser%40example.org'
        );
        $this->assertDescribes('xmpp:user@example.org', $react);
    }

    public function testFingerWebfingerFallbackHttp()
    {
        $this->addHttpResponse(
            new HTTP_Request2_Exception('No webfinger for you.')
        )->addHttpResponse(
            $this->getWebfinger()
        );

        $this->wf->fallbackToHttp = true;
        $react = $this->wf->finger('user@example.org');

        $this->assertUrlList(
            'https://example.org/.well-known/webfinger?resource=acct%3Auser%40example.org',
            'http://example.org/.well-known/webfinger?resource=acct%3Auser%40example.org'
        );
        $this->assertDescribes('acct:user@example.org', $react);
    }

    public function testFingerFetchesHostMetaSslBeforeNonSsl()
    {
        $this->addHttpResponse(
            new HTTP_Request2_Exception('No webfinger for you.')
        );

        $react = $this->wf->finger('user@example.org');
        $this->assertUrlList(
            'https://example.org/.well-known/webfinger?resource=acct%3Auser%40example.org',
            'https://example.org/.well-known/host-meta',
            'http://example.org/.well-known/host-meta'
        );
        $this->assertEquals(
            'No .well-known/host-meta file found on example.org', $react->error->getMessage()
        );
    }

    public function testFingerLrdd()
    {
        $this->addHttpResponse(
            new HTTP_Request2_Exception('No webfinger for you.')
        )
            ->addHttpResponse($this->getHostMeta())
            ->addHttpResponse($this->getLrdd());
        $react = $this->wf->finger('user@example.org');

        $this->assertUrlList(
            'https://example.org/.well-known/webfinger?resource=acct%3Auser%40example.org',
            'https://example.org/.well-known/host-meta',
            'https://example.org/lrdd?acct=acct%3Auser%40example.org'
        );

        $this->assertNoError($react);
        $this->assertDescribes('acct:user@example.org', $react);
    }

    public function testFingerLrddOpenIdFromHostMeta()
    {
        $this->addHttpResponse(
            new HTTP_Request2_Exception('No webfinger for you.')
        )
            ->addHttpResponse($this->getHostMetaOpenId())
            ->addHttpResponse($this->getLrddEmpty());
        $react = $this->wf->finger('user@example.org');

        $this->assertUrlList(
            'https://example.org/.well-known/webfinger?resource=acct%3Auser%40example.org',
            'https://example.org/.well-known/host-meta',
            'https://example.org/lrdd?acct=acct%3Auser%40example.org'
        );

        $this->assertNoError($react);
        $this->assertDescribes('acct:user@example.org', $react);
        $this->assertEquals('http://id.example.org/', $react->openid);
    }

    public function testFingerLrddFallbackHttp()
    {
        $this->addHttpResponse(
            new HTTP_Request2_Exception('No webfinger for you.')
        )
            ->addHttpResponse($this->getHostMeta())
            ->addHttpResponse(
                new HTTP_Request2_Exception('No SSL lrdd for you.')
            )
            ->addHttpResponse($this->getLrdd());
        $react = $this->wf->finger('user@example.org');

        $this->assertUrlList(
            'https://example.org/.well-known/webfinger?resource=acct%3Auser%40example.org',
            'https://example.org/.well-known/host-meta',
            'https://example.org/lrdd?acct=acct%3Auser%40example.org',
            'http://example.org/lrdd?acct=acct%3Auser%40example.org'
        );

        $this->assertNoError($react);
        $this->assertDescribes('acct:user@example.org', $react);
    }

    public function testFingerHostMetaNoLrddLink()
    {
        $this->addHttpResponse(
            new HTTP_Request2_Exception('No webfinger for you.')
        )
            ->addHttpResponse($this->getHostMetaEmpty());
        $react = $this->wf->finger('user@example.org');
        $this->assertEquals(
            'No lrdd link in host-meta for example.org', $react->error->getMessage()
        );
    }


    public function testFingerSecurityLrddAllHttps()
    {
        $this->addHttpResponse(
            new HTTP_Request2_Exception('No webfinger for you.')
        )
            ->addHttpResponse($this->getHostMeta())
            ->addHttpResponse($this->getLrdd());
        $react = $this->wf->finger('user@example.org');

        $this->assertUrlList(
            'https://example.org/.well-known/webfinger?resource=acct%3Auser%40example.org',
            'https://example.org/.well-known/host-meta',
            'https://example.org/lrdd?acct=acct%3Auser%40example.org'
        );

        $this->assertNoError($react);
        $this->assertDescribes('acct:user@example.org', $react);
        $this->assertTrue($react->secure);
    }

    public function testFingerNoLrddFile()
    {
        $this->addHttpResponse(
            new HTTP_Request2_Exception('No webfinger for you.')
        )
            ->addHttpResponse($this->getHostMeta());
        $react = $this->wf->finger('user@example.org');

        $this->assertNotNull($react->error);
        $this->assertEquals(
            'No webfinger data found',
            $react->error->getMessage()
        );

        $this->assertNotNull($react->error->getPrevious());
        $this->assertEquals(
            'LRDD file not found',
            $react->error->getPrevious()->getMessage()
        );

        $this->assertNotNull($react->error->getPrevious()->getPrevious());
        $this->assertEquals(
            'Error loading XRD file: 400 Bad Request',
            $react->error->getPrevious()->getPrevious()->getMessage()
        );
    }

    public function testFingerSecurityHostMetaHttp()
    {
        $this->addHttpResponse(
            new HTTP_Request2_Exception('No webfinger for you.')
        )
            ->addHttpResponse(
                new HTTP_Request2_Exception('No SSL host-meta for you.')
            )
            ->addHttpResponse($this->getHostMeta())
            ->addHttpResponse($this->getLrdd());
        $react = $this->wf->finger('user@example.org');

        $this->assertUrlList(
            'https://example.org/.well-known/webfinger?resource=acct%3Auser%40example.org',
            'https://example.org/.well-known/host-meta',
            'http://example.org/.well-known/host-meta',
            'https://example.org/lrdd?acct=acct%3Auser%40example.org'
        );

        $this->assertNoError($react);
        $this->assertDescribes('acct:user@example.org', $react);
        $this->assertFalse($react->secure);
    }

    public function testFingerSecurityHostMetaHttpsLrddHttp()
    {
        $this->addHttpResponse(
            new HTTP_Request2_Exception('No webfinger for you.')
        )
            ->addHttpResponse($this->getHostMeta())
            ->addHttpResponse(
                new HTTP_Request2_Exception('No SSL lrdd for you.')
            )
            ->addHttpResponse($this->getLrdd());
        $react = $this->wf->finger('user@example.org');

        $this->assertUrlList(
            'https://example.org/.well-known/webfinger?resource=acct%3Auser%40example.org',
            'https://example.org/.well-known/host-meta',
            'https://example.org/lrdd?acct=acct%3Auser%40example.org',
            'http://example.org/lrdd?acct=acct%3Auser%40example.org'
        );

        $this->assertNoError($react);
        $this->assertDescribes('acct:user@example.org', $react);
        $this->assertFalse($react->secure, 'Reaction should not be secure');
    }

    public function testFingerSecurityLrddSubjectWrong()
    {
        $this->addHttpResponse(
            new HTTP_Request2_Exception('No webfinger for you.')
        )
            ->addHttpResponse($this->getHostMeta())
            ->addHttpResponse(
                str_replace('example.org', 'bad.com', $this->getLrdd())
            );
        $react = $this->wf->finger('user@example.org');

        $this->assertNotNull($react);
        $this->assertEquals(
            'Webfinger file is not about "acct:user@example.org" but "acct:user@bad.com"',
            $react->error->getMessage()
        );
    }

    public function testLoadXrdNoHttpClient()
    {
        $wf = new Net_WebFinger();
        $rm = new ReflectionMethod($wf, 'loadXrd');
        $rm->setAccessible(true);
        $react = $rm->invoke(
            $wf, __DIR__ . '/../subject.xrd'
        );

        $this->assertNoError($react);
        $this->assertEquals('23.42.net', $react->subject);
    }

    public function testLoadXrdNoHttpClientFileNotFound()
    {
        $wf = new Net_WebFinger();
        $rm = new ReflectionMethod($wf, 'loadXrd');
        $rm->setAccessible(true);
        $react = $rm->invoke(
            $wf, __DIR__ . '/doesnotexist'
        );

        $this->assertNotNull($react->error);
        $this->assertEquals(
            'Error loading XRD file',
            $react->error->getMessage()
        );
    }

    public function testLoadXrdNoHttpClientUrlNotFound()
    {
        $wf = new Net_WebFinger();
        $rm = new ReflectionMethod($wf, 'loadXrd');
        $rm->setAccessible(true);
        $react = $rm->invoke(
            $wf, 'http://127.0.0.127/doesnotexist'
        );

        $this->assertNotNull($react->error);
        $this->assertEquals(
            'Error loading XRD file: HTTP/1.1 404 Not Found',
            $react->error->getMessage()
        );
    }

}
?>