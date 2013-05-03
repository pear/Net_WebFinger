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

require_once 'XML/XRD.php';

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
class Net_WebFinger_Reaction extends XML_XRD
{
    /**
     * Message describing the error that occured during fingering.
     *
     * When no error happened, this variable is NULL.
     *
     * A webfinger error object is an exception, so you can use
     * <code>$react->error->getMessage()</code> to get the error.
     *
     * To get the reason for the error, an error object may have an encapsulated
     * exception:
     * <code>
     * if ($react->error->getPrevious()) {
     *     echo 'Reason for this error: '
     *     . $react->error->getPrevious()->getMessage()) . "\n";
     * }
     * </code>
     *
     * @var Net_WebFinger_Error
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
     * @var boolean
     */
    public $secure = true;

    /**
     * URL from which the data have been fetched
     *
     * @var string
     */
    public $url;

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
        $link = $this->get(self::$shortNameMap[$variable]);
        if ($link !== null && $link->href) {
            return $link->href;
        }
        return null;
    }
}

?>