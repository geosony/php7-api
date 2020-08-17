<?php

namespace Api\Modules\Fruits;

/*
 *  Fruits Class
 *  Invoked only for loading fruits
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class Fruits extends \Api\Core\Container\Controller {

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
        $this->model = new \Api\Modules\Fruits\FruitsModel($this->getFilteredData(), $this->container);
        $this->model->setDataSignature();
    }

    /*
     *  Default method to get all fruits
     *  
     *  @uri: /fruits
     *  @method: GET
     *  @media-type: application/json
     * 
     *  @render array $payload
     */
    public function index() 
    {
        $fruits = $this->model->getAll();
        $this->setData("payload", $fruits);
        $this->render();
    }


    /*
     *  Endpoint to get a specific fruit
     *  
     *  @uri: /fruits/{fruitId}
     *  @method: GET
     *  @media-type: application/json
     * 
     *  @render array $payload
     */
    public function getById() 
    {
        $fruitId = $this->args["fruitId"] ?? 0;

        if (!$fruitId) {
            $this->setError(": INVALID_REQUEST");
        }

        $fruit = $this->model->getById($fruitId);
        $this->setData("payload", $fruit);
        $this->render();
    }


    /*
     *  Method to get available stock of a fruit
     * 
     *  @param int $fruitId 
     * 
     *  @render array $fruit
     */
    public function getFruitSummary(int $fruitId) 
    {
        $fruit = $this->model->getFruitSummary($fruitId);
        return $fruit;
    }

}