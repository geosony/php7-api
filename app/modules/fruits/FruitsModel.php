<?php

namespace Api\Modules\Fruits;

/*
 *  Fruits Model Class
 *  Invoked only by Fruits Controller class.
 * 
 *  @author "Sony George" <sony@thinkberries.com>
 */
class FruitsModel extends \Api\Core\Container\Model implements \Api\Core\Container\DataSignature
{

    public $dataSignature = array();

    /*
     *  Model method to get all fruits 
     * 
     *  @return array $result
     */ 
    public function getAll()
    {
        $query = "SELECT product_id, product_name, product_rate, product_image, product_short_desc, product_desc, product_stock
		          FROM products p
		          LEFT JOIN product_categories pc ON (pc.product_cat_id = p.product_cat_id)
		          WHERE product_cat_name = 'fruits'
                  ORDER BY product_name";
                  
        $rs = $this->query($query);
        $res = $rs->fetchAll(\PDO::FETCH_ASSOC);

        $result = array();
        foreach ($res as $k => $row) {
            $result[] = $this->validateResult($row, $this->dataSignature);
        }
        return $result;
    }

    /*
     *  Model method to get a specific fruit
     * 
     *  @return array $result
     */ 
    public function getById($fruitId)
    {
        $query = "SELECT product_id, product_name, product_rate, product_image, product_short_desc, product_desc, product_stock
		          FROM products p
		          LEFT JOIN product_categories pc ON (pc.product_cat_id = p.product_cat_id)
		          WHERE product_cat_name = 'fruits' AND product_id = $fruitId;
                  ORDER BY product_name";
                  
        $rs = $this->query($query);
        $res = $rs->fetch(\PDO::FETCH_ASSOC);

        $result = $this->validateResult($res, $this->dataSignature);
        return $result;
    }


    /*
     *  Model method to get a specific fruit minimum details
     * 
     *  @return array $result
     */ 
    public function getFruitSummary($fruitId)
    {
        $query = "SELECT product_id, product_name, product_rate, product_image, product_stock
		          FROM products p
		          LEFT JOIN product_categories pc ON (pc.product_cat_id = p.product_cat_id)
		          WHERE product_cat_name = 'fruits' AND product_id = $fruitId;
                  ORDER BY product_name";
                  
        $rs = $this->query($query);
        $res = $rs->fetch(\PDO::FETCH_ASSOC);

        $result = $this->validateResult($res, $this->dataSignature);
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
            "product_id"         => array("required" => 1, "type" => "int"),
	        "product_name"       => array("required" => 1, "type" => "string"),
	        "product_short_desc" => array("required" => 0, "type" => "string"),
	        "product_rate"       => array("required" => 1, "type" => "float"),
	        "product_image"      => array("required" => 1, "type" => "string"),
	        "product_desc"       => array("required" => 0, "type" => "string"),
	        "product_stock"      => array("required" => 0, "type" => "int"),
        );
    }
}