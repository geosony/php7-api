<?php

namespace Api\Modules\Cart;

/*
 *  Cart Class
 *  Invoked only for cart endpoints.
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class Cart extends \Api\Core\Container\Controller {

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
        $this->model = new \Api\Modules\Cart\CartModel($this->getFilteredData(), $this->container);
        $this->model->setDataSignature();
    }


    /*
     *  Default method to get the cart
     *  
     *  @uri: /cart
     *  @method: GET
     *  @media-type: application/json
     * 
     *  @render array $payload
     */
    public function index()
    {
        $cart = $this->model->getCart();
        if ($errorCode = $this->model->getError()) {
            $this->setError(": $errorCode");
        } elseif ($cart) {
            $couponObj = $this->loadModule("coupons");

            $cartID = $cart["cart_id"];
            if ($result = $couponObj->applyCoupon($cartID)) {
                if (isset($result["discount_applied"]) && $result["discount_applied"]) {
                    $cart["cart_discount"] = $result["discount_amt"] ?? 0.0;
                } 
            }
            $this->setData("payload", $cart);
        }
        $this->render();
    }


    /*
     *  Endpoint to add an item the cart
     *  
     *  @uri: /cart
     *  @method: POST
     *  @media-type: application/json
     * 
     *  @render array $payload
     */
    public function addToCart() 
    {
        if (!$this->isValid()) {
            $this->setError(": $this->errorCode");
        } else {

            $logger = $this->container['logger'];

            $fruitObj = $this->loadModule("fruits");
            $data = $this->getFilteredData();

            $fruit = $fruitObj->getFruitSummary((int)$data['ProductId']);

            if ($fruit["product_stock"] < (int)$data["ProductQuantity"]) {
                $logger::warning("Stock not available while adding item to the cart");
                $this->setError(": STOCK_NOT_AVAILABLE");
            } else {
                $res = $this->model->saveItem();
                if ($errorCode = $this->model->getError()) {
                    $this->setError(": $errorCode");
                } elseif ($res) {
                    $this->setData("payload", $res);
                }
            }
        }
        $this->render();
    }


    /*
     *  Endpoint to apply coupon on cart
     *  
     *  @uri: /cart
     *  @method: PUT
     *  @media-type: application/json
     * 
     *  @render array $payload
     */
    public function applyCoupon() 
    {
        $data = $this->getFilteredData();

        if (!$this->isValid()) {
            $this->setError(": $this->errorCode");
        } else {
            $couponObj = $this->loadModule("coupons");
            $cart = array();
            
            if ($result = $couponObj->applyCoupon($cartID, $data["CouponCode"])) {
                if (isset($result["discount_applied"]) && $result["discount_applied"]) {
                    $cart["cart_discount"] = $result["discount_amt"] ?? 0.0;
                }
            }
            
            $newCart = $couponObj->cart;
            $cartTotal = 0.0;
            
            if ($newCart) {
                foreach ($newCart as $row) {
                    $cartTotal += (float)$row["cart_total"];
                }
            }
            
            $cart["cart_total"] = $cartTotal;

            $this->setData("Payload", $cart);
        }
        $this->render();
    }


    /*
     *  Endpoint to an item from cart
     *  
     *  @uri: /cart
     *  @method: DELETE
     *  @media-type: application/json
     * 
     *  @render array $payload
     */
    public function deleteFromCart()
    {
        $cartID = $this->args["cartID"] ?? 0;
        $productID = $this->args["productID"] ?? 0;
        
        if ($cartID && $productID) {
            $res = $this->model->deleteItem($cartID, $productID);

            if ($errorCode = $this->model->getError()) {
                $this->setError(": $errorCode");
            } elseif ($res) {

                $cart = array();
                $cart["cart_id"] = $cartID;

                $couponObj = $this->loadModule("coupons");

                if ($result = $couponObj->applyCoupon($cartID)) {
                    if (isset($result["discount_applied"]) && $result["discount_applied"]) {
                        $cart["cart_discount"] = $result["discount_amt"] ?? 0.0;
                    }
                }

                $newCart = $couponObj->cart;
                $cartItemCount = 0;
                $cartTotal = 0.0;

                if ($newCart) {
                    foreach ($newCart as $row) {
                        $cartItemCount += 1;
                        $cartTotal += (float)$row["cart_total"];
                    }
                }
                $cart["cart_item_count"] = $cartItemCount;
                $cart["cart_total"] = $cartTotal;
                $this->setData("payload", $cart);
            }
        } else {
            $this->setError(": INVALID_REQUEST");
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

        if (isset($data['ProductId'])) {
            if (!$data['ProductId']) {
                $this->errorCode = 'REQUIRED_FIELDS_MISSING';
                $logger::warning("ProductID should not be null");
                return false;
            }

            if (!is_numeric($data['ProductId'])) {
                $this->errorCode = 'INVALID_INPUT';
                $logger::warning("ProductID should not be null");
                return false;
            }
        }

        if (isset($data['ProductQuantity'])) {
            if (!$data['ProductQuantity']) {
                $this->errorCode = 'REQUIRED_FIELDS_MISSING';
                $logger::warning("ProductQuantity should not be null");
                return false;
            }

            if (!is_numeric($data['ProductQuantity'])) {
                $this->errorCode = 'INVALID_INPUT';
                $logger::warning("ProductQuantity should not be null");
                return false;
            }
        }

        if (isset($data['CouponCode'])) {
            if (!$data['CouponCode']) {
                $this->errorCode = 'COUPON_CODE_MISSING';
                $logger::warning("CouponCode should not be null");
                return false;
            }

            if (!preg_match("/^[a-zA-Z0-9]{4,25}$/", $data['CouponCode'])) {
                $this->errorCode = 'INVALID_INPUT';
                $logger::warning("CouponCode should not be null");
                return false;
            }
        }
        
        return true;
    }
}