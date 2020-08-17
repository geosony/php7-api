<?php


namespace Api\Core\Http;

/**
 *  Response Class
 *  Following PSR standards, mostly.
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class Response {

    private $statusCode = 200;

    private $reasonPhrase;

    private $content;

    private $headers = array();

    private $contentType = 'application/json';

    private $originHeader = CORS_ORIGIN_HEADER;

    /**
     * Map of standard HTTP status code/reason phrases
     *
     * @var array
     */
    private static $phrases = array(
        // INFORMATIONAL CODES
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        // SUCCESS CODES
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        // REDIRECTION CODES
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy', // Deprecated
        307 => 'Temporary Redirect',
        // CLIENT ERROR
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        // SERVER ERROR
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        511 => 'Network Authentication Required',
    );


    public function __construct() {
    }

    /**
     * Gets the response status code.
     *
     * The status code is a 3-digit integer result code of the server's attempt
     * to understand and satisfy the request.
     *
     * @return int Status code.
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Return an instance with the specified status code and, optionally, reason phrase.
     *
     * If no reason phrase is specified, implementations MAY choose to default
     * to the RFC 7231 or IANA recommended reason phrase for the response's
     * status code.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated status and reason phrase.
     *
     * @see http://tools.ietf.org/html/rfc7231#section-6
     * @see http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     * @param int $code The 3-digit integer result code to set.
     * @param string $reasonPhrase The reason phrase to use with the
     *     provided status code; if none is provided, implementations MAY
     *     use the defaults as suggested in the HTTP specification.
     * @return static
     * @throws \InvalidArgumentException For invalid status code arguments.
     */
    public function withStatus($code, $reasonPhrase = '')
    {
        if (!preg_match("/\d{3}/", $code)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid status code "%s"; must be an integer between 100 and 599, inclusive',
                (is_scalar($code) ? $code : gettype($code))
            ));
        }

        if ($code < 100 || $code > 600) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid status code "%s"; must be an integer between 100 and 599, inclusive',
                (is_scalar($code) ? $code : gettype($code))
            ));
        }
        $new = clone $this;
        $new->statusCode   = (int) $code;
        $new->reasonPhrase = $reasonPhrase;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getReasonPhrase()
    {
        if (! $this->reasonPhrase
            && isset(self::$phrases[$this->statusCode])
        ) {
            $this->reasonPhrase = self::$phrases[$this->statusCode];
        }

        return $this->reasonPhrase;
    }

    /* method to get content-type / media-type
     *
     * @return string $contenType 
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /* method to set content-type
     * like application/json
     *
     * @return void 
     */
    public function setContentType(string $contenType)
    {
        $this->contentType = $contenType;
    }

    /* method to get content body
     * like json encoded string
     *
     * @return string $content
     */
    public function getContent()
    {
        return $this->content;
    }

    /* method to set content
     * like json encoded string
     * 
     * @return void
     */
    public function setContent(string $content)
    {
        $this->content = $content;
    }

    /* set a valid header
     *
     * @return void
     */
    public function setHeader(string $header)
    {
        $this->headers[] = $header;
    }

    /* set response status header
     *
     * @return void
     */
    public function setStatusHeader()
    {
        $statusHeader = implode(" ", array('HTTP/1.1', $this->getStatusCode(), $this->getReasonPhrase()));
        $this->setHeader($statusHeader);
     }

    /* set response headers defined by CORS spec
     * expecting most of the requests are XMLHttpRequest which will not support multiple origins.
     * 
     * @return void
     */
    public function setCorsHeaders()
    {
        $origin = (trim($this->originHeader)) ? $this->originHeader : '*';

        $this->setHeader("Access-Control-Allow-Origin: $origin");
        $this->setHeader('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        $this->setHeader('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
        $this->setHeader('Access-Control-Max-Age: 600');
        $this->setHeader('Content-Type: ' . $this->getContentType());
     }

    /* set response cache headers
     *
     * @return void
     */
     public function setCacheHeaders()
     {
         // ttl not set
         $ttl = 0;
         $this->setHeader('Expires: '.gmdate('D, d M Y H:i:s', time() + $ttl) . 'GMT');
         $this->setHeader('Cache-Control: no-store');
     }

    /* send the response headers
     *
     * @return void
     */
     public function sendHeaders()
     {
         foreach($this->headers as $header) {
             header($header);
         }
     }

    /* send response
     *
     * @return void
     */
     public function sendResponse()
     {
         // set headers
         $this->setStatusHeader();
         $this->setCorsHeaders();
         $this->setCacheHeaders();

        // send headers
         $this->sendHeaders();

        // TODO:- Redirection

        // send content
        $content = $this->getContent();

        echo $content;
        exit;
     }
}