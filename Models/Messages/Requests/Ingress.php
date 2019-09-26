<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsRouter\Models\Messages\Requests;

class Ingress extends \MTM\WsRouter\Models\Messages\Base
{
	protected $_type="ingress-request";
	protected $_rxTime=null;
	protected $_rxData=null;
	protected $_txTime=null;
	protected $_rsvp=true;
	protected $_sockObj=null;
	
	public function setFromObj($obj, $srcPeer=null)
	{
		if (is_object($obj) === true) {
			
			if ($obj->type == "egress-request") {
				$this->_rxTime		= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
				$this->setGuid($obj->guid);
				$this->setEvent($obj->event);
				
				$this->setRsvp($obj->rsvp);
				$this->setRxData(unserialize(base64_decode($obj->data, true)));

			} else {
				throw new \Exception("Not handled for type: " . $obj->type);
			}
		}
		if (is_object($srcPeer) === true) {
			$this->setSrcPeer($srcPeer);
		}
		return $this;
	}
	public function setSrcPeer($obj)
	{
		$this->_srcPeerObj	= $obj;
		return $this;
	}
	public function getSrcPeer()
	{
		return $this->_srcPeerObj;
	}
	public function setRxData($data)
	{
		$this->_rxData	= $data;
		return $this;
	}
	public function getRxData()
	{
		return $this->_rxData;
	}
	public function setRsvp($bool)
	{
		$this->_rsvp	= $bool;
		return $this;
	}
	public function getRsvp()
	{
		return $this->_rsvp;
	}
	public function exec($throw=true)
	{
		if ($this->_txTime === null) {
			if ($this->getRsvp() === true) {
				//respond to the egress-request made by another peer
				$stdObj					= new \stdClass();
				$stdObj->guid			= $this->getGuid();
				$stdObj->time			= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
				$stdObj->type			= "egress-response";
				//gateway will override, but if coming from gateway it needed
				$stdObj->srcPeer		= $this->getParent()->getGuid();
				$stdObj->event			= $this->getEvent();
				$stdObj->error			= null;
				if (is_object($this->getError()) === true) {
					$stdObj->error			= new \stdClass();
					$stdObj->error->msg		= $this->getError()->getMessage();
					$stdObj->error->code	= $this->getError()->getCode();
				}
				$stdObj->receivers		= array();
				$stdObj->receivers[]	= $this->getSrcPeer()->getGuid();
				
				$stdObj->data			= base64_encode(serialize($this->getTxData()));
				$this->getSrcPeer()->send($stdObj, $throw);
			}
			$this->_txTime		= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
		}
		return $this;
	}
}