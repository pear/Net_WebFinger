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

        $react = new Net_WebFinger_Reaction($identifier);

        if (!$this->loadHostMetaCached($react, $host)) {
            return $react;
        }

        $this->loadLrdd($react, $identifier, $host, $react->hostMetaXrd);
        return $react;
    }

    /**
     * Load the host's .well-known/host-meta XRD file and caches it.
     *
     * @param object $react Reaction object to fill
     * @param string $host  Hostname to fetch host-meta file from
     *
     * @return boolean True if the host-meta file could be loaded
     *
     * @see loadHostMeta()
     */
    protected function loadHostMetaCached(Net_WebFinger_Reaction $react, $host)
    {
        if (!$this->cache) {
            return $this->loadHostMeta($react, $host);
        }

        //FIXME: make $host secure, remove / and so
        $cacheId     = 'hostmeta-' . $host;
        $cacheRetval = $this->cache->get($cacheId);
        if ($cacheRetval === null) {
            //no cache yet
            $retval = $this->loadHostMeta($react, $host);
            $data   = array(
                'retval'      => $retval,
                'hostMetaXrd' => $react->hostMetaXrd,
                'error'       => $react->error,
                'secure'      => $react->secure
            );

            //we do not implement http caching headers yet
            //5 minutes expiry time by default
            $expiry = '+300';
            if ($react->hostMetaXrd && $react->hostMetaXrd->expires > time()) {
                $expiry = $react->hostMetaXrd->expires;
            }
            $this->cache->save($cacheId, $data, $expiry);
        } else {
            //load from cache
            $react->hostMetaXrd = $cacheRetval['hostMetaXrd'];
            $react->error       = $cacheRetval['error'];
            $react->secure      = $cacheRetval['secure'];
            $retval             = $cacheRetval['retval'];
        }

        return $retval;
    }

    /**
     * Load the host's .well-known/host-meta XRD file.
     *
     * The XRD is stored in the reaction object's $hostMetaXrd property,
     * and any error that is encountered in its $error property.
     *
     * When the XRD file cannot be loaded, this method returns false.
     *
     * @param object $react Reaction object to fill
     * @param string $host  Hostname to fetch host-meta file from
     *
     * @return boolean True if the host-meta file could be loaded
     *
     * @see Net_WebFinger_Reaction::$hostMetaXrd
     * @see Net_WebFinger_Reaction::$error
     */
    protected function loadHostMeta(Net_WebFinger_Reaction $react, $host)
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
        $react->secure = true;

        $xrd = $this->loadXrd('https://' . $host . '/.well-known/host-meta', $react);
        if (!$xrd) {
            $xrd = $this->loadXrd(
                'http://' . $host . '/.well-known/host-meta', $react
            );
            //no https, so not secure
            //TODO: XML signature verification once supported by XML_XRD
            $react->secure = false;
            if (!$xrd) {
                $react->error = new Net_WebFinger_Error(
                    'No .well-known/host-meta for ' . $host,
                    Net_WebFinger_Error::NO_HOSTMETA,
                    $react->error
                );
                return false;
            }
        }
        $react->hostMetaXrd = $xrd;

        return true;
    }

    /**
     * Loads the user XRD file for a given identifier
     *
     * The XRD is stored in the reaction object's $userXrd property,
     * any error is stored in its $error property.
     *
     * When loading of the file fails, false is returned.
     *
     * @param object $react      Reaction object to fill
     * @param string $identifier E-mail address like identifier ("user@host")
     * @param string $host       Hostname of $identifier
     * @param object $hostMeta   host-meta XRD object
     *
     * @return boolean True when the user XRD could be loaded, false if not
     *
     * @see Net_WebFinger_Reaction::$hostMetaXrd
     * @see Net_WebFinger_Reaction::$error
     */
    protected function loadLrdd(
        Net_WebFinger_Reaction $react, $identifier, $host, XML_XRD $hostMeta
    ) {
        $link = $hostMeta->get('lrdd', 'application/xrd+xml');
        if ($link === null || !$link->template) {
            $react->error = new Net_WebFinger_Error(
                'No lrdd link in host-meta for ' . $host,
                Net_WebFinger_Error::NO_LRDD_LINK,
                $react->error
            );
            return false;
        }

        $account = 'acct:' . $identifier;
        $userUrl = str_replace('{uri}', urlencode($account), $link->template);

        $react->userXrd = $this->loadXrd($userUrl, $react);
        if ($react->userXrd === null && $this->isHttps($userUrl)) {
            //fall back to HTTP
            $userUrl = 'http://' . substr($userUrl, 8);
            $react->userXrd = $this->loadXrd($userUrl, $react);
        }
        if (!$react->userXrd) {
            return false;
        }

        if (!$this->isHttps($userUrl)) {
            $react->secure = false;
            //TODO: XML signature verification once supported by XML_XRD
        }
        if (!$react->userXrd->describes($account)) {
            $react->secure = false;
        }

        return true;
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
            $react->error = $e;
            return null;
        }
    }

}

?>