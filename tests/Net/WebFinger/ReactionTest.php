<?php
require_once 'Net/WebFinger/Reaction.php';
require_once 'XML/XRD.php';

class Net_WebFinger_ReactionTest extends PHPUnit_Framework_TestCase
{
    public function test__get()
    {
        $react = new Net_WebFinger_Reaction();
        $react->links[0] = new XML_XRD_Element_Link();
        $react->links[0]->rel  = 'http://gmpg.org/xfn/11'; 
        $react->links[0]->href = 'http://example.org/xfn.htm';

        $this->assertEquals('http://example.org/xfn.htm', $react->xfn);
        $this->assertNull($react->openid);
    }

    public function test__getUnknown()
    {
        $react = new Net_WebFinger_Reaction();
        $this->assertNull($react->unknownproperty);
    }

    public function test__isset()
    {
        $react = new Net_WebFinger_Reaction();
        $this->assertTrue(isset($react->openid));
        $this->assertFalse(isset($react->doesnotexist));
    }
}

?>