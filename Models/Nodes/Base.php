<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsRouter\Models\Nodes;

class Base
{
	protected $_guid=null;
	protected $_id=null;
	protected $_isTerm=false;
	protected $_sockIp=null;
	protected $_sockPort=null;
	protected $_sockObj=null;
	protected $_aSyncObj=null;
	protected $_errorCb=null;
	protected $_peerObjs=array();
	
	public function __destruct()
	{
		$this->terminate();
	}
	public function setConfiguration($id, $ip, $port)
	{
		$id	= trim($id);
		if (is_string($id) === false || strlen($id) < 1) {
			throw new \Exception("Invalid Id");
		}
		if (is_object($ip) === false) {
			$ip   		= \MTM\Network\Factories::getIp()->getIpFromString($ip);
		}
		$this->_id			= $id;
		$this->_sockIp		= $ip;
		$this->_sockPort	= intval($port);
		return $this;
	}
	public function getGuid()
	{
		if ($this->_guid === null) {
			$this->_guid	= \MTM\Utilities\Factories::getGuids()->getV4()->get(false);
		}
		return $this->_guid;
	}
	public function getId()
	{
		return $this->_id;
	}
	public function getIp()
	{
		return $this->_sockIp;
	}
	public function getPort()
	{
		return $this->_sockPort;
	}
	public function setTerminated()
	{
		$this->_isTerm	= true;
		return $this;
	}
	public function getTerminated()
	{
		return $this->_isTerm;
	}
	public function isRunning()
	{
		return \MTM\WsSocket\Factories::getSockets()->getApi()->testConnect($this->getIp()->getAsString("std", false), $this->getPort(), "tcp", 1000);
	}
	public function getSocket()
	{
		return $this->_sockObj;
	}
	public function getAsync()
	{
		if ($this->_aSyncObj === null) {
			$loopObj			= \MTM\Async\Factories::getServices()->getLoop();
			$this->_aSyncObj	= $loopObj->getSubscription()->setCallback($this, "ingressLoop");
		}
		return $this->_aSyncObj;
	}
	public function addPeer($peerObj)
	{
		$peerObj->setParent($this);
		$this->_peerObjs[$peerObj->getGuid()]	= $peerObj;
		return $this;
	}
	public function getPeers()
	{
		return $this->_peerObjs;
	}
	public function getPeersById($id, $onlyOne=true)
	{
		//id is not unique
		//run messages to be sure there has not been updates
		\MTM\Async\Factories::getServices()->getLoop()->runOnce();
		$rObjs	= array();
		foreach ($this->getPeers() as $peerObj) {
			if ($peerObj->getId() == $id) {
				$rObjs[]	= $peerObj;
			}
		}
		if ($onlyOne === false) {
			return $rObjs;
		} elseif (count($rObjs) > 0) {
			//id is a group fulfilling a role or responsibillity
			//return a random peer, if you want a specific one use
			//getPeerFromGuid()
			return $rObjs[array_rand($rObjs)];
		} else {
			return null;
		}
	}
	public function getPeerFromGuid($guid, $throw=true)
	{
		foreach ($this->_peerObjs as $peerObj) {
			if ($guid == $peerObj->getGuid()) {
				return $peerObj;
			}
		}
		if ($throw === true) {
			//id used by clients, to determine when a peer went away
			throw new \Exception("Invalid Peer Guid: " . $guid, 2500);
		} else {
			return null;
		}
	}
	public function getPeerFromSocket($sockObj, $throw=true)
	{
		foreach ($this->_peerObjs as $peerObj) {
			if ($sockObj->getUuid() == $peerObj->getSocket()->getUuid()) {
				return $peerObj;
			}
		}
		if ($throw === true) {
			throw new \Exception("No peer on socket uuid: " . $sockObj->getUuid());
		} else {
			return null;
		}
	}
	public function removePeerBySocket($sockObj)
	{
		$peerObj	= $this->getPeerFromSocket($sockObj, false);
		if (is_object($peerObj) === true) {
			$this->removePeer($peerObj);
		} else {
			$sockObj->terminate(false);
		}
		return $this;
	}
	public function terminate()
	{
		if (is_object($this->_aSyncObj) === true) {
			$this->_aSyncObj->unsubscribe();
			$this->_aSyncObj	= null;
		}
		if (is_object($this->_sockObj) === true) {
			$this->_sockObj->terminate(false);
			$this->_sockObj		= null;
		}
		$this->setTerminated();
	}
	public function setErrorCb($obj=null, $method=null)
	{
		if (is_object($obj) === true && is_string($method) === true) {
			//set error call back, any uncaught exception will be sent here
			$this->_errorCb	= array($obj, $method);
		}
		return $this;
	}
	protected function callError($e)
	{
		if ($this->_errorCb !== null) {
			try {
				call_user_func_array($this->_errorCb, array($e));
			} catch (\Exception $e) {
			}
		}
	}
	//commands
	public function getIngress($msgObj=null, $peerObj=null)
	{
		$reqObj	= new \MTM\WsRouter\Models\Messages\Requests\Ingress();
		$reqObj->setParent($this)->setFromObj($msgObj, $peerObj);
		return $reqObj;
	}
	public function getTerminateUpdate($event=null)
	{
		$reqObj	= new \MTM\WsRouter\Models\Messages\Updates\Terminate();
		$reqObj->setParent($this)->setEvent($event);
		return $reqObj;
	}
}