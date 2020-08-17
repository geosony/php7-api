<?php


$routes = array();

$routes['/'] = array(
    "GET" => array(
        "method" => "fruits",
        "info" => "Get the list of fruits in the store",
        "version" => "1.0"
    )
);

$routes['/auth'] = array(
    "POST" => array(
        "method" => "login",
        "info" => "To authenticate login action providing precise credentials",
        "version" => "1.0"
    )
);

$routes['/fruits'] = array(
    "GET" => array(
        "method" => "fruits",
        "info" => "Get the list of fruits in the store",
        "version" => "1.0"
    )
);

$routes['/fruits/{fruitId}'] = array(
    "GET" => array(
        "method" => "fruits/getById",
        "info" => "Get a fruit in the store",
        "version" => "1.0"
    )
);

$routes['/cart'] = array(
    "GET" => array(
        "method" => "cart",
        "info" => "Get the cart of a user",
        "version" => "1.0"
    ),

    "POST" => array(
        "method" => "cart/addToCart",
        "info" => "Add a fruit to the cart of a user",
        "version" => "1.0"
    ),
    
    "PUT" => array(
        "method" => "cart/applyCoupon",
        "info" => "Apply a coupon to the cart",
        "version" => "1.0"
    ),

    "auth" => true,
);

$routes['/cart/{cartID}/{productID}'] = array(
    "DELETE" => array(
        "method" => "cart/deleteFromCart",
        "info" => "Delete a cart item",
        "version" => "1.0"
    ),

    "auth" => true
);

$routes['/checkout'] = array(
    "POST" => array(
        "method" => "checkout",
        "info" => "To checkout the order.",
        "version" => "1.0"
    ),
    
    "auth" => true
);