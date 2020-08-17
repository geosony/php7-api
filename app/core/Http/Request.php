<?php

namespace Api\Core\Http;


/**
 *  Request Class
 *  Following PSR standards
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class Request {

    private $serverVars = array();


    /**
     * Constructor
     *
     * @return void
     */
    public function __construct() {
        foreach ($_SERVER as $key => $value) {
            $this->serverVars[$key] = $value;
        }
    }

    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version.
     */
    public function getProtocolVersion() : string {
        return $this->serverVars['SERVER_PROTOCOL'] ?? 'HTTP/1.0';
    }

    /**
     * Retrieves all message header values.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     * While header names are not case-sensitive, getHeaders() will preserve the
     * exact case in which headers were originally specified.
     *
     * @return string[][] Returns an associative array of the message's headers.
     *     Each key MUST be a header name, and each value MUST be an array of
     *     strings for that header.
     */
    public function getHeaders() : array {
        return array_filter($this->serverVars, function($key) {
            if (preg_match("/^HTTP_.*$/", $key)) {
                return true;
            }
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name Case-insensitive header field name.
     * @return bool Returns true if any header names match the given header
     *     name using a case-insensitive string comparison. Returns false if
     *     no matching header name is found in the message.
     */
    public function hasHeader($name) {
        return isset($this->serverVars[$name]) ?? false;
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $name Case-insensitive header field name.
     * @return string[] An array of string values as provided for the given
     *    header. If the header does not appear in the message, this method MUST
     *    return an empty array.
     */
    public function getHeader($name){
        return $this->serverVars[$name] ?? array();
    }

    /**
     * Retrieves the message's request target.
     *
     * Retrieves the message's request-target either as it will appear (for
     * clients), as it appeared at request (for servers), or as it was
     * specified for the instance (see withRequestTarget()).
     *
     * In most cases, this will be the origin-form of the composed URI,
     * unless a value was provided to the concrete implementation (see
     * withRequestTarget() below).
     *
     * If no URI is available, and no request-target has been specifically
     * provided, this method MUST return the string "/".
     *
     * @return string
     */
    public function getRequestTarget() {
        $requestUri = $this->serverVars['REQUEST_URI'];

        $requestTargetArr = explode("?", $requestUri);

        return $requestTargetArr[0] ?? "/";
    }

    /**
     * Retrieves the HTTP method of the request.
     *
     * @return string Returns the request method.
     */
    public function getMethod() {
        return $this->serverVars['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Retrieves the URI instance.
     *
     * This method MUST return a UriInterface instance.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-4.3
     * @return UriInterface Returns a UriInterface instance
     *     representing the URI of the request.
     */
    public function getUri() {
        return new Uri($this->serverVars);
    }

    /**
     * Retrieves the HTTP Content-Type of the request.
     *
     * @return string Returns the request method.
     */
    public function getMediaType() : string
    {
        return $this->serverVars['CONTENT_TYPE'] ?? '';
    }
}
