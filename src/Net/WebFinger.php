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
 * Fetches the well-known WebFinger URI
 * https://example.org/.well-known/webfinger?resource=acct:user@example.org
 *
 * If that fails, the account's host's .well-known/host-meta file is fetched,
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
     * Retry with HTTP if the HTTPS webfinger request fails.
     * This is not allowed by the webfinger specification, but may be
     * helpful during development.
     *
     * @var boolean
     */
    public $fallbackToHttp = false;

    /**
     * Verify the remote SSL certificate
     *
     * Spec says:
     * > If the client determines that the resource has an invalid certificate,
     * > [...] then the client MUST accept that the WebFinger query has failed
     *
     * @var boolean
     */
    public $verifySslCert = true;

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

        $react = $this->loadWebfinger($identifier, $host);

        if ($react->error === null) {
            //FIXME: only fallback if URL does not exist, not on general error
            // like broken XML/JSON
            return $react;
        }

        //fall back to host-meta and LRDD file if webfinger URL does not exist
        $hostMeta = $this->loadHostMetaCached($host);
        if ($hostMeta->error) {
            return $hostMeta;
        }

        $react = $this->loadLrdd($identifier, $host, $hostMeta);
        if ($react->error
            && $react->error->getCode() == Net_WebFinger_Error::NO_LRDD
        ) {
            $react->error = new Net_WebFinger_Error(
                'No webfinger data found',
                Net_WebFinger_Error::NOTHING,
                $react->error
            );
        }
        return $react;
    }

    /**
     * Loads the webfinger JRD file for a given identifier
     *
     * @param string $identifier E-mail address like identifier ("user@host")
     * @param string $host       Hostname of $identifier
     *
     * @return Net_WebFinger_Reaction Reaction object
     *
     * @see Net_WebFinger_Reaction::$error
     */
    protected function loadWebfinger($identifier, $host)
    {
        $account = 'acct:' . $identifier;
        $userUrl = 'https://' . $host . '/.well-known/webfinger?resource='
            . urlencode($account);

        $react = new Net_WebFinger_Reaction();
        $this->loadXrd($react, $userUrl);

        if ($this->fallbackToHttp && $react->error !== null
            && $this->isHttps($userUrl)
        ) {
            //fall back to HTTP
            $react = new Net_WebFinger_Reaction();
            $userUrl = 'http://' . substr($userUrl, 8);
            $this->loadXrd($react, $userUrl);
            $react->secure = false;
        }
        if ($react->error !== null) {
            return $react;
        }

        $this->verifyDescribes($react, $account);

        return $react;
    }

    /**
     * Load the host's .well-known/host-meta XRD file and caches it.
     *
     * @param string $host  Hostname to fetch host-meta file from
     *
     * @return Net_WebFinger_Reaction Reaction object with host-meta data
     *
     * @see loadHostMeta()
     */
    protected function loadHostMetaCached($host)
    {
        if (!$this->cache) {
            return $this->loadHostMeta($host);
        }

        //FIXME: make $host secure, remove / and so
        $cacheId     = 'hostmeta-' . $host;
        $cacheRetval = $this->cache->get($cacheId);
        if ($cacheRetval !== null) {
            //load from cache
            return $cacheRetval;
        }

        //no cache yet
        $retval = $this->loadHostMeta($host);

        //we do not implement http caching headers yet
        //5 minutes expiry time by default
        $this->cache->save($cacheId, $retval, '+300');

        return $retval;
    }

    /**
     * Load the host's .well-known/host-meta XRD file.
     *
     * The XRD is stored in the reaction object's $source['host-meta'] property,
     * and any error that is encountered in its $error property.
     *
     * When the XRD file cannot be loaded, this method returns false.
     *
     * @param string $host Hostname to fetch host-meta file from
     *
     * @return Net_WebFinger_Reaction Reaction object
     *
     * @see Net_WebFinger_Reaction::$hostMetaXrd
     * @see Net_WebFinger_Reaction::$error
     */
    protected function loadHostMeta($host)
    {
        /**
         * HTTPS is secure.
         * xrd->describes() may not be used because the host-meta should not
         * have a subject at all: http://tools.ietf.org/html/rfc6415#section-3.1
         * > The document SHOULD NOT include a "Subject" element, as at this
         * > time no URI is available to identify hosts.
         * > The use of the "Alias" element in host-meta is undefined and
         * > NOT RECOMMENDED.
         */
        $react = new Net_WebFinger_Reaction();
        $react->secure = true;

        $res = $this->loadXrd($react, 'https://' . $host . '/.well-known/host-meta');
        if (!$res) {
            $res = $this->loadXrd(
                $react, 'http://' . $host . '/.well-known/host-meta'
            );
            //no https, so not secure
            $react->secure = false;
            if (!$res) {
                $react->error = new Net_WebFinger_Error(
                    'No .well-known/host-meta file found on ' . $host,
                    Net_WebFinger_Error::NO_HOSTMETA,
                    $react->error
                );
            }
        }

        return $react;
    }

    /**
     * Loads the user XRD file for a given identifier
     *
     * The XRD is stored in the reaction object's $userXrd property,
     * any error is stored in its $error property.
     *
     * @param string $identifier E-mail address like identifier ("user@host")
     * @param string $host       Hostname of $identifier
     * @param object $hostMeta   host-meta XRD object
     *
     * @return Net_WebFinger_Reaction Reaction object
     *
     * @see Net_WebFinger_Reaction::$hostMetaXrd
     * @see Net_WebFinger_Reaction::$error
     */
    protected function loadLrdd(
        $identifier, $host, XML_XRD $hostMeta
    ) {
        //copy certain links from hostMeta to lrdd
        $react = new Net_WebFinger_Reaction();
        $this->mergeHostMeta($react, $hostMeta);

        $link = $hostMeta->get('lrdd', 'application/xrd+xml');
        if ($link === null || !$link->template) {
            $react->error = new Net_WebFinger_Error(
                'No lrdd link in host-meta for ' . $host,
                Net_WebFinger_Error::NO_LRDD_LINK,
                $react->error
            );
            return $react;
        }

        $account = 'acct:' . $identifier;
        $userUrl = str_replace('{uri}', urlencode($account), $link->template);

        $res = $this->loadXrd($react, $userUrl);
        if (!$res && $this->isHttps($userUrl)) {
            //fall back to HTTP
            $userUrl = 'http://' . substr($userUrl, 8);
            $react->error = null;
            $res = $this->loadXrd($react, $userUrl);
        }
        if (!$res) {
            $react->error = new Net_WebFinger_Error(
                'LRDD file not found',
                Net_WebFinger_Error::NO_LRDD,
                $react->error
            );
            return $react;
        }

        if (!$this->isHttps($userUrl)) {
            $react->secure = false;
        }
        $this->verifyDescribes($react, $account);

        return $react;
    }

    protected function mergeHostMeta(
        Net_WebFinger_Reaction $react, Net_WebFinger_Reaction $hostMeta
    ) {
        foreach ($hostMeta->links as $link) {
            if ($link->rel == 'http://specs.openid.net/auth/2.0/provider') {
                $react->links[] = $link;
            }
        }
        $react->secure = $hostMeta->secure;
    }

    protected function verifyDescribes(Net_WebFinger_Reaction $react, $account)
    {
        if (!$react->describes($account)) {
            $react->error = new Net_WebFinger_Error(
                'Webfinger file is not about "' . $account . '"'
                . ' but "' . $react->subject . '"',
                Net_WebFinger_Error::DESCRIBE
            );
            //additional hint that something is wrong
            $react->secure = false;
        }
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
     * Sets $react->error when loading fails
     *
     * @param object $react Reaction object to store error in
     * @param string $url   URL to fetch
     *
     * @return boolean True if loading data succeeded, false if not
     */
    protected function loadXrd(Net_WebFinger_Reaction $react, $url)
    {
        try {
            $react->error = null;
            if ($this->httpClient !== null) {
                $this->httpClient->setUrl($url);
                $this->httpClient->setHeader(
                    'accept',
                    'application/jrd+json, application/xrd+xml;q=0.9',
                    true
                );
                $res = $this->httpClient->send();
                $code = $res->getStatus();
                if (intval($code / 100) !== 2) {
                    throw new Net_WebFinger_Error(
                        'Error loading XRD file: ' . $res->getStatus()
                        . ' ' . $res->getReasonPhrase(),
                        Net_WebFinger_Error::NOT_FOUND
                    );
                }
                $react->loadString($res->getBody());
            } else {
                $context = stream_context_create(
                    array(
                        'http' => array(
                            'user_agent' => 'PEAR Net_WebFinger',
                            'header' => 'accept: application/jrd+json, application/xrd+xml;q=0.9',
                        ),
                        'ssl' => array(
                            'verify_peer' => $this->verifySslCert
                        )
                    )
                );
                $content = @file_get_contents($url, false, $context);
                if ($content === false) {
                    $msg = 'Error loading XRD file';
                    if (isset($http_response_header)) {
                        $msg .= ': ' . $http_response_header[0];
                    };
                    throw new Net_WebFinger_Error(
                        $msg, Net_WebFinger_Error::NOT_FOUND,
                        new Net_WebFinger_Error(
                            'file_get_contents on ' . $url
                        )
                    );
                }
                $react->loadString($content);
            }
            return true;
        } catch (Exception $e) {
            $react->error = $e;
            return false;
        }
    }

}

?>