*************
Net_WebFinger
*************

A WebFinger implementation for PHP.


=======
Example
=======

OpenID discovery
================
::

    <?php
    require_once 'Net/WebFinger.php';
    $wf = new Net_WebFinger();
    $res = $wf->finger('user@example.org');
    $openIdProvider = $res->get('http://specs.openid.net/auth/2.0/provider');
    if ($openIdProvider !== null) {
        echo 'OpenID provider found: ' . $openIdProvider . "\n";
    }
    ?>


Caching
=======
With caching, the retrieved files will be stored locally which leads to faster
lookup times when the same email address is loaded again, and when another
email account on the same host is retrieved.

::

    <?php
    require_once 'Net/WebFinger.php';
    require_once 'Cache.php';
    $wf = new Net_WebFinger();
    $wf->setCache(
        new Cache('file', array('cache_dir' => sys_get_temp_dir() . '/myapp'))
    );
    $res = $wf->finger('user@example.org');
    $openIdProvider = $res->get('http://specs.openid.net/auth/2.0/provider');
    ?>

PEAR's Cache_Lite package does not support per-item lifetimes, so we cannot
use it: http://pear.php.net/bugs/bug.php?id=13297


Simple access
=============
Some common link relations have a short name in Net_WebFinger. Those short
names can be used to access them more easily::

    <?php
    require_once 'Net/WebFinger.php';
    $wf  = new Net_WebFinger();
    $res = $wf->finger('user@example.org');
    if ($res->openid !== null) {
        echo 'OpenID provider found: ' . $res->openid . "\n";
    }
    ?>


XRD file access
===============
Sometimes the simple API is not enough and you need more details.
The result object gives you access to the ``.well-known/host-meta`` and user
XRD file objects::

    <?php
    require_once 'Net/WebFinger.php';
    $wf  = new Net_WebFinger();
    $res = $wf->finger('user@example.org');

    $openIdLink = $res->userXrd->get('http://specs.openid.net/auth/2.0/provider');
    echo $openIdLink->getTitle('de') . ':' . $openIdLink->href . "\n";

    foreach ($res->hostMetaXrd as $link) {
        echo $link->rel . ': ' . $link->href . "\n";
    }
    ?>


Security
========
The underlying XRD files will be retrieved via SSL when possible, with fallback
to normal HTTP. In the latter case, the XRD files need to have valid signatures
in order to be seen as secure.

The XRD subject is also verified. When it does not match the host name of the
email address, then the information are seen as insecure.

You should not trust the information if they are not secure.

::

    <?php
    require_once 'Net/WebFinger.php';
    $wf  = new Net_WebFinger();
    $res = $wf->finger('user@example.org');
    if (!$res->secure) {
        die("Those data may not be trusted\n");
    }


====
TODO
====
- Goal: Discover OpenID provider for email account
- determine which urls may fall back to the host xrd (e.g. yahoo)
- use openid-provider from host-meta xrd (yahoo)


=======
Testing
=======
- See test-mail

- Myspace/facebook?

==========
References
==========

- IETF draft: http://www.ietf.org/id/draft-jones-appsawg-webfinger-00.txt
- Specification: http://code.google.com/p/webfinger/wiki/WebFingerProtocol
- Mailing list: http://groups.google.com/group/webfinger
- Link relations: http://code.google.com/p/webfinger/wiki/CommonLinkRelations
- http://hueniverse.com/2009/09/implementing-webfinger/
- http://hueniverse.com/2009/09/openid-and-lrdd/
- http://paulosman.me/2010/02/01/google-webfinger.html Google have since rolled out WebFinger support for everyone with a Google Profile.
- Finger history: http://www.rajivshah.com/Case_Studies/Finger/Finger.htm
- Ruby implementation: http://intridea.com/2010/2/12/redfinger-a-ruby-webfinger-gem
- Perl implementation: http://search.cpan.org/~tobyink/WWW-Finger-0.101/lib/WWW/Finger/Webfinger.pm
- XRD: http://docs.oasis-open.org/xri/xrd/v1.0/xrd-1.0.html
