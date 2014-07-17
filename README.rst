*************
Net_WebFinger
*************

WebFinger client library for PHP.

Discover meta data about users by just their email address.
Discoverable data may be the user's OpenID, profile page URL,
link to portable contacts, hcard, foaf and other user pages.

Distributed social networks use WebFinger to distribute public encryption keys,
OStatus and Salmon URLs.

Package supports Webfinger (`RFC 7033`__) and can fall back
to `RFC 6415`__ (host-meta + lrdd).

__ http://tools.ietf.org/html/rfc7033
__ http://tools.ietf.org/html/rfc6415

.. contents::


==============
Error handling
==============
The package does not throw any exceptions.
Technically, ``Net_WebFinger_Error`` objects are exceptions, but they are
only set as ``$error`` property in the ``Net_WebFinger_Reaction`` object.

You can ignore them completely if you're just out to get the data.

Sometimes it's even necessary to ignore the data.
Yahoo! for example has a ``host-meta`` file, but no LRDD files.
The OpenID provider URL already noted in ``host-meta``, so even though
fetching the LRDD file fails, information about the OpenID provider is available.


Error handling example
======================
The ``Net_WebFinger_Reaction`` object has an ``$error`` property that contains
an exception with error message and code.
It often even has a previous exception object with more underlying details::

    <?php
    require_once 'Net/WebFinger.php';
    $wf  = new Net_WebFinger();
    $react = $wf->finger('user@example.org');


    if ($react->error !== null) {
        echo "Error when fetching " . $react->url . "\n";
        echo "Error: " . $react->error->getMessage() . "\n";
        if ($react->error->getPrevious()) {
            echo "Underlying error: "
                . $react->error->getPrevious()->getMessage() . "\n";
        }
    }
    ?>


========
Examples
========

OpenID discovery
================
::

    <?php
    require_once 'Net/WebFinger.php';
    $wf = new Net_WebFinger();
    $react = $wf->finger('user@example.org');
    if ($react->error) {
        echo 'There was an error: ' . $react->error->getMessage() . "\n";
    }
    $openIdProvider = $react->get('http://specs.openid.net/auth/2.0/provider');
    if ($openIdProvider !== null) {
        echo 'OpenID provider found: ' . $openIdProvider . "\n";
    }
    ?>


Simple link access
==================
Some common link relations have a short name in ``Net_WebFinger``.
Those short names can be used to access them more easily::

    <?php
    require_once 'Net/WebFinger.php';
    $wf  = new Net_WebFinger();
    $react = $wf->finger('user@example.org');
    if ($react->error) {
        echo 'There was an error: ' . $react->error->getMessage() . "\n";
    }
    if ($react->openid !== null) {
        echo 'OpenID provider found: ' . $react->openid . "\n";
    }
    ?>

Currently supported short names:

- ``contacts``
- ``hcard``
- ``openid``
- ``profile``
- ``xfn``

See the list ``$shortNameMap`` in class ``Net_WebFinger_Reaction``.


Accessing all links
===================
You can use ``foreach`` on the reaction object to get all links::

    <?php
    require_once 'Net/WebFinger.php';
    $wf = new Net_WebFinger();
    $react = $wf->finger('user@example.org');
    foreach ($react as $link) {
        echo 'Link: ' . $link->rel . ' to ' . $link->href . "\n";
    }
    ?>


Caching
=======
With caching, the retrieved files will be stored locally which leads
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
    $react = $wf->finger('user@example.org');
    $openIdProvider = $react->get('http://specs.openid.net/auth/2.0/provider');
    ?>

Note: PEAR's Cache_Lite package does not support per-item lifetimes, so we cannot
use it: http://pear.php.net/bugs/bug.php?id=13297


Security
========
All files will be retrieved via SSL when possible, with fallback to normal HTTP.

The fallback for pure webfinger files does only happen when ``$fallbackToHttp``
is enabled.
Fallback for ``host-meta`` and LRDD files is always on.

The XRD subject is also verified.
When it does not match the host name of the email address, then the error
object is set.

::

    <?php
    require_once 'Net/WebFinger.php';
    $wf  = new Net_WebFinger();
    $react = $wf->finger('user@example.org');
    if ($react->error || !$react->secure) {
        die("Those data may not be trusted\n");
    }


Custom HTTP adapter
===================
If you want to send special HTTP headers or need e.g. proxy settings,
you may use an own HTTP adapter that's used to fetch the files::

    <?php
    require_once 'HTTP/Request2.php';
    require_once 'Net/WebFinger.php';

    $req = new HTTP_Request2();
    $req->setConfig('follow_redirects', true);//needed for full compatibility
    $req->setHeader('User-Agent', 'MyApp 1.42');

    $wf = new Net_WebFinger();
    $wf->setHttpClient($req);
    $react = $wf->finger('foo@example.org');


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
- `First webfinger specification`__
- `Common link relations`__
- `IETF webfinger draft`__
- http://hueniverse.com/2009/09/implementing-webfinger/
- http://hueniverse.com/2009/09/openid-and-lrdd/
- http://paulosman.me/2010/02/01/google-webfinger.html Google have since rolled out WebFinger support for everyone with a Google Profile.
- `Finger history`__
- `XRD 1.0 specification`__ 

__ http://groups.google.com/group/webfinger
__ http://code.google.com/p/webfinger/wiki/WebFingerProtocol
__ http://code.google.com/p/webfinger/wiki/CommonLinkRelations
__ http://tools.ietf.org/html/draft-ietf-appsawg-webfinger-13
__ http://www.rajivshah.com/Case_Studies/Finger/Finger.htm
__ http://docs.oasis-open.org/xri/xrd/v1.0/xrd-1.0.html


Alternate implementations
=========================
See http://www.packetizer.com/webfinger/software.html

- Ruby:

  - Redfinger__
  - Webfinger__

- Perl: `WWW::Finger::Webfinger`__
- PHP: discovery-php__ 
- PHP Wordpress plugin: Blogpost__, `webfinger-profile plugin`__

__ http://intridea.com/2010/2/12/redfinger-a-ruby-webfinger-gem
__ http://rubyforge.org/projects/webfinger/
__ http://search.cpan.org/~tobyink/WWW-Finger-0.104/lib/WWW/Finger/Webfinger.pm
__ https://github.com/walkah/discovery-php
__ http://blog.duthied.com/2011/08/30/webfinger-profile-plugin/
__ http://wordpress.org/extend/plugins/webfinger-profile/
