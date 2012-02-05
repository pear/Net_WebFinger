<?php
require_once 'Net/WebFinger.php';
require_once 'HTTP/Request2.php';
require_once 'HTTP/Request2/Adapter/Mock.php';

class Net_WebFingerTest extends PHPUnit_Framework_TestCase
{
    protected function getHostMeta()
    {
        $xrd = new XML_XRD();
        $xrd->subject = 'example.org';
        $xrd->links[0] = new XML_XRD_Element_Link();
        $xrd->links[0]->rel = 'lrdd';
        $xrd->links[0]->template = 'https://example.org/lrdd?acct={uri}';
        return $xrd;
    }

    protected function getLrdd()
    {
        $xrd = new XML_XRD();
        $xrd->subject = 'acct:user@example.org';
        $xrd->links[0] = new XML_XRD_Element_Link();
        $xrd->links[0]->rel = 'http://specs.openid.net/auth/2.0/provider';
        $xrd->links[0]->uri = 'http://id.example.org/user';
        return $xrd;
    }

    protected function addLoadXrdExpect($obj, $atPos, $url, $retVal = null)
    {
        $obj->expects($this->at($atPos))
            ->method('loadXrd')
            ->with(
                $this->equalTo($url),
                $this->anything()
            )
            ->will($this->returnValue($retVal));
    }

    public function testFingerFetchesHostMetaSslFirst()
    {
        $wf = $this->getMock('Net_WebFinger', array('loadXrd'));
        $this->addLoadXrdExpect(
            $wf, 0,
            'https://example.org/.well-known/host-meta',
            $this->getHostMeta()
        );

        $wf->expects($this->at(1))
            ->method('loadXrd')
            ->with(
                $this->equalTo('https://example.org/lrdd?acct=acct%3Auser%40example.org'),
                $this->anything()
            );

        $wf->finger('user@example.org');
    }

    public function testFingerNoHostMeta()
    {
        $wf = $this->getMock('Net_WebFinger', array('loadXrd'));
        //https
        $wf->expects($this->at(0))
            ->method('loadXrd')
            ->will($this->returnValue(null));
        //http
        $this->addLoadXrdExpect(
            $wf, 1,
            'http://example.org/.well-known/host-meta'
        );

        $react = $wf->finger('user@example.org');
        $this->assertEquals(
            'No .well-known/host-meta for example.org', $react->error->getMessage()
        );
    }

    public function testFingerFetchesHostMetaHttpFallback()
    {
        $wf = $this->getMock('Net_WebFinger', array('loadXrd'));
        //https
        $this->addLoadXrdExpect(
            $wf, 0,
            'https://example.org/.well-known/host-meta'
        );

        //http
        $this->addLoadXrdExpect(
            $wf, 1,
            'http://example.org/.well-known/host-meta', $this->getHostMeta()
        );

        //lrdd
        $this->addLoadXrdExpect(
            $wf, 2,
            'https://example.org/lrdd?acct=acct%3Auser%40example.org'
        );

        $wf->finger('user@example.org');
    }

    public function testFingerUserFallbackHttp()
    {
        $wf = $this->getMock('Net_WebFinger', array('loadXrd'));

        //host-meta
        $wf->expects($this->at(0))
            ->method('loadXrd')
            ->will($this->returnValue($this->getHostMeta()));

        //https lrdd
        $wf->expects($this->at(1))
            ->method('loadXrd')
            ->with(
                $this->equalTo('https://example.org/lrdd?acct=acct%3Auser%40example.org'),
                $this->anything()
            )
            ->will($this->returnValue(null));

        //http lrdd
        $wf->expects($this->at(2))
            ->method('loadXrd')
            ->with(
                $this->equalTo('http://example.org/lrdd?acct=acct%3Auser%40example.org'),
                $this->anything()
            );

        $wf->finger('user@example.org');
    }

    public function testFingerNoLrdd()
    {
        $wf = $this->getMock('Net_WebFinger', array('loadXrd'));
        //https
        $wf->expects($this->at(0))
            ->method('loadXrd')
            ->will($this->returnValue(new XML_XRD()));

        $react = $wf->finger('user@example.org');
        $this->assertEquals(
            'No lrdd link in host-meta for example.org', $react->error->getMessage()
        );
    }



    public function testFingerSecurityAllHttps()
    {
        $wf = $this->getMock('Net_WebFinger', array('loadXrd'));
        //https
        $this->addLoadXrdExpect(
            $wf, 0,
            'https://example.org/.well-known/host-meta',
            $this->getHostMeta()
        );

        //https lrdd
        $this->addLoadXrdExpect(
            $wf, 1,
            'https://example.org/lrdd?acct=acct%3Auser%40example.org',
            $this->getLrdd()
        );

        $react = $wf->finger('user@example.org');
        $this->assertTrue($react->secure);
    }

