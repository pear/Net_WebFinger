<?php
require_once 'Net/WebFinger.php';

require_once 'HTTP/Request2.php';
require_once 'HTTP_Request2_Adapter_LogMock.php';

class Net_WebFingerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var HTTP_Request2_Adapter_Mock
     */
    protected $adapter;

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
        $this->assertFalse($react->secure);
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
        $react = new Net_WebFinger_Reaction();
        $rm->invoke(
            $wf, $react, __DIR__ . '/../subject.xrd'
        );

        $this->assertNoError($react);
        $this->assertEquals('23.42.net', $react->subject);
    }

    public function testLoadXrdNoHttpClientError()
    {
        $wf = new Net_WebFinger();
        $rm = new ReflectionMethod($wf, 'loadXrd');
        $rm->setAccessible(true);
        $react = new Net_WebFinger_Reaction();
        $rm->invoke(
            $wf, $react, __DIR__ . '/doesnotexist'
        );

        $this->assertNotNull($react->error);
        $this->assertEquals(
            'Error loading XRD file',
            $react->error->getMessage()
        );
    }



    /* helper methods + assertions */

    protected function addHttpMock(Net_WebFinger $wf)
    {
        $this->adapter = new HTTP_Request2_Adapter_LogMock();
        $req = new HTTP_Request2();
        $req->setAdapter($this->adapter);
        $wf->setHttpClient($req);
        return $this;
    }

    protected function addHttpResponse($response, $url = null)
    {
        $this->adapter->addResponse($response, $url);
        return $this;
    }

    protected function assertNoError(Net_WebFinger_Reaction $react)
    {
        if ($react->error === null) {
            $this->assertNull($react->error);
            return;
        }

        $this->fail(
            'Reaction has an error: ' . $react->error->getMessage()
        );
    }

    protected function assertDescribes($url, Net_WebFinger_Reaction $react)
    {
        $this->assertNoError($react);
        $this->assertTrue(
            $react->describes($url),
            'Reaction does not describe "' . $url . '"'
            . ' but is for "' . $react->subject . '"'
        );
    }

    protected function assertUrlList()
    {
        $expectedUrls = func_get_args();
        $this->assertEquals(
            $expectedUrls, $this->adapter->requestedUrls,
            'Expected URL list does not match with reality'
        );
    }

    /* data generators */

    protected function getWebfinger()
    {
        return implode(
            "\r\n",
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: application/jrd+json',
                'Connection: close',
                '',
                '{',
                '    "subject" : "acct:user@example.org",',
                '    "links" : [',
                '        {',
                '            "rel" : "http://webfinger.example/rel/avatar",',
                '            "type" : "image/jpeg",',
                '            "href" : "http://www.example.com/~user/user.jpg"',
                '        }',
                '    ]',
                '}'
            )
        );
    }

    protected function getHostMeta()
    {
        return implode(
            "\r\n",
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: application/xrd+xml',
                'Connection: close',
                '',
                '<?xml version="1.0"?>',
                '<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0">',
                ' <Subject>example.org</Subject>',
                ' <Link rel="lrdd" template="https://example.org/lrdd?acct={uri}" />',
                '</XRD>'
            )
        );
    }

    protected function getHostMetaOpenId()
    {
        return implode(
            "\r\n",
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: application/xrd+xml',
                'Connection: close',
                '',
                '<?xml version="1.0"?>',
                '<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0">',
                ' <Subject>example.org</Subject>',
                ' <Link rel="lrdd" template="https://example.org/lrdd?acct={uri}" />',
                ' <Link rel="http://specs.openid.net/auth/2.0/provider" href="http://id.example.org/"/>',
                '</XRD>'
            )
        );
    }

    protected function getHostMetaEmpty()
    {
        return implode(
            "\r\n",
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: application/xrd+xml',
                'Connection: close',
                '',
                '<?xml version="1.0"?>',
                '<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0">',
                ' <Subject>example.org</Subject>',
                '</XRD>'
            )
        );
    }

    protected function getLrdd()
    {
        return implode(
            "\r\n",
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: application/xrd+xml',
                'Connection: close',
                '',
                '<?xml version="1.0"?>',
                '<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0">',
                ' <Subject>acct:user@example.org</Subject>',
                ' <Link rel="http://specs.openid.net/auth/2.0/provider" template="http://id.example.org/user"/>',
                '</XRD>'
            )
        );
    }

    protected function getLrddEmpty()
    {
        return implode(
            "\r\n",
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: application/xrd+xml',
                'Connection: close',
                '',
                '<?xml version="1.0"?>',
                '<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0">',
                ' <Subject>acct:user@example.org</Subject>',
                '</XRD>'
            )
        );
    }
}
?>