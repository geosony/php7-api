<?php

namespace Api\Modules\Login;

/*
 *  Login Class
 *  Invoked only for login purpose.
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class Login extends \Api\Core\Container\Controller {

    // default error code
    private $errorCode = "INVALID_REQUEST";
    private $statusText = "";

    /*
     *  Constructor
     * 
     *  @param array $router
     * 
     * @return void
     */ 
    public function __construct($router)
    {
        parent::__construct($router);
        $this->model = new \Api\Modules\Login\LoginModel($this->getFilteredData(), $this->container);
    }

    /*
     *  Default method to submit the login
     *  
     *  @uri: /auth
     *  @method: POST
     *  @media-type: application/json
     *  @body: {email:<email>, password:<password>} 
     * 
     *  @render Session $session
     */
    public function index()
    {
        if (!$this->isValid()) {
            $this->setError(": $this->errorCode", $this->statusText);
        } else {
            $data = $this->model->handshake();

            if (!$data) {
                $this->setError(": USER_DOES_NOT_EXISTS");
            } else {
                $this->auth = true;
                $this->setDataArray($data);
            }
        }
        $this->render();
    }

    /*
     *  Method to check the input is valid
     * 
     *  @return bool
     */
    private function isValid()
    {
        $data = $this->getFilteredData();

        $logger = $this->container['logger'];

        if (!isset($data['email']) || !isset($data['password'])) {
            $logger::warning("Email/Password is not in the input for login action");
            return false;
        }

        if (!$data['email'] || !$data['password']) {
            $logger::warning("Required fields are missing");
            $this->errorCode = 'REQUIRED_FIELDS_MISSING';
            return false;
        }

        if (!preg_match("/^[-0-9a-zA-Z_\\.]{1,64}\\@[-a-zA-Z_\\.]+\\.[a-zA-Z]{2,5}$/", $data['email'])) {
            $logger::warning("Invalid email for login action");
            $this->errorCode = 'INVALID_EMAIL';
            return false;
        }

        if (!preg_match("/^^.{6,20}$/", $data['email'])) {
            $logger::warning("Invalid password for login action");
            $this->errorCode = 'INVALID_PASSWORD';
            return false;
        }

        return true;
    }
}