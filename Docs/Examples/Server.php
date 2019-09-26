<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsRouter\Docs\Examples;

class Server extends Base
{
	protected $_serverObj=null;
	protected $_recvData=array();
	
	public function run($tls=false)
	{
		$this->getServer($tls)->run();
		return $this->getRecvData();
	}
	public function registrationCb($clientObj, $authData)
	{
		//put logic here for what should happen when a new client tries to register
	
		$this->_recvData[]	= $authData;
		if (
			$authData instanceof \stdClass
			&& property_exists($authData, "secret") === true
			&& $authData->secret == "My very secret data that lets me register"
		) {
			//valid
			return true;
		} else {
			//deny access
			return false;
		}
	}
	public function getRecvData()
	{
		return $this->_recvData;
	}
	protected function getServer($tls=false)
	{
		if ($this->_serverObj === null) {
			$this->_serverObj	= \MTM\WsRouter\Factories::getNodes()->getGateway("my.gateway", $this->getHost(), $this->getPort());
			$this->_serverObj->setRegistrationCb($this, "registrationCb");
			if ($tls === true) {
				$this->_serverObj->setSsl($this->getServerCert());
			}
		}
		return $this->_serverObj;
	}
}