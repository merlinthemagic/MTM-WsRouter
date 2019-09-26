<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsRouter\Docs\Examples;

class Base
{
	protected $_cStore=array();
	
	public function getHost()
	{
		return "127.0.0.1";
	}
	public function getPort()
	{
		return 5896;
	}
	private function getCerts()
	{
		if (array_key_exists(__FUNCTION__, $this->_cStore) === false) {
			$this->_cStore[__FUNCTION__]	= new \MTM\Certs\Docs\Examples\Certificates();
		}
		return $this->_cStore[__FUNCTION__];
	}
	public function getServerCert()
	{
		return $this->getCerts()->getServer1();
	}
	public function getClientCert()
	{
		return $this->getCerts()->getClient1();
	}
}