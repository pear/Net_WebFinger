<?php
require_once 'Net/WebFinger/Reaction.php';
require_once 'XML/XRD.php';

class Net_WebFinger
{
    /**
     * Finger a email address - get information about it.
     *
     * @param string $email E-mail address
     *
     * @return Net_WebFinger_Reaction Reaction object
     */
    public function finger($email)
    {
        $email   = strtolower($email);
        $host    = substr($email, strpos($email, '@') + 1);
        $account = 'acct:' . $email;

        $react  = new Net_WebFinger_Reaction($email);

        $xrd = $this->loadXrd('https://' . $host . '/.well-known/host-meta');
        if (!$xrd) {
            $xrd = $this->loadXrd('http://' . $host . '/.well-known/host-meta');
            //TODO: XML signature verification once supported by XML_XRD
            $react->secure = false;
            if (!$xrd) {
                $react->error = 'No .well-known/host-meta';
                return $react;
            }
        }
        $react->hostMetaXrd = $xrd;
        $react->secure = (bool)($react->secure & $xrd->describes($host));

        $link = $xrd->get('lrdd', 'application/xrd+xml');
        if ($link === null || !$link->template) {
            $react->error = 'No lrdd template';
        }

        $userUrl = str_replace('{uri}', urlencode($account), $link->template);
        //FIXME: mark insecure when url isn't https
        $react->userXrd = $this->loadXrd($userUrl);

        return $react;
    }

    /**
     * Loads the XRD file from the given URL.
     *
     * @param string $url URL to fetch
     *
     * @return XML_XRD XRD object, null if it could not be loaded
     */
    protected function loadXrd($url)
    {
        try {
            $xrd = new XML_XRD();
            //FIXME: use HTTP_Request2
            //FIXME: caching
            $xrd->loadFile($url);
            return $xrd;
        } catch (Exception $e) {
            //FIXME: what to do with the exception?
            return null;
        }
    }

}

?>