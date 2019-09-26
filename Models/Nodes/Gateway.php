<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsRouter\Models\Nodes;

class Gateway extends Base
{
	protected $_isInit=false;
	protected $_initTime=null;
	protected $_sslCertObj=null;
	protected $_hbMax=30;
	protected $_regCb=null;
	protected $_errorCb=null;
	
	public function setRegistrationCb($obj=null, $method=null)
	{
		if (is_object($obj) === true && is_string($method) === true) {
			//set registration call back, any client trying to register will
			//have to pass though this method. Can be used to authenticate
			$this->_regCb	= array($obj, $method);
		}
		return $this;
	}
	public function setErrorCb($obj=null, $method=null)
	{
		if (is_object($obj) === true && is_string($method) === true) {
			$this->_errorCb	= array($obj, $method);
		}
		return $this;
	}
	public function setSsl($certObj)
	{
		$this->_sslCertObj			= $certObj;
		return $this;
	}
	public function connect()
	{
		if ($this->_isInit === false) {
			
			try {
				
				$this->_isInit	= null;
				if ($this->isRunning() === true) {
					throw new \Exception("Port is in use");
				}
				$socket				= \MTM\WsSocket\Factories::getSockets()->getNewServer();
				if (is_object($this->_sslCertObj) === true) {
					$socket->setConnection("tls", $this->getIp()->getAsString("std", false), $this->getPort());
					$socket->setSslConnection($this->_sslCertObj);
				} else {
					$socket->setConnection("tcp", $this->getIp()->getAsString("std", false), $this->getPort());
				}
				//reading or writing a message should never take longer than this
				$socket->setClientDefaultMaxReadTime(1000);
				$socket->setClientDefaultMaxWriteTime(1000);
				
				$this->_isInit		= true;
				$this->_initTime	= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
				$this->_sockObj		= $socket;
				
				//subscribe us to the loop and register
				$this->getAsync();
			
			} catch (\Exception $e) {
				$this->_isInit		= false;
				$this->_initTime	= null;
				$this->_sockObj		= null;
				throw $e;
			}
		}
		return $this;
	}
	public function ingressLoop($subObj)
	{
		if ($this->_isTerm === false) {

			$cTime	= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
			foreach ($this->getSocket()->getClients() as $cObj) {
				
				try {

					$msgs	= $cObj->getMessages();
					if (count ($msgs) > 0) {
						foreach ($msgs as $msg) {
							$this->handleMessage($cObj, $msg);
						}
					} else {
						if (($cTime - $cObj->getLastReceivedTime()) > $this->_hbMax) {
							//have not heard from this client in awhile, send a heartbeat
							if ($cObj->getIsConnected() === true) {
								$cObj->ping("hb-" . $cTime);
							} else {
								throw new \Exception("Socket: " . $cObj->getUuid() . " has disconnected");
							}
						}
					}
					
				} catch (\Exception $e) {
					$this->removePeerBySocket($cObj);
					$this->callError($e);
				}
			}

		} else {
			$this->terminate();
		}
	}
	protected function handleMessage($cObj, $msg)
	{
		try {
			
			$msgObj   	= @json_decode($msg);
			if (is_object($msgObj) === true) {

				if ($msgObj->event == "transit") {
					$txObj	= $this->getPeerFromSocket($cObj);
					if (is_object($txObj) === true) {
						$this->debugMsg($cObj, $msg);
						$this->getPeerTransit($msgObj, $txObj)->exec();
					} else {
						throw new \Exception("You are not registered");
					}

				} elseif ($msgObj->event == "registration") {
					$this->register($msgObj, $cObj);
				} elseif ($msgObj->event == "terminate") {
					$this->_isTerm	= true;
				} elseif ($msgObj->event == "client") {
					$txObj	= $this->getPeerFromSocket($cObj);
					if (is_object($txObj) === true) {
						if ($msgObj->type == "update-terminate") {
							$this->removePeer($txObj);
						} else {
							throw new \Exception("Not handled for type: " . $msgObj->type);
						}
					} else {
						throw new \Exception("Missing registration");
					}
					
				} else {
					throw new \Exception("Not handled for event: " . $msgObj->event);
				}

			} elseif (strpos($msg, "hb-") === 0) {
				//do nothing
			} elseif ($msg == "GoodByeServer") {
				$this->removePeerBySocket($cObj);
			} else {
				throw new \Exception("Invalid Message");
			}
		
		} catch (\Exception $e) {
			$this->getException($e)->setTxData($msg)->exec($cObj, false);
			$this->removePeerBySocket($cObj);
			$this->callError($e);
		}
	}
	protected function debugMsg($cObj, $msg)
	{
		$msgObj   	= @json_decode($msg);
		if (is_object($msgObj) === true) {
			$rId			= reset($msgObj->receivers);
			$to				= $this->getPeerFromGuid($rId)->getId();
			$from			= $this->getPeerFromSocket($cObj)->getId();
			
			file_put_contents("/dev/shm/merlin.txt", $from . "->" . $to . " - " . $msgObj->guid . " - " . $msgObj->type  . "\n", FILE_APPEND);
// 			$msgObj->data	= unserialize(base64_decode($msgObj->data));
			
// 			$subType		= null;
// 			$rId			= reset($msgObj->receivers);
// 			$to				= $this->getPeerFromGuid($rId)->getId();
// 			$from			= $this->getPeerFromSocket($cObj)->getId();
// 			if (
// 				$msgObj->data instanceof \stdClass
// 				&& property_exists($msgObj->data, "data") === true
// 			) {
// 				$dataObj	= @base64_decode($msgObj->data->data);
// 				if ($dataObj !== false) {
// 					$dataObj	= @unserialize($dataObj);
// 				}
// 				if ($dataObj === false) {
// 					$dataObj	= $msgObj->data->data;
// 					$subType	= $msgObj->data->type;
// 				} elseif (
// 					$dataObj instanceof \stdClass === true
// 					&& property_exists($dataObj, "heads") === true
// 				) {
// 					$subType	= $msgObj->data->type;
// 					unset($dataObj->heads);
// 				}
				
// 			} else {
// 				$dataObj	= $msgObj->data;
// 			}
			
// 			$rObj			= new \stdClass();
// 			$rObj->guid		= $msgObj->guid;
// 			$rObj->type		= $msgObj->type;
// 			$rObj->subType	= $subType;
			
// 			$rObj->from		= $from;
// 			$rObj->to		= $to;
// 			$rObj->data		= $dataObj;
			
// 			file_put_contents("/dev/shm/merlin.txt", print_r($rObj, true) . "\n", FILE_APPEND);
		}
	}
	protected function register($msgObj, $cObj)
	{
		$reqObj		= $this->getIngress($msgObj);
		$dataObj	= $reqObj->getRxData();
		if (
			$dataObj instanceof \stdClass === true
			&& property_exists($dataObj, "id") === true
			&& property_exists($dataObj, "auth") === true
		) {
			$peerObj	= $this->getPeerFromSocket($cObj, false);
			if (is_object($peerObj) === false) {
				
				if (strtolower(trim($dataObj->id)) != "gateway") {
					
					$peerObj	= new \MTM\WsRouter\Models\Peers\Gateway();
					$peerObj->setSocket($cObj)->setId($dataObj->id)->setRole("client");
					$peerObj->setParent($this);
					$reqObj->setSrcPeer($peerObj);
					$isValid	= $this->callRegistration($reqObj);
					
					if ($isValid === true) {

						$puObj		= $this->getPeerUpdate("add");
						$puObj->setTxData($peerObj->getData());
						
						$txObj			= new \stdClass();
						$txObj->pubId	= $peerObj->getGuid();
						$txObj->peers	= array();
						
						//add gateway to the pool of peers sent to the new client
						$rObj			= new \stdClass();
						$rObj->id		= $this->getId();
						$rObj->guid		= $this->getGuid();
						$rObj->role		= "gateway";
						$txObj->peers[]	= $rObj;
						
						foreach ($this->getPeers() as $eObj) {
							$txObj->peers[]	= $eObj->getData();
							$puObj->addReceiver($eObj);
						}
						
						$this->addPeer($peerObj);
						$reqObj->setTxData($txObj)->exec();
						$puObj->exec();
						
					} else {
						throw new \Exception("Registration denied");
					}
					
				} else {
					//throw, because we cannot respond to the egress obj
					//we do not have a valid peer to respond to
					throw new \Exception("Registration denied, Invalid ID");
				}

			} else {
				throw new \Exception("Already registered");
			}

		} else {
			throw new \Exception("Registration invalid");
		}
	}
	public function removePeer($peerObj)
	{
		$puObj			= $this->getPeerUpdate("remove");
		$puObj->setTxData($peerObj->getData());
		foreach ($this->_peerObjs as $eId => $eObj) {
			if ($peerObj->getGuid() == $eObj->getGuid()) {
				$peerObj->setParent(null);
				unset($this->_peerObjs[$eId]);
				$peerObj->getSocket()->terminate(false);
			} else {
				$puObj->addReceiver($eObj);
			}
		}
		$puObj->exec();	
		return $this;
	}
	public function callRegistration($reqObj)
	{
		$isValid	= true;
		if ($this->_regCb !== null) {
			try {
				$isValid	= call_user_func_array($this->_regCb, array($reqObj));
				if (is_bool($isValid) === false) {
					//hey user, return something we can work with
					throw new \Exception("Registration callback failed");
				}
			} catch (\Exception $e) {
				//do not throw the user error, clearly something went wrong
				//the function should only ever return true or false
				//we do not want to leak data to a potential mallory
				throw new \Exception("Registration function error");
			}
		}
		return $isValid;
	}
	public function callError($e)
	{
		try {
			if ($this->_errorCb !== null) {
				call_user_func_array($this->_errorCb, array($e));
			}
		} catch (\Exception $e) {
		}
	}
	public function terminate()
	{
		$termObj	= $this->getTerminateUpdate("gateway");
		foreach ($this->getPeers() as $peerObj) {
			$termObj->addReceiver($peerObj);
		}
		$termObj->exec(false);
		parent::terminate();
	}
	
	//commands
	public function getException($e=null)
	{
		$reqObj	= new \MTM\WsRouter\Models\Messages\Errors\Exception();
		$reqObj->setParent($this)->setEvent("error")->setError($e);
		return $reqObj;
	}
	public function getPeerUpdate($event=null)
	{
		$reqObj	= new \MTM\WsRouter\Models\Messages\Updates\Peer();
		$reqObj->setParent($this)->setEvent($event);
		return $reqObj;
	}
	public function getPeerTransit($msgObj=null, $srcPeer=null)
	{
		$reqObj	= new \MTM\WsRouter\Models\Messages\Requests\Transit();
		$reqObj->setParent($this)->setFromObj($msgObj, $srcPeer);
		return $reqObj;
	}
}