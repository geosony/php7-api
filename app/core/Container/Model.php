<?php

namespace Api\Core\Container;


/*
 *  Model Class
 *  The class is the parent class for all the model classes in each module
 * 
 *  input data and container service is a mandatory arguments to initiate the model base class
 *  the class has some shortcut apis for PDO class.
 * 
 *  - Services integrated; including logger, auth and redis
 *  - Quick APIS for PDO 
 *  - get $input data 
 *  - validate method the data signature if applied
 *  - get and set error
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class Model {

    protected $db;
    protected $redis;
    protected $logger;
    private $data = array();
    private $error = "";

    /*
     * Constructor
     * 
     * @param array $data input data
     * @param object $container  Pimple container itself
     * 
     * @return void
     */
    public function __construct(array $data, object $container) 
    {
        if ($data) {
            $this->data = $data;
        }

        $this->db = $container["db"];
        $this->redis = $container["redis"];
        $this->logger = $container["logger"];
        $this->auth = $container["auth"];
    }


    /*
     * Get input data
     * 
     * @return array $data
     */ 
    protected function getData() :array
    {
        return $this->data;
    }


    /*
     *  wrapper for PDO::query method
     *  if data has to be bind with the query it initialy prepare the query statement
     * 
     *  raise exception if query fails
     * 
     *  @param string $query
     *  @param array $data values to bind the query place-holders; // optional
     * 
     *  @return object $stmt
     */ 
    protected function query(string $query, array $data=array())
    {
        if (!$query) {
            return false;
        }

        try {
            if ($data) {
                $stmt = $this->db->prepare($query);
                $stmt->execute($data);
            } else {
                $stmt = $this->db->query($query);
            }
            return $stmt;
        } catch(\PDOException $e ) {
            $this->logger::logException($e);
            return false;
        } catch(\Exception $e ) {
            $this->logger::logException($e);
            return false;
        }
    }


    /*
     *  Wrapper function for PDO transactions
     * 
     *  @param string $action // begin, rollback, commit
     * 
     *  @return void
     */ 
    protected function transaction(string $action)
    {
        switch(strtolower($action)) {
            case 'begin':
            $this->db->beginTransaction();
            break;
            case 'rollback':
            $this->db->rollback();
            break;
            case 'commit':
            $this->db->commit();
            break;
            default:
            throw new \Exception("Unknown db transaction!");
            break;
        }
    }


    /*
     *  renamed method to get last insert ID
     * 
     *  @return int auto-increment value
     */
    protected function getInsertId() :int
    {
        return $this->db->lastInsertId();
    }


    /*
     *  get the error code set previously
     * 
     *  @return string $error
     */
    public function getError() :string
    {
        return $this->error;
    }


    /*
     *  set the error
     * 
     *  @param string $code error code
     * 
     *  @return void
     */
    protected function setError(string $code)
    {
        $this->error = $code;
    }


    /*
     *  validate the data signature that set in the model
     *  it validates the data-type and check it required or not 
     *  basically checks in response and sometimes in inputs
     * 
     *  @param array $result 
     *  @param array $signature
     * 
     *  @return array $result // validated 
     */
    protected function validateResult(array $result, array $signature) :array
    {
        if (!$result) {
            throw new \Exception("NO_RECORDS_FOUND");
        }

        if (!$signature) {
            throw new \Exception("EMPTY_DATA_SIGNATURE");
        }

        foreach ($signature as $field => $checks) {

            if (is_array($checks)) {
                if (isset($result[$field]) && is_array($result[$field])) {
                    $this->validateResult($result[$field], $checks);
                }
            } elseif ($checks["required"]) {
                if (!isset($result[$field])) {
                    $this->logger::warning("Required field $field not found!");
                    throw new \Exception("INVALID_RESPONSE");
                }
            }

            if (!isset($result[$field])) {
                continue;
            }
            
            $feildType = $checks["type"];
            
            switch ($feildType) {

                case "int":
                if (!is_numeric($result[$field])) {
                    $this->logger::warning("$field expected to be type $feildType");
                    throw new \Exception("INVALID_RESPONSE");
                }
                break;
                case "float":
                if (!is_float($result[$field] + 0)) {
                    $this->logger::warning("$field expected to be type $feildType");
                    throw new \Exception("INVALID_RESPONSE");
                }
                break;
                default:
                if (!is_string($result[$field])) {
                    $this->logger::warning("$field expected to be type $feildType");
                    throw new \Exception("INVALID_RESPONSE");
                }
                break;
            }
        }

        return $result;
    }
}