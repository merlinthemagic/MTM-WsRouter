<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsRouter\Models\Messages\Responses;

class Egress extends \MTM\WsRouter\Models\Messages\Base
{
	protected $_type="egress-response";
	protected $_srcPeerObj=null;
	protected $_rxData=null;
	
	public function setFromObj($obj)
	{
		if (is_object($obj) === true) {

			if ($obj->type == "egress-response") {
				$this->setGuid($obj->guid);
				$this->setEvent($obj->event);
				if ($obj->error !== null) {
					$this->setError(new \Exception($obj->error->msg, $obj->error->code));
				}
				$this->setRxData(unserialize(base64_decode($obj->data, true)));
				
				//on gateway responses we do not have a peer
				$this->_srcPeerObj	= $this->getParent()->getPeerFromGuid($obj->srcPeer, false);
			
			} else {
				throw new \Exception("Not handled for type: " . $msgObj->type);
			}
		}
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
}