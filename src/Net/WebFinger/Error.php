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
 * An error that happened during WebFinger discovery.
 *
 * @category Networking
 * @package  Net_WebFinger
 * @author   Christian Weiske <cweiske@php.net>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL
 * @link     http://pear.php.net/package/Net_WebFinger
 */
class Net_WebFinger_Error extends Exception
{
    /**
     * The .well-known/host-meta file could not be found
     */
    const NO_HOSTMETA = 2342010;

    /**
     * The .well-known/host-meta file does not have a link with rel="lrdd".
     */
    const NO_LRDD_LINK = 2342011;
}

?>