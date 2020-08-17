<?php

namespace Api\Core\Container;

/**
 *  Auth Class
 *  The class can be used as a container service
 *  which shares the information about all the auth data
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class Auth {

    // for instance this framework currently only supports HTTP_AUTHORIZATION and Bearer Token
    private const AUTH_HEADER_NAME = 'HTTP_AUTHORIZATION';
    private const AUTH_HEADER_TYPE = 'Bearer';

    // Common Salt
    private const HASH_SECRET = "NaCl";
    
    private static $container;
    private static $userID;
    private static $sessionID;
    private static $hashString;
    private static $expiry;


    /**
     * Constructor
     * 
     * @param Pimple\Container $container
     * 
     * @return void
     */ 
    public function __construct($container)
    {
        self::$container = $container; 
    }


    /**
     * to check whether a valid session available in the particular request
     * 
     * - if valid session, then update the session
     * 
     * @param Request $request
     * 
     * @return bool
     */ 
    public static function hasSession($request)
    {
        if (self::isValidSession($request)) {
            self::updateSession($request, self::$userID);
            return true;
        }
        return false;
    }


    /**
     * get userID of current session
     * 
     * @return int $userID
     */ 
    public static function getUserID() :int
    {
        return (int)self::$userID;
    }


    /**
     * get sessionID of current session
     * 
     * @return int $sessionID
     */ 
    public static function getSessionID() :int
    {
        return (int)self::$sessionID;
    }


    /**
     * get expiry time of the current session
     * 
     * @return unix.timestamp $expiry
     */ 
    public static function getExpiry() :int
    {
        return (int)self::$expiry;
    }


    /**
     * __toString() of the sesssion
     * 
     * @return array $session
     */ 
    public static function getSession()
    {
        return array(
            "session_id" => self::getSessionID(),
            "customer_id" => self::getUserID(),
            "token" => self::$hashString,
            "expiry" => self::getExpiry()
        );
    }


    /**
     * to hash a string with password hashing logic of the application
     * 
     * @param string $password to hash
     * 
     * @return string $hashedPassword
     */ 
    public static function hashPswd($password)
    {
        return hash_hmac("sha256", $password, self::HASH_SECRET);
    } 


    /**
     * to check the request contains a valid session
     * 
     *  - check valid auth header
     *  - check valid token type
     *  - check valid token with specified format
     *      eg: <customerId:int>|<sessionID:int>|<hashToken:string(64)>
     *  - check the token exists in Cache DB
     *  - check if both matches
     * 
     * @param Request $request
     * 
     * @return bool
     */ 
    public static function isValidSession($request)
    {
        $redis = self::$container['redis'];
        if ($request->hasHeader(self::AUTH_HEADER_NAME)) {
            if ($authHeader = $request->getHeader(self::AUTH_HEADER_NAME)) {
                $authData = explode(self::AUTH_HEADER_TYPE, $authHeader);
                
                $bearerToken = $authData[1] ?? "";
                $bearerToken = trim($bearerToken);
                
                if ($bearerToken) {
                    if (preg_match("/^(\d+)\|(\d+)\|([a-z0-9]{64})$/", $bearerToken, $matches)) {
                        list($bt, $userID, $sessionID, $hashString) = $matches;

                        if ($redis->exists(RK_USER . $userID)) {

                            $sessToken = $redis->get(RK_USER . $userID);
                            if (trim($sessToken) === $bearerToken) {
                                self::$userID = $userID; 
                                self::$sessionID = $sessionID;
                                self::$hashString = $hashString;

                                return true;
                            }
                            
                        }
                        
                    }
                }
            }
        }
        return false;
    }
    

    /**
     * to update session expiry time in redis and DB
     * - redis session key is primarily validated to check the session
     * 
     * @param Request $request
     * @param int $userID
     * 
     * @return void
     */ 
    public static function updateSession($request, $userID)
    {
        self::updateSessionCacheDB($userID);
        self::updateSessionDB();
    }

        
    /**
     * to update session expiry time in redis
     * 
     * @param int $userID
     * 
     * @return void
     */ 
    public static function updateSessionCacheDB($userID)
    {
        $redis = self::$container['redis'];
        $redis->expire(RK_USER . $userID, SESSION_TIMEOUT);
    }


    /**
     * to update session expiry time in DB
     * 
     * changing expiry time may not make any changes in application workflow, 
     * if redis is pramarily using to check the session
     * backward compatability for the API's 
     * 
     * @return bool
     */ 
    private static function updateSessionDB()
    {
        $db = self::$container['db'];
        $query = "UPDATE `website_sessions` SET  `website_session_expiry` = ? WHERE `website_session_id` = ?";

        $sessionTimeout = SESSION_TIMEOUT;
        $sessionExpiry = strtotime("+$sessionTimeout second");
        self::$expiry = $sessionExpiry;

        $stmt = $db->prepare($query);
        return $stmt->execute([$sessionExpiry, self::$sessionID]);
    } 


    /**
     * create a new session 
     * 
     * @param int $userID
     * 
     * @return bool
     */ 
    public static function createSession($userID)
    {
        $db = self::$container['db'];

        $sessionTimeout = SESSION_TIMEOUT;
        $sessionExpiry = strtotime("+$sessionTimeout second");
        
        self::$expiry = $sessionExpiry;

        self::$hashString = hash_hmac("sha256", $userID.$sessionExpiry, self::HASH_SECRET);

        $query = "INSERT INTO `website_sessions` (
            `website_session_hash`,
            `website_session_created_on`, 
            `website_session_expiry`, 
            `customer_id`)
            VALUES(:hash, NOW(), :expireOn, :userID)";

        $stmt = $db->prepare($query);
        $stmt->execute(array(
            "hash" => self::$hashString,
            "expireOn" => $sessionExpiry,
            "userID" => $userID
        ));


        self::$userID = $userID;
        self::$sessionID = $db->lastInsertId();
     
        self::setSessionCacheDB();

        return true;
    }


    /**
     * set session key in redis
     * 
     * @return bool
     */ 
    public static function setSessionCacheDB()
    {
        $redis = self::$container['redis'];

        extract(self::getSession());
        $redis->set(RK_USER . $customer_id, "$customer_id|$session_id|$token");
        $redis->expire(RK_USER . $customer_id, $expiry);
    }
}