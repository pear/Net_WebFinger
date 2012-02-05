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
require_once 'Net/WebFinger.php';


echo 'Discovering ' . $identifier . "\n";

$wf = new Net_WebFinger();
$res = $wf->finger($identifier);

echo 'Information secure? ' . var_export($res->secure, true) . "\n";

if ($res->error !== null) {
    echo 'Error: ' . $res->error->getMessage() . "\n";
    if ($res->error->getPrevious()) {
        echo ' Underlying error: '
            . trim($res->error->getPrevious()->getMessage()) . "\n";
    }
}

if ($res->openid === null) {
    echo "No OpenID provider found\n";
} else {
    echo 'OpenID provider: ' . $res->openid . "\n";
}

if ($res->userXrd) {
    foreach ($res->userXrd as $link) {
        echo 'Link: ' . $link->rel . ': ' . ($link->href ? $link->href : $link->template) . "\n";
    }
}

?>