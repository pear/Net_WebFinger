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

    public function testFingerFetchesHostMetaSslFirst()
    {
        $wf = $this->getMock('Net_WebFinger', array('loadXrd'));
        $wf->expects($this->at(0))
            ->method('loadXrd')
            ->with(
                $this->equalTo('https://example.org/.well-known/host-meta'),
                $this->anything()
            )->will($this->returnValue($this->getHostMeta()));

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
        $wf->expects($this->at(1))
            ->method('loadXrd')
            ->with(
                $this->equalTo('http://example.org/.well-known/host-meta'),
                $this->anything()
            )->will($this->returnValue(null));

        $react = $wf->finger('user@example.org');
        $this->assertEquals('No .well-known/host-meta for example.org', $react->error);
    }

    public function testFingerFetchesHostMetaHttpFallback()
    {
        $wf = $this->getMock('Net_WebFinger', array('loadXrd'));
        //https
        $wf->expects($this->at(0))
            ->method('loadXrd')
            ->with(
                $this->equalTo('https://example.org/.well-known/host-meta'),
                $this->anything()
            )
            ->will($this->returnValue(null));

        //http
        $wf->expects($this->at(1))
            ->method('loadXrd')
            ->with(
                $this->equalTo('http://example.org/.well-known/host-meta'),
                $this->anything()
            )
            ->will($this->returnValue($this->getHostMeta()));

        //lrdd
        $wf->expects($this->at(2))
            ->method('loadXrd')
            ->with(
                $this->equalTo('https://example.org/lrdd?acct=acct%3Auser%40example.org'),
                $this->anything()
            )
            ->will($this->returnValue(null));

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
        $this->assertEquals('No lrdd template for example.org', $react->error);
    }
}
?>