<?php
/**
 * Part of Net_WebFinger
 *
 * PHP version 5
 *
 * @category Networking
 * @package  Net_WebFinger
 * @author   Christian Weiske <cweiske@php.net>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL
 * @link     http://pear.php.net/package/Net_WebFinger
 */

require_once 'HTTP/Request2.php';
require_once 'Net/WebFinger/Reaction.php';
require_once 'XML/XRD.php';

/**
 * PHP WebFinger client. Performs discovery and returns a result.
 *
 * At first, the account's host's .well-known/host-meta file is fetched,
 * then the file indicated by the "lrdd" type.
 *
 * <code>
 * require_once 'Net/WebFinger.php';
 * $wf = new Net_WebFinger();
 * $react = $wf->finger('user@example.org');
 * echo 'OpenID: ' . $react->openid . "\n";
 * </code>
 *
 * @category Networking
 * @package  Net_WebFinger
 * @author   Christian Weiske <cweiske@php.net>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL
 * @link     http://pear.php.net/package/Net_WebFinger
 */
class Net_WebFinger
{
    /**
     * HTTP client to use.
     *
     * @var HTTP_Request2
     */
    protected $httpClient;

    /**
     * Set a HTTP client object that's used to fetch URLs.
     *
     * Useful to set an own user agent.
     *
     * @param object $httpClient HTTP_Request2 instance
     *
     * @return void
     */
    public function setHttpClient(HTTP_Request2 $httpClient)
    {
        $this->httpClient = $httpClient;
    }


    /**
     * Finger a email address like identifier - get information about it.
     *
     * @param string $identifier E-mail address like identifier ("user@host")
     *
     * @return Net_WebFinger_Reaction Reaction object
     */
    public function finger($identifier)
    {
        $identifier = strtolower($identifier);
        $host       = substr($identifier, strpos($identifier, '@') + 1);
        $account    = 'acct:' . $identifier;

        $react = new Net_WebFinger_Reaction($identifier);

        $xrd = $this->loadXrd('https://' . $host . '/.well-known/host-meta', $react);
        if (!$xrd) {
            $xrd = $this->loadXrd(
                'http://' . $host . '/.well-known/host-meta', $react
            );
            //TODO: XML signature verification once supported by XML_XRD
            $react->secure = false;
            if (!$xrd) {
                $react->error = 'No .well-known/host-meta for ' . $host;
                return $react;
            }
        }
        $react->hostMetaXrd = $xrd;
        $react->secure = (bool)($react->secure & $xrd->describes($host));

        $link = $xrd->get('lrdd', 'application/xrd+xml');
        if ($link === null || !$link->template) {
            $react->error = 'No lrdd template for ' . $host;
            return $react;
        }

        $userUrl = str_replace('{uri}', urlencode($account), $link->template);

        $react->userXrd = $this->loadXrd($userUrl, $react);
        if ($react->userXrd === null && $this->isHttps($userUrl)) {
            //fall back to HTTP
            $userUrl = 'http://' . substr($userUrl, 8);
            $react->userXrd = $this->loadXrd($userUrl, $react);
        }
        if (!$this->isHttps($userUrl)) {
            $react->secure = false;
            //TODO: XML signature verification once supported by XML_XRD
        }
        if ($react->userXrd && !$react->userXrd->describes($account)) {
            $react->secure = false;
        }

        return $react;
    }

    /**
     * Check wether the URL is an HTTPS url.
     *
     * @param string $url URL to check
     *
     * @return boolean True if it's a HTTPS url
     */
    protected function isHttps($url)
    {
        return substr($url, 0, 8) == 'https://';
    }

    /**
     * Loads the XRD file from the given URL.
     *
     * @param string $url   URL to fetch
     * @param object $react Reaction object to store error in
     *
     * @return XML_XRD XRD object, null if it could not be loaded
     */
    protected function loadXrd($url, Net_WebFinger_Reaction $react)
    {
        try {
            $xrd = new XML_XRD();
            //FIXME: caching
            if ($this->httpClient !== null) {
                $this->httpClient->setUrl($url);
                $this->httpClient->setHeader('accept', 'application/xrd+xml', true);
                $xrd->loadString($this->httpClient->send()->getBody());
            } else {
                $xrd->loadFile($url);
            }

            return $xrd;
        } catch (Exception $e) {
            $react->error = $e->getMessage();
            return null;
        }
    }

}

?>