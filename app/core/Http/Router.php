<?php

namespace Api\Core\Http;

/**
 *  Router Class
 *  It is the most important class that acts as a glue between a request and response.
 *  All controllers are injected with this class
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class Router {

    private static $routes = array();
    
    public $requestTarget = '/';
    public $currentRoute = array();
    public $request;
    public $response;
    public $container;


    /**
     *  Constructor
     * 
     *  @param array $routes all the routes configured in the API
     *  @param \Pimple\Container $container the container
     * 
     *  @return void
     */
    public function __construct(array $routes,  \Pimple\Container $container) 
    {
        self::$routes = $routes;
        $this->container = $container;
        $this->request = new Request();
        $this->response = new Response();

        $this->requestTarget = $this->stripRequest();

        try {
            $this->currentRoute = $this->getRoute();
        } catch(\Exception $e) {
            echo $e->getMessage();
            exit;
        }

        if (!$this->validateRequestMethod()) {
            throw new \Exception("Route method does not matches!");
            exit;
        }
    }


    /**
     *  get the module name that has been parsed from the router for the request
     * 
     *  @return string $classMethod; the module/Class::method to be instantiated
     */
    public function getModule() 
    {
        return $this->currentRoute[$this->request->getMethod()]["method"];
    }


    /**
     *  Get the parsed input data of the request
     * 
     *  return array $parsedData
     */
    public function getRawData()
    {
        return $this->parseData();
    }


    /**
     *  To parse the request target from the URI
     * 
     *  @return string $requestTarget
     */
    private function stripRequest() :string
    {
        $requestTarget = rtrim($this->request->getRequestTarget(), '/');
        
        // service name 
        if (DOC_ROOT_DIR) {
            $routeArr = explode(DOC_ROOT_DIR, $requestTarget);
            $requestTarget = array_pop($routeArr);
        }
        
        $requestTarget = ($requestTarget) ? $requestTarget : '/';

        return $requestTarget;
    }


    /**
     *  Find the exact route for the request from routes config.
     * 
     *  Exception: if route not found.
     * 
     *  @return array $routeDetail
     */
    private function getRoute() :array
    {
        // to find the custom route with URL parameters
        $findRoute = function() {
            $requestTarget = $this->requestTarget;
            foreach (self::$routes as $route => $routeDetail) {
                $routePattern = str_replace("/", "\/", $route);

                // This Framework will only allow integer values as URL params
                $routePattern = preg_replace("/\{.+?\}/", "(\d+)", $routePattern);
                $regExp = "^$routePattern$";

                if (preg_match("/$regExp/", $requestTarget, $matches)) {
                    // somehow, there will be a match in the pattern;
                    $routeArr = explode('/', $route);
                    $reqTargetArr = explode('/', $requestTarget);

                    // compare and assign the results with pattern and value
                    $combinedRoute = array_combine(array_values($routeArr), array_values($reqTargetArr));

                    // filter the array; remove unwanted
                    $filteredRoute = array_filter($combinedRoute, function($v, $k) {
                        return $k != $v;
                    }, ARRAY_FILTER_USE_BOTH);

                    // remove the cloth {} from the args; purify
                    $args = array();
                    array_walk($filteredRoute, function($v, &$k) use (&$args) {
                        $k = preg_replace("/^\{(.+)\}$/", "$1", $k);
                        $args[$k] = $v;
                    });

                    // if arguments it will be embedded in route detail
                    if ($args) {
                        $routeDetail["args"] = $args;
                    }

                    return $routeDetail;
                }
            }
            throw new \Exception("Route not found!");
        };

        // return the routeDetail of the requested route
        return self::$routes[$this->requestTarget] ?? $findRoute();
    }


    /**
     *  To validate the request method; Request vs RouteConfig
     *  
     *  @return string Request Method
     */
    private function validateRequestMethod() :string
    {
        return $this->currentRoute[$this->request->getMethod()] ?? '';
    }


    /**
     *  To parse the request method and save it to an object which can be accessible from any point
     * 
     *  @return array input array
     */
    private function parseData() :array
    {
        switch ($this->request->getMethod()) {
            case 'GET':
                return $_GET;
            break;
            case 'POST':
            case 'PUT':
            case 'DELETE':
                return $this->parseBody();
            break;
            default:
                return array();
            break;
        }
    }


    /**
     *  If the input is requested in request body; parsed data will be returned
     *  expecting XML, JSON, and $_POST only
     * 
     *  @return array input array
     */
    private function parseBody() :array
    {
        $input = file_get_contents("php://input");

        switch($this->request->getMediaType()) {
            case 'application/json':
                $result = json_decode($input, true);

                if (!is_array($result)) {
                    return array();
                }

                return $result;
            break;
            case 'application/xml':
            case 'text/xml':

                $backup = libxml_disable_entity_loader(true);
                $backup_errors = libxml_use_internal_errors(true);
                $result = simplexml_load_string($input);

                libxml_disable_entity_loader($backup);
                libxml_clear_errors();
                libxml_use_internal_errors($backup_errors);

                if ($result === false) {
                    return array();
                }

                return $result;

            break;
            case 'application/x-www-form-urlencoded':

                parse_str($input, $data);
                return $data;

            break;
            default:
                return ($_POST) ? $_POST : array();
            break;

        }

        return array();
    }
}