<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsRouter\Docs\Examples;

class Client extends Base
{
	protected $_clientId=null;
	protected $_clientObj=null;
	protected $_recvData=array();

	public function terminateGateway($tls=false)
	{
		$this->_recvData		= array();
		$this->_clientId		= "terminator";
		$clientObj				= $this->getClient($tls);
		$clientObj->terminateGateway();
	}
	public function runAsTimeServer($tls=false)
	{
		$this->_recvData		= array();
		$this->_clientId		= "time_server";
		$clientObj				= $this->getClient($tls);
		
		$clientObj->getAsync()->getParent()->run();
		return $this->getRecvData();
	}
	public function runAsTimeClient($tls=false)
	{
		$this->_recvData		= array();
		$this->_clientId		= "time_client";
		$clientObj				= $this->getClient($tls);
		
		//send request using some data structure
		//to the server asking for the time
		$serverId				= "time_server";
		$peerObj				= $clientObj->getPeersById($serverId);
		if (is_object($peerObj) === false) {
			throw new \Exception("Time server not connected to gateway");
		}
		
		$data				= new \stdClass();
		$data->rType		= "getTimePlease";
		$this->_recvData[]	= $peerObj->newRequest($data)->get();

		return $this->getRecvData();
	}
	public function ingressHandler($reqObj)
	{
		//this method is called when we receive a message from a peer
		$dataObj			= $reqObj->getRxData();
		$this->_recvData[]	= $dataObj;

		if (
			$dataObj instanceof \stdClass
			&& property_exists($dataObj, "rType") === true
			&& $dataObj->rType == "getTimePlease"
		) {
			$reqObj->setTxData(time())->exec();
			
		} else {
			throw new \Exception("Invalid request");
		}
	}
	protected function getClient($tls=false)
	{
		if ($this->_clientObj === null) {
			$this->_clientObj	= \MTM\WsRouter\Factories::getNodes()->getClient($this->_clientId, $this->getHost(), $this->getPort());
			$this->_clientObj->setIngressCb($this, "ingressHandler");
			
			if ($tls === true) {
				$clientObj->setSsl($this->getClientCert(), true, false, false);
			}
			
			$authData			= new \stdClass();
			$authData->secret	= "My very secret data that lets me register";
			$this->_clientObj->connect($authData);
		}
		return $this->_clientObj;
	}
	public function getRecvData()
	{
		return $this->_recvData;
	}
}