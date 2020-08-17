<?php

namespace Api\Modules\Coupons;

/*
 *  Coupons Model Class
 *  Invoked only by Coupons Controller class.
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class CouponsModel extends  \Api\Core\Container\Model{

    public $dataSignature = array();

    public const TYPE_CART_RULE = 4;
    

    /*  Get the cart rules existing in the system
     * 
     *  @param int $couponID default 0
     * 
     *  @return array $result
     */ 
    public function getCartRules(int $couponID=0) :array
    {
        $result = array();

        if ($couponID) {
            $where = "cp.coupon_id = $couponID";
        } else {
            $where = "cp.coupon_type_id = " . self::TYPE_CART_RULE;
        }

        $query = "SELECT cp.coupon_id, cart_rule_formula AS rule_formula, cart_rule_params->'$.vars' AS rule_param_vars, 
                  cart_rule_params->'$.checks' AS rule_param_checks
                  FROM coupons cp 
                  JOIN cart_rules cr ON (cp.coupon_id = cr.coupon_id) 
                  WHERE $where AND cart_rule_status = 1";

        if (!$rs = $this->query($query)) {
            $this->logger::warning("Query failed while trying to select the cart rules");
            $this->setError("QUERY_FAILED");
            return $result;
        }
        
        if (!$res = $rs->fetchAll(\PDO::FETCH_ASSOC)) {
            $this->logger::warning("No rows found while trying to select the cart rules");
            $this->setError("NO_ROWS_FOUND");
            return $result;
        } else {
            $result = $res;
        }

        return $result;
    }


    /*
     *  Get coupon ID
     * 
     *  @return int $couponID
     */
    public function getCouponID($code) :int
    {
        $query = "SELECT coupon_id FROM coupons WHERE coupon_code = :code";

        if (!$rs = $this->query($query, array("code" => $code))) {
            $this->logger::warning("Query failed while trying to select the coupon ID");
            $this->setError("QUERY_FAILED");
            return 0;
        }

        $res = $rs->fetch(\PDO::FETCH_ASSOC);

        $result = $this->validateResult($res, $this->dataSignature);
        $couponID = (int)$result['coupon_id'] ?? 0;
        return $couponID;
    }


    /*
     *  Get cart ID of a customer
     * 
     *  @param int $customerID
     * 
     *  @return int $cartID
     */
    public function getCartID(int $customerID) :int
    {
        $query = "SELECT customer_cart_id AS cart_id FROM customer_carts WHERE customer_id = :customer_id";

        if (!$rs = $this->query($query, array("customer_id" => $customerID))) {
            $this->logger::warning("Query failed while trying to select the coupon ID");
            $this->setError("QUERY_FAILED");
            return 0;
        }

        $res = $rs->fetch(\PDO::FETCH_ASSOC);

        $result = $this->validateResult($res, $this->dataSignature);
        $cartID = (int)$result['cart_id'] ?? 0;
        return $cartID;
    }


    /*
     *  Get cart details
     * 
     *  @param int $cartID
     * 
     *  @return array $result
     */
    public function getCartSummary(int $cartID) :array
    {
        $result = array();
        $query = "SELECT cci.product_id AS cart_item_id, product_rate AS cart_item_rate, 
                  SUM(customer_cart_item_count) AS cart_item_count, COALESCE(SUM(customer_cart_item_count * product_rate), 0) as cart_total
		          FROM customer_cart_items  cci
		          LEFT JOIN products p ON (p.product_id = cci.product_id)
		          WHERE customer_cart_id = :cart_id
                  GROUP BY 1";
        
        if (!$rs = $this->query($query, array("cart_id" => $cartID))) {
            $this->logger::warning("Query failed while trying to select cart summary of the cart ($cartID)");
            $this->setError("QUERY_FAILED");
            return $result;
        }

        if (!$res = $rs->fetchAll(\PDO::FETCH_ASSOC)) {
            $this->logger::warning("Query failed while trying to select the cart summary");
            $this->setError("QUERY_FAILED");
            return $result;
        } else {
            foreach ($res as $row) {
                $result[$row["cart_item_id"]] = $this->validateResult($row, $this->dataSignature);
            }
        }

        return $result;
    }


    /*
     *  Set data signature
     * 
     *  @return void
     */
    public function setDataSignature() 
    {
        $this->dataSignature = array(
            "coupon_id"          => array("required" => 0, "type" => "int"),
            "cart_id"            => array("required" => 0, "type" => "int"),
            "cart_item_id"       => array("required" => 0, "type" => "int"),
            "cart_item_count"    => array("required" => 0, "type" => "int"),
            "cart_discount"      => array("required" => 0, "type" => "float"),
            "cart_total"         => array("required" => 0, "type" => "float"),
            "cart_item_rate"       => array("required" => 0, "type" => "float"),
        );
    }
}