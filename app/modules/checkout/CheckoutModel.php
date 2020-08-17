<?php

namespace Api\Modules\Checkout;

/*
 *  Checkout Model Class
 *  Invoked only by Checkout Controller class.
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class CheckoutModel extends \Api\Core\Container\Model implements \Api\Core\Container\DataSignature
{

    public $dataSignature = array();


    /*
     *  Model method to validate the input request
     * 
     *  @param array $data input ($_POST) 
     *  @param array $signature
     * 
     *  @return array
     */ 
    public function validateData(array $data, array $signature) :array
    {
        return $this->validateResult($data, $signature);
    }


    /*
     *  Model method to checkout the order
     * 
     *  @return array $result
     */ 
    public function checkout()
    {
        $authService = $this->auth;
        $customerID = $authService::getUserID();
        $data = $this->getData();

        $result = array();
        $this->transaction("begin");
        
        // create order
        $orderID = $this->createCustomerOrder($data, $customerID);
        if (!$orderID) {
            $this->transaction("rollback");
            return $result;
        }

        // get cart summary
        if (!$cartSummary = $this->getCartSummary($customerID)) {
            $this->transaction("rollback");
            return $result;
        }
        // save cart items to order
        foreach ($cartSummary as $row) {
            if (!$this->saveOrderItems($row, $orderID)) {
                $this->transaction("rollback");
                return $result;
            }
        }

        // save payment details
        if (!$paymentID = $this->saveOrderPayment($data, $orderID)) {
            $this->transaction("rollback");
            return $result;
        }

        // save payment method details
        if (!$this->savePaymentMethodDetails($data, $paymentID)) {
            $this->transaction("rollback");
            return $result;
        }

        // clear the cart
        if (!$this->clearCart($customerID)) {
            $this->transaction("rollback");
            return $result;
        }

        // validate coupon code if any
        if ($data["orderCouponApplied"]) {
            // get coupon ID
            if (!$couponID = $this->getCouponID($data["orderCouponApplied"])) {
                $this->transaction("rollback");
                return $result;
            }

            // save the coupon usage
            if (!$this->logCouponUsage($customerID, $orderID, $couponID)) {
                $this->transaction("rollback");
                return $result;
            }
        } 

        $this->transaction("commit");

        return $this->validateResult(array("order_no" => $orderID, "order_status" => 1), $this->dataSignature);
    }


    /*
     *  Method to create a new order
     *  
     *  @param array $data POST array
     *  @param int $customerID
     * 
     *  @return int $orderID
     */
    private function createCustomerOrder(array $data, int $customerID) :int
    {
        $query = "INSERT INTO customer_orders(
                      customer_order_price,
                      customer_order_discount,
                      customer_order_coupon_code,
                      customer_order_shipping_name,
                      customer_order_shipping_address,
                      customer_order_shipping_zipcode,
                      customer_order_shipping_country_code,
                      customer_order_shipping_phone,
                      customer_id
                  ) VALUES (
                      :order_price,
                      :order_discount,
                      :order_coupon_code,
                      :order_shipping_name,
                      :order_shipping_address,
                      :order_shipping_zipcode,
                      :order_shipping_country_code,
                      :order_shipping_phone,
                      :customer_id
                  )";

        $insData = array(
            "order_price" => $data["orderTotal"],
            "order_discount" => $data["orderDiscount"],
            "order_coupon_code" => $data["orderCouponApplied"],
            "order_shipping_name" => $data["customerName"],
            "order_shipping_address" => $data["customerAddress"],
            "order_shipping_zipcode" => $data["customerZip"],
            "order_shipping_country_code" => $data["customerCountry"],
            "order_shipping_phone" => $data["customerPhone"],
            "customer_id" => $customerID
        );

        if (!$stmt = $this->query($query, $insData)) {
            $this->logger::warning("Query failed while trying to create (INSERT) an order by the customer ($customerID)");
            $this->setError("QUERY_FAILED");
            return 0;
        }

        return $this->getInsertId();
    }


    /*
     *  Method to get cart summary
     *  
     *  @param int $customerID
     * 
     *  @return array $result
     */
    private function getCartSummary(int $customerID) :array
    {
        $result = array();
        $query = "SELECT cci.product_id, SUM(customer_cart_item_count) as item_count, product_rate
		          FROM customer_carts cc
		          JOIN customer_cart_items cci ON (cc.customer_cart_id = cci.customer_cart_id)
		          JOIN products p ON (cci.product_id = p.product_id)
		          WHERE cc.customer_id = :customer_id
                  GROUP BY cci.product_id";
        
        if (!$rs = $this->query($query, array("customer_id" => $customerID))) {
            $this->logger::warning("Query failed while trying to cart summary of the customer ($customerID)");
            $this->setError("QUERY_FAILED");
            return $result;
        }

        $result = $rs->fetchAll(\PDO::FETCH_ASSOC);

        return $result;
    }


    /*
     *  Method to save order items
     *  
     *  @param array $data
     *  @param int $orderID
     * 
     *  @return bool
     */
    private function saveOrderItems(array $data, int $orderID) :bool
    {
        extract($data);

        $query = "INSERT INTO customer_order_items(
			      customer_order_item_count,
			      customer_order_item_price,
			      customer_order_id,
			      product_id
			      ) VALUES (
			      	:item_count,
                    :product_rate,
                    :order_id,
                    :product_id
                  )";

        if (!$this->query($query, array(
            "item_count" => $item_count,
            "product_rate" => $product_rate,
            "order_id" => $orderID,
            "product_id" => $product_id
        ))) {
            $this->logger::warning("Query failed while trying to add (INSERT) cart items to order items of order ($orderID) and customer ($customerID)");
            $this->setError("QUERY_FAILED");
            return false;
        }

        return true;
    }


    /*
     *  Method to save the order payment
     *  
     *  @param array $data
     *  @param int $orderID
     * 
     *  @return int $paymentID
     */
    private function saveOrderPayment(array $data, int $orderID) :int
    {
        $orderTotal = $data["orderTotal"];

        $query = "INSERT INTO customer_order_payments(
                      customer_order_payment_total,
                      customer_order_id
                  ) VALUES (
                      :order_total,
                      :order_id
                  )";
        
        if (!$this->query($query, array("order_total" => $orderTotal, "order_id" => $orderID))) {
            $this->logger::warning("Query failed while trying to add (INSERT) order payment of order ($orderID)");
            $this->setError("QUERY_FAILED");
            return 0;
        }

        return $this->getInsertId();
    }

     
    /*
     *  Method to save the order payment method details
     *  
     *  @param array $data
     *  @param int $paymentID
     * 
     *  @return bool
     */
    private function savePaymentMethodDetails(array $data, int $paymentID) :bool
    {
        extract($data);

        $query = "INSERT INTO customer_payment_card_details(
                      customer_payment_card_detail_type,
                      customer_payment_card_detail_name,
                      customer_payment_card_detail_ending,
                      customer_payment_card_detail_transaction_id,
                      customer_order_payment_id
                  ) VALUES (
                      :card_type,
                      :card_name,
                      :card_ending,
                      :card_trans_id,
                      :payment_id
                  )";

        if (!$this->query($query, array(
                                        "card_type" => $paymentCardType, 
                                        "card_name" => $paymentCardName,
                                        "card_ending" => substr($paymentCardNumber, -4),
                                        "card_trans_id" => 'A1B2C3D4E5',
                                        "payment_id" => $paymentID,
                                        ))) {
            $this->logger::warning("Query failed while trying to add (INSERT) order payment method of order ($orderID)");
            $this->setError("QUERY_FAILED");
            return false;
        }

        return true;
    }

     
    /*
     *  Method to clear the cart of the customer
     *  delete customer_carts and customer_cart_items (FK:delete_cascade)
     *  
     *  @param int $customerID
     * 
     *  @return bool
     */
    private function clearCart(int $customerID) :bool
    {
        $query = "DELETE FROM customer_carts WHERE customer_id = :customer_id";
                
        if (!$this->query($query, array("customer_id" => $customerID))) {
            $this->logger::warning("Query failed while trying clear the cart of the customer ($customerID)");
            $this->setError("QUERY_FAILED");
            return false;
        }

        return true;
    }

    /*
     *  Method to get the coupon ID of a coupon code
     *  return 0 if the coupon code is wrong or inactive
     *  
     *  @param string $couponCode
     * 
     *  @return int $couponID
     */
    private function getCouponID(string $couponCode) :int
    {
        $query = "SELECT coupon_id FROM coupons WHERE coupon_code = :coupon_code";

        if (!$rs = $this->query($query, array("coupon_code" => $couponCode))) {
            $this->logger::warning("Query failed while trying to select coupon id of the coupon code ($couponCode)");
            $this->setError("QUERY_FAILED");
            return 0;
        }

        if (!$ret = $rs->fetch(\PDO::FETCH_ASSOC)) {
            $this->logger::warning("Query failed while trying to select coupon id of the coupon code ($couponCode)");
            $this->setError("NOT_ACTIVE_COUPON");
            return 0;
        }

        return $ret["coupon_id"];
    }

    /*
     *  Method to log coupon usage in an order by a customer
     *  
     *  @param int $customerID
     *  @param int $orderID
     *  @param int $couponID
     * 
     *  @return bool
     */
    private function logCouponUsage(int $customerID, int $orderID, int $couponID) :bool
    {
        $couponTimeout = COUPON_CODE_TIMEOUT;
        $couponExpireOn = strtotime("+$couponTimeout second");

        $query = "INSERT INTO customer_coupon_log(
		         	customer_id,
		         	coupon_id,
		         	customer_order_id,
		         	customer_coupon_expire_on
		         ) VALUES (
		         	:customer_id,
                    :coupon_id,
                    :order_id,
                    :expire_on
                 )";
        
        if (!$this->query($query, array(
            "customer_id" => $customerID,
            "coupon_id" => $couponID,
            "order_id" => $orderID,
            "expire_on" => $couponExpireOn
        ))) {
            $this->logger::warning("Query failed while trying to add (INSERT) coupon log of the coupon ($couponID) for the order ($orderID) by the customer ($customerID)");
            $this->setError("QUERY_FAILED");
            return false;
        }

        $this->redis->set(CUSTOMER_APPLIED_COUPON . "$customerID:$couponID", $orderID);
        $this->redis->expire(CUSTOMER_APPLIED_COUPON . "$customerID:$couponID", COUPON_CODE_TIMEOUT);

        return true;
    }

    /*
     *  Set data signature
     * 
     *  @return void
     */
    public function setDataSignature() 
    {
        $this->dataSignature = array(
            "order_no"     => array("required" => 1, "type" => "int"),
            "order_status" => array("required" => 1, "type" => "int"),
        );
    }
}