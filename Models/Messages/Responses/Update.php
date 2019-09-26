<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsRouter\Models\Messages\Responses;

class Update extends \MTM\WsRouter\Models\Messages\Base
{
	protected $_type=null;
	protected $_rxData=null;
	
	public function setFromObj($obj)
	{
		if (is_object($obj) === true) {
			$this->_type	= $obj->type;
			$this->setGuid($obj->guid);
			$this->setEvent($obj->event);
			$this->setRxData(unserialize(base64_decode($obj->data, true)));
		}
		return $this;
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