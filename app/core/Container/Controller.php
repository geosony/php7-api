<?php

namespace Api\Core\Container;


/**
 *  Controller Class
 *  The class is the parent class for all the controller classes in each module
 * 
 *  the main $router glue is injected in this class to obtain essential exchange methodologies 
 *  between the request and response. The main functionalities of this class includes the following:
 * 
 *  - set the essential objects like services, request, responses, auth, authData, args and data
 *  - parse filter and store the input data that transferred by any request method
 *  - set data to render or send response
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class Controller {

    private $router;
    private $rawData;
    protected $request;
    protected $response;
    protected $routeDetails;
    protected $auth = false;
    protected $authData = array();
    protected $args;
    protected $data;
    protected $container;

    private $responseBody = array(
        "Info" => "",
        "Version" => "",
        "Payload" => array(),
        "Session" => array()
    );


    /**
     * Constructor
     * sets all the essential bindings for controllers
     * 
     * @param Router $router
     * 
     * @return void
     */ 
    public function __construct($router) 
    {
        $this->router = $router;
        $this->container = $router->container;
        $this->request = $router->request;
        $this->response = $router->response;
        $this->rawData = $router->getRawData();
        $this->args = $router->currentRoute["args"] ?? array();
        $this->auth = $router->currentRoute["auth"] ?? false;
        $this->routeDetails = $router->currentRoute[$this->request->getMethod()];

        $this->checkSession();
    }


    /**
     *  Helper method for the controller to check an active session exist
     *  invokes the auth service to check 
     * 
     *  @render INVALID_SESSION if no active session
     */
    private function checkSession()
    {
        $auth = $this->container['auth'];
        if ($auth::hasSession($this->request)) {
            $this->authData = $auth::getSession();
        }
        
        if ($this->auth) {
            if (!$this->authData) {
                $this->setError("INVALID_SESSION");
                $this->render();
                exit;
            }
        }
    }


    /**
     * Get filtered input data in any request method
     * 
     * @return array $filteredData
     */ 
    protected function getFilteredData() : array
    {
        $rawData = $this->rawData;

        if (!is_array($rawData) || !$rawData) {
            return array();
        }

        return $this->filterData($rawData);
    }


    /**
     * reccursive method to filter each field in the input data
     * 
     * @param array $data unfiltered input data
     * 
     * @return array $data filtered data
     */ 
    private function filterData(array $data) :array
    {
        # TODO:- white-list all inputs using a config for more sanity checking
        
        if (is_array($data)) {
            foreach ($data  as $key => $val) {
                $data[$this->filterData($key)] = $this->filterData($val);
            }
        } elseif (is_object($data)) {
            $data = (array) $data;
            $this->filterData($data);
        } else {
            $data = htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }


    /**
     * set data from controller; data to render
     * 
     * @param string $key 
     * @param string $value 
     * 
     * @return void
     */ 
    protected function setData(string $key, string $value)
    {
        $this->data[$key] = $value;
    }


    /**
     * set data array from controller; data to render
     * 
     * @param array $data 
     * 
     * @return void
     */ 
    protected function setDataArray(array $data)
    {
        foreach ($data as $key => $value) {
            $this->setData($key, $value);
        }
    }


    /**
     * set error from the controller that bind the response object
     * 
     * @param string $code 
     * @param string $reasonPhrase; optional
     * 
     * @return void
     */ 
    protected function setError(string $code, string $reasonPhrase="")
    {
        
        $this->data = array("error" => array("code" => $code));
        if ($reasonPhrase) {
            $this->data["error"]["status"] = $reasonPhrase;
        }
        $this->response = $this->response->withStatus(422, $reasonPhrase);
        $logger = $this->container["logger"];
        $logger::warning($code . " :: $reasonPhrase");
    }


    /**
     * Render output in JSON format
     * 
     * @render response
     */ 
    protected function render()
    {
        if (!$this->data) {
            throw new \Exception("Nothing to send");
        }
        $responseBody = $this->responseBody;
        
        if (!isset($this->data["error"])){

            $responseBody["Info"] = $this->routeDetails["info"] ?? "No information about this endpoint";
            $responseBody["Version"] = $this->routeDetails["version"] ?? "0.0";
            
            if ($this->authData) {
                $responseBody["Session"] = $this->authData;
            }
            
            $responseBody["Payload"] = $this->data["Payload"] ?? $this->data["payload"] ?? $this->data;
            
            $content = $this->safeJsonEncode($responseBody);
        } else {
            $content = json_encode($this->data);
        }

        $this->response->setContent($content);
        $this->response->sendResponse();
    }


    /**
     * JSON encode the array data
     * Safe encoding ensures the errors while encoding array to JSON string
     * 
     * @param array $data
     * 
     * @return string $encoded
     */ 
    private function safeJsonEncode(array $data) :string
    {
        $encoded = json_encode($data);
        $error = json_last_error();
        
        if ($error) {
            switch($error) {
                case JSON_ERROR_UTF8:
                $clean = $this->utf8ize($data);
                return $this->safeJsonEncode($clean);
                break;
                default:
                $this->setError("UNKNOWN_ERROR", "Unknown error while encoding result to JSON");
                $this->render();
                exit;
            }
        }
        return $encoded;
    }


    /**
     * UTF-8 encode a string or array values 
     * 
     * @param array/string $mixed
     * 
     * @return array $mixed
     */ 
    private function utf8ize($mixed) :array
    {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = $this->utf8ize($value);
            }
        } else if (is_string ($mixed)) {
            return utf8_encode($mixed);
        }
        return $mixed;
    }


    /**
     * Load another module from a module
     * 
     * @param string $moduleName
     * 
     * @return object instance of module
     */ 
    public function loadModule(string $moduleName) :object
    {
        $moduleName = ucfirst(strtolower($moduleName));
        $fqmn = "\\Api\\Modules\\$moduleName\\$moduleName";
        return new $fqmn($this->router);
    }


}