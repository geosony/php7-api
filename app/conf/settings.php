<?php

//=================================================================================
                            # Defined constants    
//=================================================================================

// default namespace
define('BASE_NS', 'Api');

// Application directory name in the document root
define('DOC_ROOT_DIR', get_cfg_var("api.path.docroot"));

// HTTP Authorization Type
define('AUTH_TYPE', get_cfg_var("api.auth.type"));
define('AUTH_API_KEY', get_cfg_var("api.auth.key")); // only if auth type is Api Key

// HTTP Authorization Type
define('DEFAULT_RENDER_FORMAT', get_cfg_var("api.render.format"));

// LOG Path
define('LOG_PATH', get_cfg_var("api.path.log"));

//CORS_ORIGIN_HEADER
define('CORS_ORIGIN_HEADER', get_cfg_var("api.http.protocol") . '://' . get_cfg_var("api.http.origin"));

//SESSION_TIMEOUT
define('SESSION_TIMEOUT', 1800);

//SESSION_TIMEOUT
define('COUPON_CODE_TIMEOUT', get_cfg_var("api.extras.couponexpiry"));

//=================================================================================
                            # REDIS Keys    
//=================================================================================

define('RK_USER', 'user:');
define('CUSTOMER_APPLIED_COUPON', 'user_coupon:'); // get key; user_coupon:<customer_id>:<coupon_id>


//=================================================================================
                            # Utility functions    
//=================================================================================

/*
 *  Developer method to debug varibles, objects,..
 *  this method will terminates the execution by default
 *  and can be overrided by passing @param $e to 0
 * 
 *  @param mixed $var the variable to output/print
 *  @param int $e default 1 will exit execution;
 * 
 *  @render output
 */
function stop($var, int $e=1) {
    echo "<pre>";
    print_r($var);
    echo "</pre>";
    if ($e) exit;
}

/*  
 *  check if array is associative or not
 * 
 *  @param array $arr the array to check
 * 
 *  @return bool 
 */
function isAssoc(array $arr)
{
    if (array() === $arr) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}

/*
 *  Default output method accross the application; if not render application called 
 * 
 *  @param array $input one dimenstional array with code and meesage at 0 and 1
 *  
 *  @render in the default format
 */
function default_output(array $input) {

    if (!is_array($input)) {
        $input = array(422, "UNKNOWN_ERROR");
    }

    $data = array("error" => array());
    $data["error"]["code"] = $input[1];

    $code = ($input[0]) ? $input[0] : 422;

    header("HTTP/1.1 $code Unprocessable Entity");

    $flipped_data = array(true => "error");
    $flipped_data[$input[1]] = "code";

    switch(DEFAULT_RENDER_FORMAT) {
        case 'xml':
            $xml = new SimpleXMLElement('<root/>');
            array_walk_recursive($flipped_data, array ($xml, 'addChild'));
            print $xml->asXML();
            break;
        default:
            print json_encode($data); 
            break;
    }
    exit;
}

//=================================================================================