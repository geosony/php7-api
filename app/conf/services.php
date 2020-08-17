<?php
require ROOT_PATH . '/vendor/autoload.php';

use Pimple\Container;

// Open a new container
$container = new Container();

// redis container service
$container['redis'] = function () {
    return new Predis\Client([
        'scheme' => 'tcp',
        'host'   => get_cfg_var("api.redis.host"),
        'port'   => get_cfg_var("api.redis.port"),
    ]);
};

// database container service
// dsn is of mysql
$container['db'] = function() {
    $dbHost = get_cfg_var("api.db.host");
    $dbPort = get_cfg_var("api.db.port");
    $dbName = get_cfg_var("api.db.name");
    $dbUser = get_cfg_var("api.db.user");
    $dbPass = get_cfg_var("api.db.pass");

    return new \PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName}", $dbUser, $dbPass);
};

// auth service
$container['auth'] = function($c) {
    return  new \Api\Core\Container\Auth($c);
};

// a log factory; beware this factory will make static methods
// we are not expecting multiple log files
$container['logFactory'] = function($c) {
    return function ($c, $channel='') {
        return new \Api\Core\Container\Logger($c, $channel);
    };
};

// default logger will be 'logger'
$container['logger'] = function($c) {
    return $c['logFactory']($c);
};