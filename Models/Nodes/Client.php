<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsRouter\Models\Nodes;

class Client extends Base
{
	protected $_routeGuid=null; //my public address
	protected $_isInit=false;
	protected $_initTime=null;
	protected $_sslCertObj=null;
	protected $_sslVerify=null;
	protected $_sslVerifyName=null;
	protected $_sslSelfSignOk=null;
	protected $_reqObjs=array();
	protected $_penMsgs=array();
	protected $_ingressCb=null;
	protected $_hbMax=30;
	
	public function connect($authData=null)
	{
		if ($this->_isInit === false) {
			
			$this->_isInit	= null;
			try {
				
				if ($this->isRunning() === false) {
					throw new \Exception("Gateway not responding: " . $this->getIp()->getAsString("std", false) . ", " . $this->getPort());
				}
				$socket		= \MTM\WsSocket\Factories::getSockets()->getNewClient();
				if (is_object($this->_sslCertObj) === true) {
					$socket->setConnection("tls", $this->getIp()->getAsString("std", false), $this->getPort());
					$socket->setSslConnection($this->_sslCertObj, $this->_sslVerify, $this->_sslVerifyName, $this->_sslSelfSignOk);
				} else {
					$socket->setConnection("tcp", $this->getIp()->getAsString("std", false), $this->getPort());
				}
	
				$socket->setDefaultReadTime(1000);
				$socket->setDefaultWriteTime(1000);
				$socket->setTerminationCb($this, "terminate");

				$this->_sockObj		= $socket;
				$this->_initTime	= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
				$this->_isInit		= true;
				
				//subscribe us to the loop and register
				$this->getAsync();
				
				//make a fake peer until we are connected
				$gwPeerObj	= new \MTM\WsRouter\Models\Peers\Client();
				$gwPeerObj->setParent($this)->setId("gateway")->setSocket($this->getSocket())->setRole("gateway");
				
				$regObj			= new \stdClass();
				$regObj->id		= $this->getId();
				$regObj->auth	= $authData;
				
				$data			= $gwPeerObj->newRequest($regObj)->setEvent("registration")->get();
				foreach ($data->peers as $peer) {
					if ($peer->role != "gateway") {
						$peerObj	= new \MTM\WsRouter\Models\Peers\Client();
					} else {
						$peerObj	= $gwPeerObj;
					}
					$peerObj->setId($peer->id)->setGuid($peer->guid)->setSocket($this->getSocket())->setRole($peer->role);
					$this->addPeer($peerObj);
				}
				
				$this->_routeGuid	= $data->pubId;
				
			} catch (\Exception $e) {
				$this->_isInit		= false;
				$this->_initTime	= null;
				$this->_peerObjs	= array();
				$this->_sockObj		= null;
				throw $e;
			}
		}
		return $this;
	}
	public function getRouteGuid()
	{
		//use if you need to tell someone how to reach us directly
		//this is the direct mailing address, if you just want someone in
		//our group then use getId()
		return $this->_routeGuid;
	}
	public function setSsl($certObj, $verify=true, $verifyName=false, $selfSignOk=false)
	{
		//for gateway only $certObj is needed
		$this->_sslCertObj			= $certObj;
		$this->_sslVerify			= $verify;
		$this->_sslVerifyName		= $verifyName;
		$this->_sslSelfSignOk		= $selfSignOk;
		return $this;
	}
	public function setIngressCb($obj=null, $method=null)
	{
		if (is_object($obj) === true && is_string($method) === true) {
			//set ingress call back, any request received will
			//have to pass though this method. Can be used to authenticate
			$this->_ingressCb	= array($obj, $method);
		}
		return $this;
	}
	public function ingressLoop($subObj)
	{
		if ($this->getTerminated() === false) {

			$msgs	= $this->getSocket()->getMessages();
			if (count($msgs) > 0) {
				$this->_penMsgs	= array_merge($this->_penMsgs, $msgs);
			} else {
				
				$cTime	= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
				if (($cTime - $this->getSocket()->getLastReceivedTime()) > $this->_hbMax) {
					//have not heard from the gateway in awhile, send a heartbeat
					if ($this->getSocket()->getIsConnected() === true) {
						$this->getSocket()->ping("hb-" . $cTime);
					} else {
						$this->terminate();
						throw new \Exception("Socket disconnected");
					}
				}
			}
			
			$msgs	= &$this->getPendingIngress();
			foreach ($msgs as $mId => $msg) {
				unset($msgs[$mId]);
				$this->handleMessage($msg);
			}
			
			$reqObjs	= &$this->getPending();
			foreach ($reqObjs as $guid => $reqObj) {
				if ($reqObj->getIsDone() === true) {
					unset($reqObjs[$guid]);
				} elseif ($reqObj->getAge() > $reqObj->getTimeout()) {
					unset($reqObjs[$guid]);
					$reqObj->setError(new \Exception("Timeout after: " . $reqObj->getTimeout() . "ms"))->setDone();
				}
			}
		}
	}
	protected function handleMessage($msg)
	{
		$msgObj   	= @json_decode($msg);
		if (is_object($msgObj) === true) {

			if ($msgObj->type == "egress-request") {
				
				$peerObj	= $this->getPeerFromGuid($msgObj->srcPeer);
				$reqObj		= $this->getIngress($msgObj, $peerObj);
				$this->callIngress($reqObj);

			} elseif ($msgObj->type == "egress-response") {

				$respObj	= $this->getResponse($msgObj);
				if (array_key_exists($respObj->getGuid(), $this->_reqObjs) === true) {
					
					$reqObj		= $this->_reqObjs[$respObj->getGuid()];
					$peerObj	= $this->getPeerFromGuid($msgObj->srcPeer, false);
					if (is_object($respObj->getError()) === true) {
						$e	= $respObj->getError();
					} else {
						$e	= null;
					}
					$reqObj->setRxData($respObj->getRxData(), $peerObj, $e);
					
				} else {
					//request obj must have timed out
					//discard this response
				}
				
			} elseif ($msgObj->type == "update-peer") {
				$respObj	= $this->getUpdate($msgObj);
				$peer		= $respObj->getRxData();
				
				if ($respObj->getEvent() == "add") {
					$peerObj	= new \MTM\WsRouter\Models\Peers\Client();
					$peerObj->setId($peer->id)->setGuid($peer->guid)->setSocket($this->getSocket());
					$this->addPeer($peerObj);
					
				} elseif ($respObj->getEvent() == "remove") {
					$peerObj	= $this->getPeerFromGuid($peer->guid);
					if (is_object($peerObj) === true) {
						$peerObj->setTerminated();
						$this->removePeer($peerObj);
						
						//error all pending requests that use this peer
						$reqObjs	= &$this->getPending();
						foreach ($reqObjs as $guid => $reqObj) {
							foreach ($reqObj->getReceivers() as $eObj) {
								if ($eObj->getGuid() == $peerObj->getGuid()) {
									$e	= new \Exception("Peer was removed: " . $peerObj->getId());
									$reqObj->setRxData(null, $peerObj, $e);
									break;
								}
							}
						}
					}
					
				} else {
					throw new \Exception("Not handled for event: " . $respObj->getEvent());
				}

			} elseif ($msgObj->type == "update-terminate") {
				$respObj	= $this->getUpdate($msgObj);
				if ($respObj->getEvent() == "gateway") {
					$this->terminate();
					throw new \Exception("Gateway Is Going Away");
				} else {
					throw new \Exception("Not handled for event: " . $respObj->getEvent());
				}
				
			} elseif ($msgObj->type == "exception") {
				throw new \Exception("GW: " . $msgObj->error->msg, $msgObj->error->code);
			} else {
				throw new \Exception("Not handled for type: " . $msgObj->type);
			}

		} elseif ($msg == "GoodByeClient" || $msg == "") {
			$this->terminate();
			throw new \Exception("Gateway Terminated Socket");
		} elseif (strpos($msg, "hb-") === 0) {
			//do nothing
		} else {
			throw new \Exception("Invalid Message");
		}
	}
	public function addPending($reqObj)
	{
		$this->_reqObjs[$reqObj->getGuid()]	= $reqObj;
		return $this;
	}
	public function &getPending()
	{
		return $this->_reqObjs;
	}
	public function &getPendingIngress()
	{
		return $this->_penMsgs;
	}
	public function removePeer($peerObj)
	{
		foreach ($this->_peerObjs as $eId => $eObj) {
			if ($peerObj->getGuid() == $eObj->getGuid()) {
				unset($this->_peerObjs[$eId]);
				$peerObj->terminate();
				$peerObj->setParent(null);
				$peerObj->setTerminated();
				//dont terminate the socket, as client we use the same socket
				//for all peers
				break;
			}
		}
		return $this;
	}
	public function callIngress($reqObj)
	{
		if ($this->_ingressCb !== null) {
			try {
				call_user_func_array($this->_ingressCb, array($reqObj));
			} catch (\Exception $e) {
				$reqObj->setError($e)->exec();
			}
		}
	}
	public function terminate()
	{
		$sockObj	= $this->getSocket();
		if (is_object($sockObj) === true) {
			if ($sockObj->getTermStatus() === false) {
				$termObj	= $this->getTerminateUpdate("client");
				foreach ($this->getPeers() as $peerObj) {
					if ($peerObj->getRole() == "gateway") {
						//signal the gateway we are going away
						$termObj->addReceiver($peerObj)->exec();
					}
					$this->removePeer($peerObj);
				}
			}
		}
		parent::terminate();
	}
	public function terminateGateway()
	{
		if ($this->_isInit === true) {
			
			$termObj	= $this->getTerminateUpdate("terminate");
			foreach ($this->getPeers() as $peerObj) {
				if ($peerObj->getRole() == "gateway") {
					$termObj->addReceiver($peerObj);
				}
			}
			$termObj->exec();
			$this->getAsync()->unsubscribe();
		}
		return $this;
	}
	//commands
	public function getRequest($event=null)
	{
		$reqObj	= new \MTM\WsRouter\Models\Messages\Requests\Egress();
		$reqObj->setParent($this)->setEvent($event);
		return $reqObj;
	}
	public function getResponse($msgObj=null)
	{
		$reqObj	= new \MTM\WsRouter\Models\Messages\Responses\Egress();
		$reqObj->setParent($this)->setFromObj($msgObj);
		return $reqObj;
	}
	public function getUpdate($msgObj=null)
	{
		$reqObj	= new \MTM\WsRouter\Models\Messages\Responses\Update();
		$reqObj->setParent($this)->setFromObj($msgObj);
		return $reqObj;
	}
}