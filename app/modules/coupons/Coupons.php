<?php

namespace Api\Modules\Coupons;


/*
 *  Coupons Class
 *  Invoked only for cart/checkout endpoints.
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class Coupons extends \Api\Core\Container\Controller
{

    public $cart = array();
        
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
        $this->model = new \Api\Modules\Coupons\CouponsModel($this->getFilteredData(), $this->container);
        $this->model->setDataSignature();
    }


    /*
     *  Apply Coupon to the cart;
     *  if coupon code is given it will validate and calculate the coupon 
     *  if coupon code is not given it will automatically calculate a possible discount using existing cart rules
     * 
     *  if applied coupon is valid and the discount calculated is less than the amount of general cart discount, 
     *  the cart discount will be taken; the greater discount will be returned in general.
     * 
     *  @param int $code
     *  @param string $code
     * 
     *  @return array $discounts
     */ 
    public function applyCoupon(int $cartID=0, string $code='') :array
    {
        $discounts = array();

        $authService = $this->container["auth"];
        $customerID = $authService::getUserID();

        if (!$cartID) {
            $cartID = $this->model->getCartID($customerID);
        }
                
        $cartDiscounts = $this->calculateCartDiscount($cartID);
        //$code = 'ORANGE30';
        
        if ($code) {
            if (!$couponID = $this->model->getCouponID($code)) {
                $this->setError(": INVALID_COUPON");
                return false;
            }
            if ($couponDiscount = $this->calculateCouponDiscount($cartID, $couponID)) {
                $cartDiscounts[$couponID] = $couponDiscount;
            }
        } 

        if ($cartDiscounts) {
            arsort($cartDiscounts);
            $discounts["discount_applied"] = 1;
            $discounts["discount_amt"] = reset($cartDiscounts);
            $discounts["discount_summary"] = $cartDiscounts;
        }
        return $discounts; 
    }


    /*
     *  Calculate the cart discount using the cart rules added.
     * 
     *  @param int $cartID
     * 
     *  @return array $discounts
     */ 
    private function calculateCartDiscount(int $cartID) :array
    {
        $logger = $this->container["logger"];

        $discounts = array();
        
        if (!$cartRules = $this->model->getCartRules()) {
            return $discounts;
        }
        $this->cart = $this->model->getCartSummary($cartID);

        foreach ($cartRules as $rule) {

            if ($parsedRule = $this->parseCartRule($rule)) {

                extract($parsedRule);  // condition, discount, paramVars, paramChecks

                $discountAmt = $this->solveCouponRule($parsedRule);
                $discounts[$rule["coupon_id"]] = $discountAmt; 
            }
        }

        return $discounts;
    }


    /*
     *  Parse the cart rule and validate the formula
     * 
     *  @param array $rule
     * 
     *  @return array $parsedRule
     */ 
    private function parseCartRule(array $rule) :array
    {
        $parsedRule = array();
        $formula = $rule["rule_formula"] ?? '';

        if (!$formula) {
            $logger::warning("Invalid formula for the given rule");
            return $parsedRule;
        }

        if ($paramVars = $rule["rule_param_vars"]) {
            $paramVars = json_decode($paramVars, true);
            $parsedRule["paramVars"] = $paramVars;
        }

        if ($paramChecks = $rule["rule_param_checks"]) {
            $paramChecks = json_decode($paramChecks, true);
            $parsedRule["paramChecks"] = $paramChecks;
        }

        if (preg_match("/^[a-zA-Z0-9)(+*\/-]+=[0-9\/*=-]+$/", $formula)) {
            list($condition, $discount) = explode("=", $formula);

            $parsedRule["condition"] = $condition;
            $parsedRule["discount"] = $discount;
        } else {
            $parsedRule = array();
            $logger::warning("Invalid formula ($formula) found; which is not resolved by th code");
        }

        return $parsedRule;
    }


    /*
     *  Calculate the cart discount using the cart rules added.
     * 
     *  @param int $cartID
     *  @param int $couponID
     * 
     *  @return float $discountAmt
     */ 
    public function calculateCouponDiscount(int $cartID, int $couponID) :float
    {
        $cartRules = $this->model->getCartRules($couponID);

        $rule = $cartRules[0] ?? array();
        
        $discountAmt = 0.00;

        if ($rule) {
            if ($parsedRule = $this->parseCartRule($rule)) {
                extract($parsedRule);  // condition, discount, paramVars, paramChecks
                $discountAmt = $this->solveCouponRule($parsedRule);
            }
        }
        
        return $discountAmt;
        
    }

    /*
     *  Solve the formula of the cart rule
     * 
     *  Note :- its a wrapper method for solving the formula; and has the scope for scaling in future.
     * 
     *  @param array $parsedRule
     * 
     *  @return float $discountAmt
     */ 
    private function solveCartRule(array $parsedRule) :float
    {
        extract($parsedRule);

        $discountAmt = 0.00;

        if ($price = $this->solve($condition, $paramVars, $paramChecks)) {
            $discountAmt = $this->evaluate($discount, $price);
        }

        return $discountAmt;
    }


    /*
     *  Solve the formula of the coupon rule
     * 
     *  Note:- this method is just an alias for the above method for the moment.
     * 
     *  @param array $parsedRule
     * 
     *  @return float $discountAmt
     */ 
    private function solveCouponRule(array $parsedRule) :float
    {
        extract($parsedRule);

        $discountAmt = 0.00;

        if ($price = $this->solve($condition, $paramVars, $paramChecks)) {
            $discountAmt = $this->evaluate($discount, $price);
        }

        return $discountAmt;
    }


    /*
     *  Solve the formula of a rule with default or known criteria
     * 
     *  @param string $condition
     *  @param array $paramVars
     *  @param array $paramChecks
     * 
     *  @return float $price
     */ 
    private function solve(string $condition, array $paramVars, array $paramChecks) :float
    {
        $logger = $this->container["logger"];
        $price = 0.00;

        if (preg_match_all("/(\d+)?([a-zA-Z]+)/", $condition, $matches)) {
    
            $qtys = $matches[1];
            $items = $matches[2];
    
            $summary = array();
            
            extract($paramVars);
            for ($i=0; $i<count($items); $i++) {

                $item = $items[$i];
                if (isset($paramVars[$item])) {
    
                    $cartItemId = $$item;
    
                    $cartItemCount = $this->cart[$cartItemId]["cart_item_count"] ?? 0;
                    $couponItemQty = $qtys[$i] ?? 1; 
                    $couponItemQty = ($couponItemQty) ? (int)$couponItemQty : 1;
    
                    if (!$cartItemCount) {
                        $logger::info("Cart rule cannot applicable; no item ($cartItemId) found in the cart");
                        return $price;
                    }
    
                    $min = $paramChecks["min"] ?? 0;
                    if ($cartItemCount < $min) {
                        $logger::info("Item count $min ($cartItemCount) not reached for the item $cartItemId");
                        return $price;
                    }   
    
                    if ($cartItemCount < $couponItemQty) {
                        $logger::info("Item count $couponItemQty ($cartItemCount) not reached for the item $cartItemId");
                        return $price;
                    }
    
                    $summary[$item] = array(
                        "id" => $cartItemId,
                        "price" => $this->cart[$cartItemId]["cart_item_rate"],
                        "reqQty" => $couponItemQty,
                        "cartQty" => $cartItemCount,
                    );
                } 
            }
            
            
            if ($summary) {
                $setPrice = 0;
                foreach($summary as $item) {
                    $freeq = floor((int) $item["cartQty"] / (int) $item["reqQty"]); // Freequency of applied formula for the param item.
                    $freeqs[] = $freeq;
                    $setPrice += $item["reqQty"] * (float) $item["price"]; // calculate price for the amount qualified for the discount
                }
                if ($freeqs) {
                    sort($freeqs);
                    $lowFreeq = $freeqs[0];  // take the low freequency; assuming the the qualified formula set will be lowest
                    $price = $setPrice * $lowFreeq;
                }
            }
            return $price;
        } 
    }


    /*
     *  Evaluate the discount and calculate the total discount amount
     * 
     *  @param string $discount
     *  @param float $price
     * 
     *  @return float $discountAmt;
     */ 
    private function evaluate(string $discount, float $price) :float
    {
        $discountAmt = 0.00;
        if (preg_match("/^\d+$/", $discount)) {
            return (float) $discount;
        } elseif (preg_match("/^(\d+)\/(\d+)$/", $discount, $matches)) {
            $numer = $matches[1];
            $denom = $matches[2];
    
            $rate = (float) $numer/$denom;
            $discountAmt = $rate * $price;
        } 
        
        return $discountAmt;
    }
}