<?php
/**
 * The reaction (=result) of a (web)finger action.
 */
class Net_WebFinger_Reaction
{
    /**
     * .well-known/host-meta XRD object
     *
     * @var XML_XRD
     */
    public $hostMetaXrd;

    /**
     * User LRDD XRD file
     *
     * @var XML_XRD
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
     * There might be a man-in-the-middle attack if it is not secure.
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
     * Easy property access to common link relations.
     *
     * @param string $variable Requested class variable
     *
     * @return string URL or NULL if not found
     */
    public function __get($variable)
    {
        if (!isset(self::$shortNameMap[$variable])) {
            return null;
        }
        return $this->get(self::$shortNameMap[$variable]);
        //FIXME: implement isset()
    }

    /**
     * Get the link URL with highest priority for the given relation and type.
     *
     * @param string  $rel          Relation name
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
     * @param string  $rel          Relation name
     * @param string  $type         MIME Type
     * @param boolean $typeFallback When true and no link with the given type
     *                              could be found, the best link without a
     *                              type will be returned
     *
     * @return XML_XRD_Element_Link Link object or NULL if none found
     *
     * @see get()
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