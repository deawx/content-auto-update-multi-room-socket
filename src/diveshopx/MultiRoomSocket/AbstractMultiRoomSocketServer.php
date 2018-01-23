<?php
namespace diveshopx\MultiRoomSocket;

use \diveshopx\MultiRoomSocket\Exception\ConnectedClientNotFoundException;
use \diveshopx\MultiRoomSocket\Exception\InvalidActionException;
use \diveshopx\MultiRoomSocket\Exception\MissingActionException;
use \diveshopx\MultiRoomSocket\Interfaces\ConnectedClientInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\OriginCheck;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

abstract class AbstractMultiRoomSocketServer implements MessageComponentInterface
{

    const ACTION_USER_CONNECTED = 'connect';
    const ACTION_NOTIFICATION_RECEIVED = 'notification';
    const ACTION_LIST_USERS = 'list-users';

    const PACKET_TYPE_USER_CONNECTED = 'user-connected';
    const PACKET_TYPE_USER_DISCONNECTED = 'user-disconnected';
    const PACKET_TYPE_NOTIFICATION = 'notification';
    const PACKET_TYPE_USER_LIST = 'list-users';
	
	private $environment = ''; // 'production', 'development', 'testing', 'qa'

    /**
     * @param AbstractMultiRoomSocketServer $notificationServer
     * @param int $port
     * @param string $ip
     * @return IoServer
     */
    public static function run(AbstractMultiRoomSocketServer $notificationServer, $port, $ip='0.0.0.0', $originCheckDetails = array())
    {
		//$currentDomain = array('localhost'), $allowedOrigins = array('localhost')
		if ( 
			!empty($originCheckDetails) 
			&& !empty($originCheckDetails['currentDomain']) 
			&& !empty($originCheckDetails['allowedOrigins'])
		) {
			$checkedApp = new OriginCheck($notificationServer, $originCheckDetails['currentDomain']);
			$checkedApp->allowedOrigins = $originCheckDetails['allowedOrigins'];
		} else {
			$checkedApp = $notificationServer;
		}
		
        $wsServer = new WsServer($checkedApp);
        $http = new HttpServer($wsServer);
        $server = IoServer::factory($http, $port, $ip);
        $server->run();
        return $server;
    }

    /**
     * @var array
     */
    protected $rooms;

    /**
     * @var array|ConnectedClientInterface[]
     */
    protected $clients;

    /**
     * @param ConnectedClientInterface $client
     * @param int $timestamp
     * @return string
     */
    abstract protected function makeUserWelcomeNotification(ConnectedClientInterface $client, $timestamp);

    /**
     * @param ConnectedClientInterface $client
     * @param int $timestamp
     * @return string
     */
    abstract protected function makeUserConnectedNotification(ConnectedClientInterface $client, $timestamp);

    /**
     * @param ConnectedClientInterface $client
     * @param int $timestamp
     * @return string
     */
    abstract protected function makeUserDisconnectedNotification(ConnectedClientInterface $client, $timestamp);

    /**
     * @param ConnectedClientInterface $from
     * @param array $notification
     * @return string
     */
    abstract protected function makeNotificationReceivedNotification(ConnectedClientInterface $from, $notification);

    /**
     * @param string $client_name
     * @param array $notification
     * @return string
     */
    abstract protected function logNotificationReceived($client_name, $notification);
	
    /**
     * @param string $client_name
     * @param array $notification
     * @return string
     */
    abstract protected function connectedNotification($client_name, $notification);

    /**
     * @param ConnectionInterface $conn
     * @param $name
     * @return ConnectedClientInterface
     */
    abstract protected function createClient(ConnectionInterface $conn, $name);

    public function __construct($config)
    {
		$this->environment = $config['environment'];
        $this->rooms = array();
        $this->clients = array();
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn)
    {

    }
	
	abstract protected function logErrorAndException($errMsg);

    /**
     * @param ConnectionInterface $conn
     * @param string $notification
     * @throws ConnectedClientNotFoundException
     * @throws InvalidActionException
     * @throws MissingActionException
     */
    public function onMessage(ConnectionInterface $conn, $notification)
    {
		if ($this->environment !== 'production') {
			echo "Packet received: ".$notification.PHP_EOL;
		}
        $notification = json_decode($notification, true);
        $roomId = $this->makeRoom($notification['roomId']);
		$requestParams = isset($notification['request_params']) ? $notification['request_params'] : '';

        if (!isset($notification['socket_action'])) {
			$this->logErrorAndException('No action specified');
            throw new MissingActionException('No action specified');
        }

        switch ($notification['socket_action']) {
            case self::ACTION_USER_CONNECTED:
                $userName = $notification['userName'];
                $client = $this->createClient($conn, $userName);
				$client_name = $client->getName();				
                $this->connectUserToRoom($client, $roomId);
				$this->connectedNotification($client_name, $notification);
                $this->sendUserConnectedNotification($client, $roomId);
                $this->sendUserWelcomeNotification($client, $roomId);
                $this->sendListUsersNotification($client, $roomId, $requestParams);
                break;
            case self::ACTION_LIST_USERS:
                $client = $this->findClient($conn);
                $this->sendListUsersNotification($client, $roomId, $requestParams);
                break;
            case self::ACTION_NOTIFICATION_RECEIVED:
                $notification['timestamp'] = isset($notification['timestamp']) ? $notification['timestamp'] : time();
                $client = $this->findClient($conn);
				$client_name = $client->getName();
                $this->logNotificationReceived($client_name, $notification);
                $this->sendNotification($client, $notification);
                break;
            default: 
				$this->logErrorAndException('Invalid action: '.$notification['socket_action']);
				throw new InvalidActionException('Invalid action: '.$notification['socket_action']);
        }
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn)
    {
        $this->closeClientConnection($conn);
    }