    public function testFingerSecurityHostMetaHttp()
    {
        $wf = $this->getMock('Net_WebFinger', array('loadXrd'));

        $this->addLoadXrdExpect(
            $wf, 0,
            'https://example.org/.well-known/host-meta'
        );
        $this->addLoadXrdExpect(
            $wf, 1,
            'http://example.org/.well-known/host-meta', $this->getHostMeta()
        );

        $react = $wf->finger('user@example.org');
        $this->assertFalse($react->secure);
    }

    public function testFingerSecurityHostMetaHttpLrddHttps()
    {
        $wf = $this->getMock('Net_WebFinger', array('loadXrd'));

        $this->addLoadXrdExpect(
            $wf, 0,
            'https://example.org/.well-known/host-meta'
        );
        $this->addLoadXrdExpect(
            $wf, 1,
            'http://example.org/.well-known/host-meta', $this->getHostMeta()
        );
        $this->addLoadXrdExpect(
            $wf, 2,
            'https://example.org/lrdd?acct=acct%3Auser%40example.org',
            $this->getLrdd()
        );

        $react = $wf->finger('user@example.org');
        $this->assertFalse($react->secure);
    }

    public function testFingerSecurityHostMetaHttpsLrddHttp()
    {
        $wf = $this->getMock('Net_WebFinger', array('loadXrd'));

        $this->addLoadXrdExpect(
            $wf, 0,
            'https://example.org/.well-known/host-meta', $this->getHostMeta()
        );
        $this->addLoadXrdExpect(
            $wf, 1,
            'https://example.org/lrdd?acct=acct%3Auser%40example.org'
        );
        $this->addLoadXrdExpect(
            $wf, 2,
            'http://example.org/lrdd?acct=acct%3Auser%40example.org',
            $this->getLrdd()
        );

        $react = $wf->finger('user@example.org');
        $this->assertFalse($react->secure);
    }

    public function testFingerSecurityLrddSubjectWrong()
    {
        $wf = $this->getMock('Net_WebFinger', array('loadXrd'));

        $this->addLoadXrdExpect(
            $wf, 0,
            'https://example.org/.well-known/host-meta', $this->getHostMeta()
        );
        $lrdd = $this->getLrdd();
        $lrdd->subject = 'otherhost.example.org';
        $this->addLoadXrdExpect(
            $wf, 1,
            'https://example.org/lrdd?acct=acct%3Auser%40example.org',
            $lrdd
        );

        $react = $wf->finger('user@example.org');
        $this->assertFalse(
            $react->secure,
            'Host and XRD subject do not match, should not be secure anymore'
        );
    }

    public function testSetHttpClient()
    {
        $req = new HTTP_Request2();
        $adapter = new HTTP_Request2_Adapter_Mock();
        $adapter->addResponse(
            implode(
                "\r\n",
                array(
                    'HTTP/1.1 200 OK',
                    'Content-Type: application/xrd+xml',
                    'Connection: close',
                    '',
                    '<?xml version="1.0"?>',
                    '<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0">',
                    '</XRD>'
                )
            )
        );
        $req->setAdapter($adapter);

        $wf = new Net_WebFinger();
        $wf->setHttpClient($req);
        $react = $wf->finger('foo@example.org');
        $this->assertEquals(
            'No lrdd link in host-meta for example.org', $react->error->getMessage()
        );
    }


    public function testLoadXrdExceptionHandling()
    {
        $req = new HTTP_Request2();
        $adapter = new HTTP_Request2_Adapter_Mock();
        $adapter->addResponse(new HTTP_Request2_Exception('Fire in the tree!'));
        $req->setAdapter($adapter);

        $wf = new Net_WebFinger();
        $wf->setHttpClient($req);
        $react = $wf->finger('foo@example.org');
        $this->assertEquals(
            'No .well-known/host-meta for example.org',
            $react->error->getMessage()
        );
    }


    public function testLoadXrdNoHttpClient()
    {
        $wf = new Net_WebFinger();
        $rm = new ReflectionMethod($wf, 'loadXrd');
        $rm->setAccessible(true);
        $xrd = $rm->invoke($wf, __DIR__ . '/../subject.xrd', new Net_WebFinger_Reaction());
        $this->assertInstanceOf('XML_XRD', $xrd);
        $this->assertEquals('23.42.net', $xrd->subject);
    }
}
?>