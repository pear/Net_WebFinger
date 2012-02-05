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

/**
 * The reaction (=result) of a (web)finger action.
 *
 * Returned by Net_WebFinger::finger().
 *
 * Usage examples:
 *
 * Check if the data have been exchanged in a secure manner:
 * <code>
 * if (!$react->secure) {
 *     die("Be suspicious! Data may not be trusted.\n");
 * }
 * </code>
 *
 * Get the OpenID of the user:
 * <code>
 * if ($react->openid) {
 *     echo 'The user\'s OpenID is ' . $react->openid . "\n";
 * }
 * </code>
 *
 * Other short names are
 *
 * - contacts (Portable Contacts)
 * - hcard
 * - profile
 * - xfn
 *
 * Access any relation by their URL:
 * <code>
 * $foo = $react->get('http://this.is.some.foo/#spec');
 * if ($foo !== null) {
 *     //do something
 * }
 * </code>
 *
 * @category Networking
 * @package  Net_WebFinger
 * @author   Christian Weiske <cweiske@php.net>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL
 * @link     http://pear.php.net/package/Net_WebFinger
 * @see      Net_WebFinger::finger()
 */
class Net_WebFinger_Reaction
{
    /**
     * .well-known/host-meta XRD object
     *
     * @var XML_XRD
     * @see $userXrd
     */
    public $hostMetaXrd;

    /**
     * User LRDD XRD file
     *
     * @var XML_XRD
     * @see $hostMetaXrd
     */
    public $userXrd;

    /**
     * Message describing the error that occured during fingering
     *
     * @var string
     */
    public $error;

    /**
     * If the WebFinger result has been obtained from secure sources.
     *
     * There might be a man-in-the-middle attack if it is not secure.
     *
     * The result is considered secure when the XRD files have been obtained
     * via HTTPS or the files were signed with XML signatures.
     *
     * Also, the XRD files need to have the correct subject (or alias) set.
     *
     * Note: The signatures are not supported yet.
     *
     * @var boolean
     */
    public $secure = true;

    /**
     * Provides short names for common link relations.
     *
     * Keys in this array may be used as class variable.
     *
     * @var  array
     * @link http://code.google.com/p/webfinger/wiki/CommonLinkRelations
     */
    protected static $shortNameMap = array(
        'contacts' => 'http://portablecontacts.net/spec/1.0',
        'hcard'    => 'http://microformats.org/profile/hcard',
        'openid'   => 'http://specs.openid.net/auth/2.0/provider',
        'profile'  => 'http://webfinger.net/rel/profile-page',
        'xfn'      => 'http://gmpg.org/xfn/11',
    );

    /**
     * List of link relations that may be taken from the host-meta XRD file
     * when there is none in the user XRD file.
     *
     * @var array
     */
    protected static $fallbackMap = array(
        'http://specs.openid.net/auth/2.0/provider' => true,
    );

    /**
     * Check if a given short name exists.
     *
     * If it exists, you can use e.g. <code>$react->openid</code> to get
     * the openid URL.
     * Note that this only checks if the short variable name exists, not
     * if the variable has a value.
     *
     * @param string $variable Requested class variable
     *
     * @return boolean True if it exists
     *
     * @see $shortNameMap
     * @see __get()
     */
    public function __isset($variable)
    {
        return isset(self::$shortNameMap[$variable]);
    }

    /**
     * Easy property access to common link relations.
     *
     * @param string $variable Requested class variable
     *
     * @return string URL or NULL if not found
     *
     * @see $shortNameMap
     * @see get()
     * @see __isset()
     */
    public function __get($variable)
    {
        if (!isset(self::$shortNameMap[$variable])) {
            return null;
        }
        return $this->get(self::$shortNameMap[$variable]);
    }

    /**
     * Get the link URL with highest priority for the given relation and type.
     *
     * @param string  $rel          Relation name, e.g.
     *                              "http://microformats.org/profile/hcard"
     * @param string  $type         MIME Type
     * @param boolean $typeFallback When true and no link with the given type
     *                              could be found, the best link without a
     *                              type will be returned
     *
     * @return string URL for that relation, NULL if not found
     *
     * @see getLink()
     */
    public function get($rel, $type = null, $typeFallback = true)
    {
        $link = $this->getLink($rel, $type, $typeFallback);
        if ($link !== null && $link->href) {
            return $link->href;
        }
        return null;
    }

    /**
     * Get the link with highest priority for the given relation and type.
     *
     * @param string  $rel          Relation name, e.g.
     *                              "http://microformats.org/profile/hcard"
     * @param string  $type         MIME Type
     * @param boolean $typeFallback When true and no link with the given type
     *                              could be found, the best link without a
     *                              type will be returned
     *
     * @return XML_XRD_Element_Link Link object or NULL if none found
     *
     * @see get()
     * @see $userXrd
     * @see $hostMetaXrd
     */
    public function getLink($rel, $type = null, $typeFallback = true)
    {
        if ($this->userXrd !== null) {
            $link = $this->userXrd->get($rel, $type, $typeFallback);
            if ($link !== null) {
                return $link;
            }
        }

        if ($this->hostMetaXrd === null || !isset(self::$fallbackMap[$rel])) {
            return null;
        }
        return $this->hostMetaXrd->get($rel, $type, $typeFallback);
    }
}

?>