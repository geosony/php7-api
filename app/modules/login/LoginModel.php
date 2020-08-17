<?php

namespace Api\Modules\Login;

/*
 *  Login Model Class
 *  Invoked only by Login Controller class.
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class LoginModel extends  \Api\Core\Container\Model{

    /*
     *  Model method to check the user exists with correct credentials
     *  and to create a new session
     * 
     *  @return array session
     */ 
    public function handshake()
    {
        $data = $this->getData();
        $authService = $this->auth;
        $data['password'] = $authService::hashPswd($data["password"]);

        $rs = $this->query(
            "SELECT `customer_id` 
             FROM `customers` 
             WHERE `customer_email` = :email AND `customer_password` = :password", 
             $data);

        $ret = $rs->fetch();
        $userID = $ret["customer_id"] ?? "";

        if ($userID) {
            if ($authService::createSession($userID)) {
                $session = array();
                return $authService::getSession();
            }
        } else {
            return false;
        }
    }
}