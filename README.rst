*************
Net_WebFinger
*************

WebFinger client library for PHP.

Discover meta data about users by just their email address.
Discoverable data may be the user's OpenID, profile page URL,
link to portable contacts, hcard, foaf and other user pages.

Distributed social networks use WebFinger to distribute public encryption keys,
OStatus and Salmon URLs.

.. contents::

========
Examples
========

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



Caching
=======
With caching, the retrieved host-meta files will be stored locally which leads
to faster lookup times when the same identifier (email address) is loaded again,
and when another identifier on the same host is retrieved.
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

Note: PEAR's Cache_Lite package does not support per-item lifetimes, so we cannot
use it: http://pear.php.net/bugs/bug.php?id=13297


XRD file access
===============
Sometimes the simple API is not enough and you need more details.
The result object gives you access to the ``.well-known/host-meta`` and user
XRD (LRDD) file objects::

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


=======
Testing
=======
You can use this identifiers to test the WebFinger functionality on various
providers:

- Gmail: evalpaul@gmail.com
- Yahoo: mcorne@yahoo.com
- AOL: M4dSquirrels@aol.com
- other:

  - cweiske@cweiske.de
  - darron@froese.org https://github.com/intridea/redfinger/issues/2

- diaspora: kevinkleinman@joindiaspora.com
- status.net: singpolyma@identi.ca


=====
Links
=====

References
==========

- `Webfinger mailing list`__
- `First specification`__
- `Common link relations`__
- `IETF draft`__
- http://hueniverse.com/2009/09/implementing-webfinger/
- http://hueniverse.com/2009/09/openid-and-lrdd/
- http://paulosman.me/2010/02/01/google-webfinger.html Google have since rolled out WebFinger support for everyone with a Google Profile.
- `Finger history`__
- `XRD 1.0 specification`__ 

__ http://groups.google.com/group/webfinger
__ http://code.google.com/p/webfinger/wiki/WebFingerProtocol
__ http://code.google.com/p/webfinger/wiki/CommonLinkRelations
__ http://www.ietf.org/id/draft-jones-appsawg-webfinger-00.txt
__ http://www.rajivshah.com/Case_Studies/Finger/Finger.htm
__ http://docs.oasis-open.org/xri/xrd/v1.0/xrd-1.0.html


Alternate implementations
=========================

- Ruby: Redfinger__
- Perl: `WWW::Finger::Webfinger`__
- PHP: discovery-php__ 
- PHP Wordpress plugin: Blogpost__, `webfinger-profile plugin`__

__ http://intridea.com/2010/2/12/redfinger-a-ruby-webfinger-gem
__ http://search.cpan.org/~tobyink/WWW-Finger-0.101/lib/WWW/Finger/Webfinger.pm
__ https://github.com/walkah/discovery-php
__ http://blog.duthied.com/2011/08/30/webfinger-profile-plugin/
__ http://wordpress.org/extend/plugins/webfinger-profile/
