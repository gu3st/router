<?php
require_once(__DIR__.'/bootstrap.php');

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/test/abc/123';


$router = new \Bramus\Router\Router();

$hdl = function($ctx){
    1 === 1;
};

$router->get('/test/{abc}/(?<id>[0-9]+)', $hdl);

$router->run();