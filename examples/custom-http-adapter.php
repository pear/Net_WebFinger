<?php
require_once 'HTTP/Request2.php';
require_once 'Net/WebFinger.php';

$req = new HTTP_Request2();
$req->setConfig('follow_redirects', true);
$req->setHeader('User-Agent', 'Net_WebFinger custom-http-adapter example');

$wf = new Net_WebFinger();
$wf->setHttpClient($req);
$react = $wf->finger('foo@example.org');

foreach ($react as $link) {
    echo 'Link: ' . $link->rel . ': ' . ($link->href ? $link->href : $link->template) . "\n";
}

echo "..done\n";

?>
