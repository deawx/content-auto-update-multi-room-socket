<?php

use \diveshopx\MultiRoomSocket\BasicMultiRoomSocketServer;
use Ratchet\ConnectionInterface;

class MultiRoomSocketServer extends BasicMultiRoomSocketServer
{
	public static $db = array();
	private $db_con;
    public function __construct($config) {
		parent::__construct($config);
		self::$db = $config['db'];
    }
	
    protected function logNotificationReceived($client_name, $notification) {
		$this->db_con = mysqli_connect(self::$db['host'], self::$db['username'], self::$db['password'], self::$db['database']);
		
		if (mysqli_connect_errno()) {
			echo "Failed to connect to MySQL: " . mysqli_connect_error();
		}
		
		$roomId = isset($notification['roomId']) ? $notification['roomId'] : '';
		$notification = isset($notification['notification']) ? $notification['notification'] : '';		
		
		$query = "insert into notifications(`client_name`, `room_id`, `notification`) values ('".$client_name."','".$roomId."', '".$notification."')";

		mysqli_query($this->db_con, $query);

		mysqli_close($this->db_con);
    }
	
	protected function logErrorAndException($errMsg) {
		
	}	

}