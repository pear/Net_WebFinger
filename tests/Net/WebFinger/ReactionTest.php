<?php
require_once 'Net/WebFinger/Reaction.php';
require_once 'XML/XRD.php';

class Net_WebFinger_ReactionTest extends PHPUnit_Framework_TestCase
{
    public function test__get()
    {
        $react = new Net_WebFinger_Reaction();
        $react->userXrd = new XML_XRD();
        $react->userXrd->links[0] = new XML_XRD_Element_Link();
        $react->userXrd->links[0]->rel  = 'http://gmpg.org/xfn/11'; 
        $react->userXrd->links[0]->href = 'http://example.org/xfn.htm';

        $this->assertEquals('http://example.org/xfn.htm', $react->xfn);
    }

    public function test__isset()
    {
        $react = new Net_WebFinger_Reaction();
        $this->assertTrue(isset($react->openid));
        $this->assertFalse(isset($react->doesnotexist));
    }

    public function test__getUnknownShortname()
    {
        $react = new Net_WebFinger_Reaction();
        $react->userXrd = new XML_XRD();

        $this->assertNull($react->doesnotexist);
    }


    public function testGetNull()
    {
        $react = new Net_WebFinger_Reaction();
        $react->userXrd = new XML_XRD();

        $this->assertNull($react->get('http://gmpg.org/xfn/11'));
    }

    public function testGetAllParams()
    {
        $react = new Net_WebFinger_Reaction();
        $react->userXrd = new XML_XRD();
        $react->userXrd->links[0] = new XML_XRD_Element_Link();
        $react->userXrd->links[0]->rel  = 'http://gmpg.org/xfn/11'; 
        $react->userXrd->links[0]->href = 'http://example.org/xfn.htm';
        $react->userXrd->links[0]->type = 'application/xhtml+xml'; 

        $this->assertNull(
            $react->get('http://gmpg.org/xfn/11', 'text/html', false)
        );
    }


    public function testGetLinkFallbackToHostMeta()
    {
        $react = new Net_WebFinger_Reaction();
        $react->userXrd = new XML_XRD();
        $react->hostMetaXrd = new XML_XRD();
        $react->hostMetaXrd->links[0] = new XML_XRD_Element_Link();
        $react->hostMetaXrd->links[0]->rel  = 'http://gmpg.org/xfn/11'; 
        $react->hostMetaXrd->links[0]->href = 'http://example.org/xfn.htm';
        $react->hostMetaXrd->links[0] = new XML_XRD_Element_Link();
        $react->hostMetaXrd->links[0]->rel  = 'http://specs.openid.net/auth/2.0/provider'; 
        $react->hostMetaXrd->links[0]->href = 'http://example.org/id';

        $this->assertNull(
            $react->getLink('http://gmpg.org/xfn/11'),
            'XFN should *not* fall back to host'
        );

        $this->assertInstanceOf(
            'XML_XRD_Element_Link',
            $react->getLink('http://specs.openid.net/auth/2.0/provider'),
            'OpenID provider *should* fall back to host'
        );
    }


    public function testGetIterator()
    {
        $react = new Net_WebFinger_Reaction();
        $react->userXrd = new XML_XRD();
        $react->hostMetaXrd = new XML_XRD();

        $react->hostMetaXrd->links[] = new XML_XRD_Element_Link(
            'http://gmpg.org/xfn/11', 'http://example.org/xfn.htm'
        );
        $react->hostMetaXrd->links[] = new XML_XRD_Element_Link(
            'http://specs.openid.net/auth/2.0/provider', 'http://example.org/id'
        );

        $react->userXrd->links[] = new XML_XRD_Element_Link(
            'http://gmpg.org/xfn/11', 'http://example.org/user-xfn.htm'
        );
        $react->userXrd->links[] = new XML_XRD_Element_Link(
            'http://specs.openid.net/auth/2.0/provider', 'http://example.org/user-id'
        );

        $links = array();
        foreach ($react as $link) {
            $links[] = $link;
        }

        $this->assertEquals(2, count($links), 'only links from userXrd should be here');
    }

    public function testGetIteratorFallbackHostMeta()
    {
        $react = new Net_WebFinger_Reaction();
        $react->userXrd = new XML_XRD();
        $react->hostMetaXrd = new XML_XRD();

        $react->hostMetaXrd->links[] = new XML_XRD_Element_Link(
            'http://gmpg.org/xfn/11', 'http://example.org/xfn.htm'
        );
        $react->hostMetaXrd->links[] = new XML_XRD_Element_Link(
            'http://specs.openid.net/auth/2.0/provider', 'http://example.org/id'
        );

        $react->userXrd->links[] = new XML_XRD_Element_Link(
            'http://gmpg.org/xfn/11', 'http://example.org/user-xfn.htm'
        );

        $links = array();
        $idLink = false;
        foreach ($react as $link) {
            $links[] = $link;
            if ($link->rel == 'http://specs.openid.net/auth/2.0/provider'
                && $link->href == 'http://example.org/id'
            ) {
                $idLink = true;
            }
        }

        $this->assertEquals(2, count($links));
        $this->assertTrue($idLink, 'OpenID link from host-meta missing');
    }
}

?>