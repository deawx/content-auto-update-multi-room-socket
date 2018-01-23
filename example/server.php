<?php 
require_once __DIR__ . "/../vendor/autoload.php";
require_once 'MultiRoomSocketServer.php';

$environment = 'development';
$config = array();
$config['environment'] = $environment;
$config['db'] = array(
	'host' => 'localhost',
	'username' => 'root',
	'password' => '',
	'database' => 'multi-room-socket-server'
);
$port = 9911;
$server = new MultiRoomSocketServer($config);

$originCheckDetails = array();
$originCheckDetails = array(
	'currentDomain' => array(
		'localhost'
	),
	'allowedOrigins' => array(
		'localhost'
	)
);
//MultiRoomSocketServer::run($server, $port, '0.0.0.0', $originCheckDetails);
MultiRoomSocketServer::run($server, $port, '0.0.0.0');