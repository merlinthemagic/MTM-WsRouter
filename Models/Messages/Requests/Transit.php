<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsRouter\Models\Messages\Requests;

class Transit extends \MTM\WsRouter\Models\Messages\Base
{
	protected $_type=null;
	protected $_txTime=null;
	protected $_rsvp=false;
	protected $_srcPeerObj=null;
	
	public function setFromObj($obj, $srcPeer=null)
	{
		if (is_object($obj) === true) {
			
			if ($obj->type == "egress-request" || $obj->type == "egress-response") {

				$this->_type	= $obj->type;
				$this->setGuid($obj->guid);
				$this->setEvent($obj->event);
				foreach ($obj->receivers as $recvGuid) {
					$this->addReceiver($this->getParent()->getPeerFromGuid($recvGuid));
				}
				//do not want to unserialize, could be anything. 
				//Including a custom objs that we do not have a class for
				$this->setTxData($obj->data);
				
				if (property_exists($obj, "rsvp") === true) {
					$this->setRsvp($obj->rsvp);
				}
				if (property_exists($obj, "error") == true && $obj->error !== null) {
					$this->setError(new \Exception($obj->error->msg, $obj->error->code));
				}
				
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
	public function setRsvp($bool)
	{
		$this->_rsvp	= $bool;
		return $this;
	}
	public function getRsvp()
	{
		return $this->_rsvp;
	}
	public function exec()
	{
		if ($this->_txTime === null) {
			
			//relay the data from another peer
			$stdObj					= new \stdClass();
			$stdObj->guid			= $this->getGuid();
			$stdObj->time			= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
			$stdObj->type			= $this->getType();
			$stdObj->event			= $this->getEvent();
			$stdObj->rsvp			= $this->getRsvp();
			$stdObj->srcPeer		= $this->getSrcPeer()->getGuid();
			$stdObj->error			= null;
			if (is_object($this->getError()) === true) {
				$stdObj->error			= new \stdClass();
				$stdObj->error->msg		= $this->getError()->getMessage();
				$stdObj->error->code	= $this->getError()->getCode();
			}
			$stdObj->data		= $this->getTxData(); //not encoded
			foreach ($this->getReceivers() as $peerObj) {
				$peerObj->send($stdObj);
			}
			$this->_txTime		= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
		}
		return $this;
	}
}