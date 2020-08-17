<?php

namespace Api\Core\Container;

/**
 *  Class to log exceptions, errors and other type of messages to the developer
 *  
 *  this class need to be instantiate with a channel name and a lof file will be created accordingly.
 * 
 *  eg: $logger = new \Api\Core\Container\Logger('api');     // 'api' will be the default channel name
 *      will create a log file /path/to/log/api.error.{MM}.log; if not exists; {MM} current month in integer
 * 
 *  Supported Stream Handlers are 'standard', 'default' and 'file'
 *  ---------------------------------------------------------------
 *   
 *  - 'standard' write log to the PHP's system logger, using the Operating System's system logging mechanism or a file, 
 *     depending on what the error_log configuration directive is set to.
 *  - 'default' or 'file'  This is the default option. The message will be appended to the destination file. ie /path/to/log/api.error.{MM}.log;
 * 
 *  $logger->setStreamHandler('standard'); to set the stream handler to standard
 * 
 *  Supported formatters are 'standard' and 'json'; 'json' will be the default
 *  ---------------------------------------------------------------------------
 * 
 *   -  'standard' will write the log message in plain text format.
 * 
 *  $logger->setLogFormat('standard');  to set the log format to standard
 * 
 *  API's
 *  -----
 * 
 *  - $logger::log($data);  // to log the message in detail
 *    eg: $logger::log(array(
 *                           "type"    => "error",
 *                           "msg"     => "The error message",
 *                           "file"    => __FILE__,
 *                           "line"    => __LINE__,
 *                           "class"   => __CLASS__,    // optionally  __TRAIT__
 *                           "method"  => __METHOD__,
 *                     ));
 * 
 *  - $logger::logException($e);   // $e should be an instance of \Exception
 * 
 *  - $logger::info("The info message");
 * 
 *  - $logger::warning("The warning message");
 * 
 *  - $logger::error("The error message");
 * 
 *  - $logger::critical("The critical message");
 * 
 *  - $logger::debug("The debug message");
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 * 
 */
class Logger {

    private $today;
    private static $logFileName;
    private static $channel = 'api';

    // default message will be written to $logFileName
    private static $messageType = 3;
    // default message format will be json
    private static $logFormat = 'JsonFormat';

    // supported stram handlers
    private static $streamHandlers =  array(
        "default" => 3,
        "file" => 3,
        "standard" => 0
    );

    private static  $authService;
    // supported log formats
    private static $logFormats =  array(
        "json" => "JsonFormat",
        "standard" => "StandardFormat",
    );

    public const LOG_CRITICAL = 'CRITICAL';
    public const LOG_ERROR = 'ERROR';
    public const LOG_WARNING = 'WARNING';
    public const LOG_INFO = 'INFO';
    public const LOG_DEBUG = 'DEBUG';
    public const LOG_EXCEPTION = 'EXCEPTION';


    /**
     *  Constructor
     * 
     *  initialize the log file
     * 
     *  @return void(0)
     */
    public function __construct($container, $channel)
    {
        self::$authService = $container["auth"];
        self::$channel = $channel = ($channel) ? $channel : self::$channel;

        // setup log filename
        $this->today = date('Y-m-d');
        list($yy, $mm, $dd) = explode('-', $this->today);
        self::$logFileName = $logFileName = $channel.".error.$mm.log";

        // get last month
        $dateString = $this->today . " first day of last month";
        $dte = date_create($dateString);
        $lastMonth = $dte->format('m');
        $lastMonthLogFilename = $channel. ".error.$lastMonth.log";

        // clear 30 days old log
        if ($dd == '30') {
            if (file_exists(LOG_PATH . "/$lastMonthLogFilename")) {
                unlink(LOG_PATH . "/$lastMonthLogFilename");
            }
        }

        // create a new log file if not exists
        if (!file_exists(LOG_PATH . "/" . $logFileName)) {
            fopen(LOG_PATH . "/" . $logFileName, "w");
        }
    }


    /**
     *  Override the default handler
     *  
     *  @param string $type type of handler
     * 
     *  @return void(0)
     */
    public static function setStreamHandler(string $type)
    {
        self::$messageType = self::$streamHandlers[$type] ?? self::$streamHandlers['default'];
    }


    /**
     *  Override the default log format
     *  
     *  @param string $type type of handler
     * 
     *  @return void(0)
     */
    public static function setLogFormat(string $format)
    {
        self::$logFormat = self::$logFormats[$format] ?? self::$logFormats['json'];
    }


    /**
     *  Pretty method to log info
     *
     *  @param string $msg message to log
     *  
     *  @return void(0)
     */
    public static function info(string $msg)
    {
        self::log(array("type" => self::LOG_INFO, "msg" => $msg));
    }


    /**
     *  Pretty method to log warning
     *
     *  @param string $msg message to log
     *  
     *  @return void(0)
     */
    public static function warning(string $msg)
    {
        self::log(array("type" => self::LOG_WARNING, "msg" => $msg));
    }


