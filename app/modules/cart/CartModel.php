<?php

namespace Api\Modules\Cart;

/*
 *  Cart Model Class
 *  Invoked only by Cart Controller class.
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class CartModel extends \Api\Core\Container\Model implements \Api\Core\Container\DataSignature
{

    public $dataSignature = array();

    /*
     *  Model method to get all items in the cart 
     * 
     *  @return array $result
     */ 
    public function getCart()
    {
        $authService = $this->auth;
        $customerID = $authService::getUserID();

        $result = array();

        $query = "SELECT p.product_id, ci.customer_cart_id AS cart_id, 
		          p.product_name, p.product_image, p.product_rate, SUM(ci.customer_cart_item_count) as product_item_count 
		          FROM customer_carts cc
		          JOIN customer_cart_items ci on (cc.customer_cart_id = ci.customer_cart_id)
		          LEFT JOIN products p ON (ci.product_id = p.product_id)
		          WHERE cc.customer_id = :customer_id
                  GROUP BY p.product_id, ci.customer_cart_id";
        
        if (!$rs = $this->query($query, array("customer_id" => $customerID))) {
            $this->logger::warning("Query failed while trying to select the cart of the customer ($customerID)");
            $this->setError("QUERY_FAILED");
            return $result;
        }

        if (!$res = $rs->fetchAll(\PDO::FETCH_ASSOC)) {
            $this->logger::warning("No rows found while trying to select the cart of the customer ($customerID)");
            $this->setError("NO_ROWS_FOUND");
            return $result;
        } else {
            $cartItems = array();
            
            foreach ($res as $k => $row) {
                $cart_id = $row["cart_id"];
                unset($row["cart_id"]);
                $cartItems[] = $this->validateResult($row, $this->dataSignature);
            }
            
            $result = array(
                "cart_id" => $cart_id,
                "cart_discount" => "0.0",
                "cart_items" => $cartItems
            );
        }

        return $result;
    }


    /*
     *  Model method to save an item to the cart
     * 
     *  @return array $result
     */ 
    public function saveItem()
    {
        $authService = $this->auth;
        $customerID = $authService::getUserID();
        $result = array();

        // check existing cart for the customer
        $cartID = $this->getCustomerCart($customerID);

        $this->transaction("begin");

        if (!$cartID) {
            // create new cart
            if (!$cartID = $this->createCart($customerID)) {
                $this->logger::warning("Error while creating a new cart");
                $this->transaction("rollback");
                return $result;
            }
        } 
        
        if (!$cartItemCount = $this->addToCart($cartID)) {
            $this->logger::warning("Error while adding item to the cart-$cartID");
            $this->transaction("rollback");
            return $result;
        }
        
        
        if (!$result =  $this->validateResult(array("cart_id" => $cartID, "cart_item_count" => $cartItemCount), $this->dataSignature)) {
            $this->logger::warning("Error while validating result in the cart-$cartID");
            $this->transaction("rollback");
        }
        $this->transaction("commit");

        return $result;
    }


    /*
     *  Model method to check if an existing cart for a customer
     * 
     *  @param int $customerID
     * 
     *  @return int $cartID
     */ 
    private function getCustomerCart($customerID)
    {
        $query = "SELECT customer_cart_id
		          FROM customer_carts 
                  WHERE customer_id =  :customer_id";
        
        if (!$rs = $this->query($query, array("customer_id" => $customerID))) {
            $this->logger::warning("Query failed while trying to select cart of the customer ($customerID)");
            $this->setError("QUERY_FAILED");
            return 0;
        }

        $ret = $rs->fetch();
        $cartID  = $ret["customer_cart_id"] ?? "";
        
        return $cartID;
    }


    /*
     *  Model method to create a new cart for a customer
     * 
     *  @param int $customerID
     * 
     *  @return int $cartID
     */ 
    private function createCart($customerID)
    {
        $query = "INSERT INTO customer_carts (
                      customer_cart_product_count,
                      customer_cart_created_on,
                      customer_id
                      ) VALUES (
                      1,
                      NOW(),
                      :customer_id
                  )";
        
        if (!$this->query($query, array("customer_id" => $customerID))) {
            $this->logger::warning("Query failed while trying to create (INSERT) a new cart for the customer ($customerID)");
            $this->setError("QUERY_FAILED");
            return 0;
        }

        return $this->getInsertId();
    }

    
    /*
     *  Model method to add an item to existing cart of a customer
     * 
     *  @param int $cartID
     *  @param int $customerID
     * 
     *  @return int $cartItemCount
     */ 
    private function addToCart($cartID)
    {
        $data = $this->getData();

        $productID = $data["ProductId"];
        $productQty = $data["ProductQuantity"];

        $query = "INSERT INTO customer_cart_items (
                    customer_cart_id,
                    product_id, 
                    customer_cart_item_count
                    ) VALUES (
                    :cart_id,
                    :product_id,
                    :qty	
                )";
        
        if (!$this->query($query, array("cart_id" => $cartID, "product_id" => $productID, "qty" => $productQty))) {
            $this->logger::warning("Query failed while trying to create (INSERT) a quantity ($productQty) of cart item ($productID) to the cart ($cartID)");
            $this->setError("QUERY_FAILED");
            return 0;
        }

        if (!$cartItemCount = $this->getCartItemCount($cartID)) {
            return 0;
        }

        if (!$this->updateCartItemCount($cartID, $cartItemCount)) {
            $this->logger::warning("Query failed while trying to update cart-item-count ($cartItemCount) of the cart ($cartID)");
            $this->setError("QUERY_FAILED");
            return 0;
        }
        
        return $cartItemCount;
    }


    /*
     *  Model method to get total number of items in the cart
     * 
     *  @param int $cartID
     * 
     *  @return int $itemCount
     */ 
    private function getCartItemCount($cartID)
    {
        $query = "SELECT COUNT(distinct product_id) AS product_count  
                  FROM customer_cart_items WHERE customer_cart_id = :cart_id";

        if (!$rs = $this->query($query, array("cart_id" => $cartID))) {
            $this->logger::warning("Query failed while trying to select cart item count in a cart ($cartID)");
            $this->setError("QUERY_FAILED");
            return 0;
        }
        
        $ret = $rs->fetch();
        $itemCount  = $ret["product_count"] ?? 0;
        
        return (int)$itemCount;
    }


    /*
     *  Model method to update total number of items in the cart
     * 
     *  @param int $cartID
     *  @param int $itemCount
     * 
     *  @return bool
     */ 
    private function updateCartItemCount($cartID, $itemCount)
    {
        $query = "UPDATE customer_carts 
		          SET customer_cart_product_count = :cart_item_count
                  WHERE customer_cart_id =  :cart_id";

        return  $this->query($query, array("cart_id" => $cartID, "cart_item_count" => $itemCount));
    }

    
    /*
     *  Model method to delete an item to the cart
     * 
     *  @param int $cartID
     *  @param int $itemID
     * 
     *  @return bool
     */ 
    public function deleteItem(int $cartID, int $itemID) :bool
    {
        $this->transaction('begin');

        // delete item from the cart
        $query = "DELETE FROM customer_cart_items
                  WHERE customer_cart_id = :cart_id AND product_id = :item_id";
        
        if (!$this->query($query, array("cart_id" => $cartID, "item_id" => $itemID))) {
            $this->logger::warning("Query failed while trying to delete item ($itemID) from cart ($cartID)");
            $this->setError("QUERY_FAILED");
            $this->transaction("rollback");
            return false;
        }

        // get total item count
        if (!$itemCount = $this->getCartItemCount($cartID)) {
            $this->transaction("rollback");
            return false;
        }

        // update user cart with updated item count
        if (!$this->updateCartItemCount($cartID, $itemCount)) {
            $this->logger::warning("Query failed while trying to update cart-item-count ($cartItemCount) of the cart ($cartID)");
            $this->setError("QUERY_FAILED");
            $this->transaction("rollback");
            return false;
        }

        $this->transaction('commit');
        return true;
    }

    /*
     *  Model method to get the cart summary
     * 
     *  @param int $cartID
     * 
     *  @return array $result
     */ 
    public function getCartSummary(int $cartID) :array
    {
        $result = array();
        $query = "SELECT cci.product_id AS cart_item_id, SUM(customer_cart_item_count) AS cart_item_count,
		          COALESCE(SUM(customer_cart_item_count * product_rate), 0) as cart_total
		          FROM customer_cart_items  cci
		          LEFT JOIN products p ON (p.product_id = cci.product_id)
		          WHERE customer_cart_id = :cart_id
                  GROUP BY 1";
        
        if (!$rs = $this->query($query, array("cart_id" => $cartID))) {
            $this->logger::warning("Query failed while trying to select cart summary of the cart ($cartID)");
            $this->setError("QUERY_FAILED");
            return $result;
        }

        $res = $rs->fetchAll(\PDO::FETCH_ASSOC);
        
        try {
            foreach ($res as $k => $row) {
                $result[] = $this->validateResult($row, $this->dataSignature);
            }
        } catch(\Exception $e) {
            $this->logger::error("Validation error for the result set :: getCartSummary :: Exception - $e");
            $result = array();
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
            "cart_id"            => array("required" => 1, "type" => "int"),
            "cart_item_id"       => array("required" => 0, "type" => "int"),
            "cart_item_count"    => array("required" => 0, "type" => "int"),
            "cart_discount"      => array("required" => 0, "type" => "float"),
            "cart_total"         => array("required" => 0, "type" => "float"),
            "product_item_count" => array("required" => 0, "type" => "int"), 
            "product_id"         => array("required" => 0, "type" => "int"),
            "product_name"       => array("required" => 0, "type" => "string"),
            "product_rate"       => array("required" => 0, "type" => "float"),
            "product_image"      => array("required" => 0, "type" => "string"),
        );
    }
}