<?php
namespace diveshopx\MultiRoomSocket;

use \diveshopx\MultiRoomSocket\Interfaces\ConnectedClientInterface;
use Ratchet\ConnectionInterface;

class BasicMultiRoomSocketServer extends AbstractMultiRoomSocketServer
{

	public $environment = ''; // 'production', 'development', 'testing', 'qa'
	
    public function __construct($config) {
		parent::__construct($config);
    }
	
    protected function makeUserWelcomeNotification(ConnectedClientInterface $client, $timestamp)
    {
		if ($this->environment !== 'production') {
			return vsprintf('Welcome %s!', array($client->getName()));
		} else {
			return ;
		}
    }

    protected function makeUserConnectedNotification(ConnectedClientInterface $client, $timestamp)
    {
		if ($this->environment !== 'production') {
			return vsprintf('%s has connected', array($client->getName()));
		} else {
			return ;
		}
    }

    protected function makeUserDisconnectedNotification(ConnectedClientInterface $client, $timestamp)
    {
		if ($this->environment !== 'production') {
			return vsprintf('%s has left', array($client->getName()));
		} else {
			return ;
		}
    }

    protected function makeNotificationReceivedNotification(ConnectedClientInterface $from, $notification)
    {
        return $notification;
    }

    protected function logNotificationReceived($client_name, $notification)
    {
    }
	
	protected function connectedNotification($client_name, $notification) {
		
	}
	
	protected function logErrorAndException($errMsg) {
	}

    protected function createClient(ConnectionInterface $conn, $name)
    {
        $client = new ConnectedClient;
        $client->setResourceId($conn->resourceId);
        $client->setConnection($conn);
        $client->setName($name);

        return $client;
    }

}