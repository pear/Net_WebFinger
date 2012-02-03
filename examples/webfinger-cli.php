<?php
if ($argc < 2) {
    echo <<<TXT
Usage: $argv[0] user@example.com

TXT;
    exit(1);
}
$email = $argv[1];

if (is_dir(__DIR__ . '/../src/')) {
    set_include_path(
        get_include_path() . PATH_SEPARATOR . __DIR__ . '/../src/'
    );
}
require_once 'Net/WebFinger.php';


echo 'Discovering ' . $email . "\n";

$wf = new Net_WebFinger();
$res = $wf->finger($email);

echo 'Information secure? ' . var_export($res->secure, true) . "\n";

if ($res->error !== null) {
    echo 'Error: ' . $res->error . "\n";
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