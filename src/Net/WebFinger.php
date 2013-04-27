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

require_once 'Net/WebFinger/Error.php';
require_once 'Net/WebFinger/Reaction.php';

/**
 * PHP WebFinger client. Performs discovery and returns a result.
 *
 * Fetches the well-known URI
 * https://example.org/.well-known/webfinger?resource=acct:user@example.org
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
     * Retry with HTTP if the HTTPS request fails.
     * This is not allowed by the webfinger specification, but may be
     * helpful during development.
     *
     * @var boolean
     */
    public $fallbackToHttp = false;

    /**
     * HTTP client to use.
     *
     * @var HTTP_Request2
     */
    protected $httpClient;

    /**
     * Cache object to use (PEAR Cache package).
     *
     * @var Cache
     */
    protected $cache;

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
     * Set a cache object that's used to buffer XRD files.
     *
     * @param Cache $cache PEAR cache object
     *
     * @return void
     */
    public function setCache(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Finger a email address like identifier - get information about it.
     *
     * If an error occurs, you find it in the reaction's $error property.
     *
     * @param string $identifier E-mail address like identifier ("user@host")
     *
     * @return Net_WebFinger_Reaction Reaction object
     *
     * @see Net_WebFinger_Reaction::$error
     */
    public function finger($identifier)
    {
        $identifier = strtolower($identifier);
        $host       = substr($identifier, strpos($identifier, '@') + 1);

        return $this->loadInfo($identifier, $host);
    }

    /**
     * Loads the user JRD file for a given identifier
     *
     * When loading of the file fails, false is returned.
     *
     * @param string $identifier E-mail address like identifier ("user@host")
     * @param string $host       Hostname of $identifier
     *
     * @return Net_WebFinger_Reaction Reaction object with user and error
     *                                information
     *
     * @see Net_WebFinger_Reaction::$error
     */
    protected function loadInfo($identifier, $host)
    {
        $account = 'acct:' . $identifier;
        $userUrl = 'https://' . $host . '/.well-known/webfinger?resource='
            . urlencode($account);

        $react = new Net_WebFinger_Reaction();
        $this->loadXrd($userUrl, $react);

        if ($this->fallbackToHttp && $react->error !== null
            && $this->isHttps($userUrl)
        ) {
            //fall back to HTTP
            $react->error = null;
            $userUrl = 'http://' . substr($userUrl, 8);
            $this->loadXrd($userUrl, $react);
        }
        if (!$react->error !== null) {
            return $react;
        }

        if (!$this->isHttps($userUrl)) {
            $react->secure = false;
        }
        if (!$react->describes($account)) {
            $react->secure = false;
        }

        return $react;
    }

    /**
     * Check whether the URL is an HTTPS URL.
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
     * @return boolean True if loading data succeeded, false if not
     */
    protected function loadXrd($url, Net_WebFinger_Reaction $react)
    {
        try {
            if ($this->httpClient !== null) {
                $this->httpClient->setUrl($url);
                $this->httpClient->setHeader('accept', 'application/jrd+json', true);
                $react->loadString($this->httpClient->send()->getBody(), 'json');
            } else {
                $react->loadFile($url, 'json');
            }
            return true;
        } catch (Exception $e) {
            $react->error = $e;
            return false;
        }
    }

}

?>