    /**
     * @param ConnectionInterface $conn
     * @param \Exception $e
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->closeClientConnection($conn);
        $conn->close();
    }

    /**
     * @return array
     */
    public function getRooms()
    {
        return $this->rooms;
    }

    /**
     * @param array $rooms
     */
    public function setRooms($rooms)
    {
        $this->rooms = $rooms;
    }

    /**
     * @return array|ConnectedClientInterface[]
     */
    public function getClients()
    {
        return $this->clients;
    }

    /**
     * @param array|ConnectedClientInterface[] $clients
     */
    public function setClients($clients)
    {
        $this->clients = $clients;
    }

    /**
     * @param ConnectionInterface $conn
     * @throws ConnectedClientNotFoundException
     */
    protected function closeClientConnection(ConnectionInterface $conn)
    {
        $client = $this->findClient($conn);

        unset($this->clients[$client->getResourceId()]);
        foreach ($this->rooms AS $roomId=>$connectedClients) {
            if (isset($connectedClients[$client->getResourceId()])) {
                $clientRoomId = $roomId;
                unset($this->rooms[$roomId][$client->getResourceId()]);
            }
        }

        if (isset($clientRoomId)) {
            $this->sendUserDisconnectedNotification($client, $clientRoomId);
        }
    }

    /**
     * @param ConnectionInterface $conn
     * @return ConnectedClientInterface
     * @throws ConnectedClientNotFoundException
     */
    protected function findClient(ConnectionInterface $conn)
    {
        if (isset($this->clients[$conn->resourceId])) {
            return $this->clients[$conn->resourceId];
        }
		
		$this->logErrorAndException($conn->resourceId);
        throw new ConnectedClientNotFoundException($conn->resourceId);
    }

    /**
     * @param ConnectedClientInterface $client
     * @param array $notification
     */
    protected function sendNotification(ConnectedClientInterface $client, $notification)
    {
        $dataPacket = array(
            'type'=>self::PACKET_TYPE_NOTIFICATION,
            'from'=>$client->asArray(),
            'timestamp'=>$notification['timestamp'],
            'notification'=>$this->makeNotificationReceivedNotification($client, $notification),
        );

        $clients = $this->findRoomClients($notification['roomId']);
        $this->sendDataToClients($clients, $dataPacket);
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function sendUserConnectedNotification(ConnectedClientInterface $client, $roomId)
    {
        $dataPacket = array(
            'type'=>self::PACKET_TYPE_USER_CONNECTED,
            'timestamp'=>time(),
            'notification'=>$this->makeUserConnectedNotification($client, time()),
        );

        $clients = $this->findRoomClients($roomId);
        unset($clients[$client->getResourceId()]);
        $this->sendDataToClients($clients, $dataPacket);
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function sendUserWelcomeNotification(ConnectedClientInterface $client, $roomId)
    {
        $dataPacket = array(
            'type'=>self::PACKET_TYPE_USER_CONNECTED,
            'timestamp'=>time(),
            'notification'=>$this->makeUserWelcomeNotification($client, time()),
        );

        $this->sendData($client, $dataPacket);
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function sendUserDisconnectedNotification(ConnectedClientInterface $client, $roomId)
    {
        $dataPacket = array(
            'type'=>self::PACKET_TYPE_USER_DISCONNECTED,
            'timestamp'=>time(),
            'notification'=>$this->makeUserDisconnectedNotification($client, time()),
        );

        $clients = $this->findRoomClients($roomId);
        $this->sendDataToClients($clients, $dataPacket);
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function sendListUsersNotification(ConnectedClientInterface $client, $roomId, $requestParams)
    {
        $clients = array();
        foreach ($this->findRoomClients($roomId) AS $roomClient) {
            $clients[] = array(
                'name'=>$roomClient->getName(),
            );
        }

        $dataPacket = array(
            'type'=>self::PACKET_TYPE_USER_LIST,
            'timestamp'=>time(),
            'clients'=>$clients,
			'request_params' => $requestParams
        );

        $this->sendData($client, $dataPacket);
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function connectUserToRoom(ConnectedClientInterface $client, $roomId)
    {
        $this->rooms[$roomId][$client->getResourceId()] = $client;
        $this->clients[$client->getResourceId()] = $client;
    }

    /**
     * @param $roomId
     * @return array|ConnectedClientInterface[]
     */
    protected function findRoomClients($roomId)
    {
        return $this->rooms[$roomId];
    }

    /**
     * @param ConnectedClientInterface $client
     * @param array $packet
     */
    protected function sendData(ConnectedClientInterface $client, array $packet)
    {
        $client->getConnection()->send(json_encode($packet));
    }

    /**
     * @param array|ConnectedClientInterface[] $clients
     * @param array $packet
     */
    protected function sendDataToClients(array $clients, array $packet)
    {
        foreach ($clients AS $client) {
            $this->sendData($client, $packet);
        }
    }

    /**
     * @param $roomId
     * @return mixed
     */
    protected function makeRoom($roomId)
    {
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = array();
        }

        return $roomId;
    }

}