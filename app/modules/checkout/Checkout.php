<?php

namespace Api\Modules\Checkout;

/*
 *  Checkout Class
 *  Invoked only for Checkout endpoints.
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class Checkout extends \Api\Core\Container\Controller {

    // default error code
    private $errorCode = "INVALID_REQUEST";
    private $statusText = "";

    /*
     *  Constructor
     * 
     *  @param array $router
     * 
     *  @return void
     */ 
    public function __construct($router)
    {
        parent::__construct($router);
        $this->model = new \Api\Modules\Checkout\CheckoutModel($this->getFilteredData(), $this->container);
        $this->model->setDataSignature();
    }

    /*
     *  Endpoint to checkout the order
     *  
     *  @uri: /checkout
     *  @method: POST
     *  @media-type: application/json
     * 
     *  @render array $payload
     */
    public function index()
    {
        if (!$this->isValid()) {
            $this->setError(": $this->errorCode", $this->statusText);
        } else {
            $data = $this->model->checkout();
            if ($errorCode = $this->model->getError()) {
                $this->setError(": $errorCode");
            } elseif ($data) {
                $this->setData("orderId", $data);
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

        $requestDataSignature = array(
            "customerName"        => array("required" => 1, "type" => "string"),
            "customerAddress"     => array("required" => 1, "type" => "string"),
            "customerZip"         => array("required" => 1, "type" => "string"),
            "customerCountry"     => array("required" => 1, "type" => "string"),
            "customerPhone"       => array("required" => 1, "type" => "string"),
            "paymentCardType"     => array("required" => 1, "type" => "string"),
            "paymentCardName"     => array("required" => 1, "type" => "string"),
            "paymentCardNumber"   => array("required" => 1, "type" => "int"),
            "paymentCardExpMonth" => array("required" => 1, "type" => "int"),
            "paymentCardExpYear"  => array("required" => 1, "type" => "int"),
            "paymentCardCvv"      => array("required" => 1, "type" => "int"),
            "orderTotal"          => array("required" => 0, "type" => "float" ),
            "orderDiscount"       => array("required" => 0, "type" => "float" ),
            "orderCouponApplied"  => array("required" => 0, "type" => "string")
        );
        
        try {
            $validatedData = $this->model->validateData($data, $requestDataSignature);
        } catch(\Exception $e) {
            $this->errorCode = "INVALID_INPUT";
            return false;
        }

        if (!preg_match("/^[a-zA-Z ]{3,50}$/", $data['customerName'])) {
            $logger::warning("Invalid shipment name for checkout action");
            $this->errorCode = 'INVALID_INPUT_SHIP_NAME';
            return false;
        }

        if (!preg_match("/^[-a-zA-Z0-9(),\\. ]{10,500}$/", $data['customerAddress'])) {
            $logger::warning("Invalid shipment address for checkout action");
            $this->errorCode = 'INVALID_INPUT_SHIP_ADDR';
            return false;
        }

        if (!preg_match("/^[-a-zA-Z0-9() ]{5,10}$/", $data['customerZip'])) {
            $logger::warning("Invalid shipment zip code for checkout action");
            $this->errorCode = 'INVALID_INPUT_SHIP_ZIP';
            return false;
        }

        if (!preg_match("/^[A-Z]{2}$/", $data['customerCountry'])) {
            $logger::warning("Invalid shipment country code for checkout action");
            $this->errorCode = 'INVALID_INPUT_SHIP_CNTRY';
            return false;
        }

        if (!preg_match("/^[0-9-()]{6,15}$/", $data['customerPhone'])) {
            $logger::warning("Invalid shipment phone number for checkout action");
            $this->errorCode = 'INVALID_INPUT_SHIP_PHNO';
            return false;
        }

        if (!preg_match("/^[a-z]{3,12}$/", $data['paymentCardType'])) {
            $logger::warning("Invalid card type for checkout action");
            $this->errorCode = 'INVALID_INPUT_CARD_TYPE';
            return false;
        }
        
        if (!preg_match("/^[a-zA-Z ]{3,50}$/", $data['paymentCardName'])) {
            $logger::warning("Invalid card name for checkout action");
            $this->errorCode = 'INVALID_INPUT_CARD_NAME';
            return false;
        }
        
        if (!preg_match("/^\d{13,20}$/", $data['paymentCardNumber'])) {
            $logger::warning("Invalid card type for checkout action");
            $this->errorCode = 'INVALID_INPUT_CARD_NO';
            return false;
        }
        
        if (!preg_match("/^[0-9]{1,2}$/", $data['paymentCardExpMonth'])) {
            $logger::warning("Invalid card expiry month for checkout action");
            $this->errorCode = 'INVALID_INPUT_CARD_EXP_MM';
            return false;
        }
        
        if (!preg_match("/^[0-9]{2}$/", $data['paymentCardExpYear'])) {
            $logger::warning("Invalid card expiry year for checkout action");
            $this->errorCode = 'INVALID_INPUT_CARD_EXP_YY';
            return false;
        }
        
        if (!preg_match("/^[0-9]{3,4}$/", $data['paymentCardCvv'])) {
            $logger::warning("Invalid card CVV for checkout action");
            $this->errorCode = 'INVALID_INPUT_CARD_EXP_CVV';
            return false;
        }
        
        if (isset($data['orderCouponApplied'])) {

            if (!preg_match("/^[A-Z0-9]{3,20}$/", $data['orderCouponApplied'])) {
                $logger::warning("Invalid coupon code applied checkout action");
                $this->errorCode = 'INVALID_INPUT_ORDER_COUPON_CODE';
                return false;
            }
        }
        return true;
    }

}