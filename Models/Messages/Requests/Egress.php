<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsRouter\Models\Messages\Requests;

class Egress extends \MTM\WsRouter\Models\Messages\Base
{
	protected $_type="egress-request";
	protected $_timeout=60000; //0 means dont wait for a response
	protected $_txTime=null;
	protected $_penPeers=array();
	protected $_rxDatas=array();
	protected $_rxErrors=array();
	
	public function getAge()
	{
		//returns in ms
		if ($this->_txTime !== null) {
			return ceil((\MTM\Utilities\Factories::getTime()->getMicroEpoch() - $this->_txTime) * 1000);
		} else {
			return 0;
		}
	}
	public function setTimeout($ms)
	{
		$this->_timeout	= $ms;
		return $this;
	}
	public function getTimeout()
	{
		return $this->_timeout;
	}
	public function getRxErrors()
	{
		return $this->_rxErrors;
	}
	public function setRxData($data, $peerObj=null, $e=null)
	{
		file_put_contents("/dev/shm/merlin.txt", "setRx: " . $this->getGuid() . "\n", FILE_APPEND);
		
		if (is_object($peerObj) === true) {
			if (array_key_exists($peerObj->getGuid(), $this->_penPeers) === true) {
				unset($this->_penPeers[$peerObj->getGuid()]);
			} else {
				throw new \Exception("Cannot set response for a peer that is not a receiver");
			}
		} elseif (count($this->_penPeers) === 1) {
			//the initial gateway connect
			$peerObj	= array_pop($this->_penPeers);
		} else {
			throw new \Exception("Cannot set response, invalid peer value");
		}
		
		if (is_object($e) === true) {
			$this->setError($e); //only the last error makes it to the throw
		}
		
		$rxData				= new \stdClass();
		$rxData->data		= $data;
		$rxData->peer		= $peerObj;
		$rxData->error		= $e;
		$this->_rxDatas[]	= $rxData;

		if ($this->getPendingCount() === 0) {
			$this->setDone();
		}
		return $this;
	}
	public function getRxData()
	{
		$recCount	= count($this->getReceivers());
		if ($recCount < 2) {
			if (count($this->_rxDatas) === 0) {
				return null;
			} else {
				return reset($this->_rxDatas)->data;
			}
		} else {
			return $this->_rxDatas;
		}
	}
	public function getPendingCount()
	{
		if ($this->getTimeout() > 0) {
			return count($this->_penPeers);
		} else {
			return 0;
		}
	}
	public function exec($throw=true)
	{
		if ($this->_txTime === null) {
			
			//egress requests are only done by clients, there is only one socket
			$stdObj				= new \stdClass();
			$stdObj->guid		= $this->getGuid();
			$stdObj->time		= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
			$stdObj->type		= "egress-request";
			$stdObj->event		= $this->getEvent();
			$stdObj->rsvp		= false;
			if ($this->getTimeout() > 0) {
				$stdObj->rsvp		= true;
			}
			$stdObj->receivers	= array();
			foreach ($this->getReceivers() as $peerObj) {
				$stdObj->receivers[]	= $peerObj->getGuid();
			}
			$stdObj->data		= base64_encode(serialize($this->getTxData()));
			
			try {
				
				$this->getParent()->getSocket()->sendMessage(json_encode($stdObj, JSON_PRETTY_PRINT));
				$this->_txTime			= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
				if ($this->getTimeout() > 0) {
					$this->getParent()->addPending($this);
					$this->_penPeers	= $this->_receivers; //we expect to hear back from each of these peers
				} else {
					$this->setDone();
				}
				
			} catch (\Exception $e) {
				$this->setError($e)->setDone();
				if ($throw === true) {
					throw $e;
				}
			}
		}
		return $this;
	}
	public function get($throw=true)
	{
		$this->exec();
		while(true) {
			if ($this->getIsDone() === false) {
				\MTM\Async\Factories::getServices()->getLoop()->runOnce();
			} elseif (is_object($this->getError()) === true && $throw === true) {
				throw $this->getError();
			} else {
				return $this->getRxData();
			}
		}
	}
}