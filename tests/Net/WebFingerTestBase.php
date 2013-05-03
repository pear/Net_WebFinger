<?php
require_once 'HTTP/Request2.php';
require_once 'HTTP_Request2_Adapter_LogMock.php';

class Net_WebFingerTestBase extends PHPUnit_Framework_TestCase
{
    /**
     * @var HTTP_Request2_Adapter_Mock
     */
    protected $adapter;

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
