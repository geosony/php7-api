<?php

declare(strict_types=1);
namespace Api;
// include the essentials to bootstrap the framework.
define('ROOT_PATH', dirname(__FILE__) );
define('BASE_PATH', ROOT_PATH .'/app/');
require_once ("./app/conf/settings.php");
require_once ("./app/conf/autoload.php");
require_once ("./app/conf/services.php");
require_once ("./app/conf/routes.php");

$logger = $container['logger'];

try {
    $router = new Core\Http\Router($routes, $container);
} catch (\Exception $e) {
    $logger::logException($e);
    default_output(array(422, $e->getMessage()));
}

$module = $router->getModule();

if (!preg_match("/^([a-zA-Z0-9]+)(?:\/([a-zA-Z0-9]+))?$/", $module, $matches)) {
    default_output(array(422, "SERVER_ERROR"));
} else {

    $moduleName = ucfirst($matches[1]);
    $methodName = $matches[2] ?? 'index';

    $fqcn = "\\Api\\Modules\\$moduleName\\$moduleName";
    try {
        $obj = new $fqcn($router);
        $obj->$methodName();
    } catch(\Error $e) {
        $logger::logError($e);
        default_output(array(422, "SERVER_ERROR".$e->getMessage() ));
    } catch(\RuntimeException $e) {
        $logger::logException($e);
        default_output(array($e->getCode(), $e->getMessage()));
    } catch(\Exception $e) {
        $logger::logException($e);
        default_output(array($e->getCode(), $e->getMessage()));
    }
}
