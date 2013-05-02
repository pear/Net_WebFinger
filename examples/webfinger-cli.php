<?php
if ($argc < 2) {
    echo <<<TXT
Usage: $argv[0] user@example.com

TXT;
    exit(1);
}
$identifier = $argv[1];

if (is_dir(__DIR__ . '/../src/')) {
    set_include_path(
        get_include_path() . PATH_SEPARATOR . __DIR__ . '/../src/'
    );
}
    set_include_path(
        '/home/cweiske/Dev/pear/git-packages/XML_XRD/src/' . PATH_SEPARATOR . get_include_path()
    );
require_once 'Net/WebFinger.php';


echo 'Discovering ' . $identifier . "\n";

$wf = new Net_WebFinger();
$react = $wf->finger($identifier);

echo 'Information secure? ' . var_export($react->secure, true) . "\n";

if ($react->error !== null) {
    echo 'Error: ' . $react->error->getMessage() . "\n";
    if ($react->error->getPrevious()) {
        echo ' Underlying error: '
            . trim($react->error->getPrevious()->getMessage()) . "\n";
    }
}

if ($react->openid === null) {
    echo "No OpenID provider found\n";
} else {
    echo 'OpenID provider: ' . $react->openid . "\n";
}

foreach ($react as $link) {
    echo 'Link: ' . $link->rel . ': ' . ($link->href ? $link->href : $link->template) . "\n";
}

?>