    /**
     *  Pretty method to log error
     *
     *  @param string $msg message to log
     *  
     *  @return void(0)
     */
    public static function error(string $msg)
    {
        self::log(array("type" => self::LOG_ERROR, "msg" => $msg));
    }


    /**
     *  Pretty method to log debug
     *
     *  @param string $msg message to log
     *  
     *  @return void(0)
     */
    public static function debug(string $msg)
    {
        self::log(array("type" => self::LOG_DEBUG, "msg" => $msg));
    }


    /**
     *  Pretty method to log critical
     *
     *  @param string $msg message to log
     *  
     *  @return void(0)
     */
    public static function critical(string $msg)
    {
        self::log(array("type" => self::LOG_CRITICAL, "msg" => $msg));
    }


    /**
     *  General method to log data
     *   
     *  eg: log(array("type" => $logger::LOG_INFO, "msg" => "your log message.."));
     * 
     *  Log Types: LOG_ . INFO | DEBUG | WARNING | ERROR | CRITICAL
     *  Meta Keys: 
     *             "file" => filename (__FILE__),
     *             "class" => Class Name (__CLASS__),
     *             "method" => filename (__METHOD__),
     *             "line" => Line No (__LINE__),
     *   
     *  @param array $data log message
     *  
     *  @return void(0) 
     */
    public static function log(array $data=array())
    {
        $type = ($data["type"] ?? "") ? $data["type"] : self::LOG_WARNING;
        $msg = ($data["msg"] ?? "") ? $data["msg"] : "Invalid logging without message..";

        $curTime = date('h:i:s');

        $messageArr = array(
            "type" => $type,
            "time" => date('Y-m-d') . " $curTime",
            "channel" => strtoupper(self::$channel."_excpt"),
            "msg" => $msg
        );

        $authService = self::$authService;
        
        if ($userID = $authService::getUserID()) {
            $data["user"] = $userID;
        }

        $otherKeys = array("user", "file", "line", "class", "method", "trace");

        foreach ($otherKeys as $key) {
            if (isset($data[$key])) {
                $messageArr[$key] = $data[$key];
            }
        }

        $logFormatMethod = "log".self::$logFormat;

        self::$logFormatMethod($messageArr);
    }


    /**
     *  Handy method to log an exception
     *   
     *  eg: log($exception);
     *   
     *  @param array $data log message
     *  
     *  @return void(0) 
     */
    public static function logError(\Error $e)
    {
        if (!($e instanceof \Error)) {
            self::warning("Incorrent error logged..");
            exit;
        }

        $data["type"] = self::LOG_EXCEPTION;
        $data["msg"] = $e->getMessage();
        $data["file"] = $e->getFile();
        $data["line"] = $e->getLine();
        $data["trace"] = $e->getTrace();

        self::log($data);
    }


    /**
     *  Handy method to log an exception
     *   
     *  eg: log($exception);
     *   
     *  @param array $data log message
     *  
     *  @return void(0) 
     */
    public static function logException(\Exception $e)
    {
        if (!($e instanceof \Exception)) {
            self::warning("Incorrent exception logged..");
            exit;
        }

        $data["type"] = self::LOG_EXCEPTION;
        $data["msg"] = $e->getMessage();
        $data["file"] = $e->getFile();
        $data["line"] = $e->getLine();
        $data["trace"] = $e->getTrace();

        self::log($data);
    }


    /**
     *  Log message in JSON format
     * 
     *  @param array $messageArr prepared message Array 
     * 
     *  @return void(0)
     */
    private static function logJsonFormat(array $messageArr)
    {
        $message = json_encode($messageArr);
        self::write($message);
    }


    /**
     *  Log message in Standard plain text format
     * 
     *  @param array $messageArr prepared message Array 
     * 
     *  @return void(0)
     */
    private static function logStandardFormat(array $messageArr)
    {

        extract($messageArr);

        $fileName = ($file ?? "") ? "FILE - $file :: ": "";
        $className = ($class ?? "") ? "CLASS - $class :: ": "";
        $methodName = ($method ?? "") ? "METHOD - $method :: ": "";
        $lineNo = ($line ?? "") ? "LINE - $line :: " : "";
        $traceStack = ($trace ?? "") ? "\nTRACE - $trace :: " : "";

        $extraMessage = implode("", array($fileName, $className, $methodName, $lineNo));

        $message = "$channel :: $type :: $time :: $msg :: $extraMessage";
        self::write($message);
    }


    /**
     *  Log message to the stream
     * 
     *  @param string $message prepared message string 
     * 
     *  @return void(0)
     */
    private static function write(string $message)
    {
        if (self::$messageType === 0) {
            error_log(PHP_EOL . $message . PHP_EOL, self::$messageType);
        } elseif (self::$messageType === 3) {
            error_log(PHP_EOL . $message . PHP_EOL, self::$messageType, LOG_PATH ."/". self::$logFileName);
        }
    }
}