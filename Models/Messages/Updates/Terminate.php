<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsRouter\Models\Messages\Updates;

class Terminate extends \MTM\WsRouter\Models\Messages\Base
{
	protected $_type="update-terminate";
	protected $_txTime=null;

	public function exec($throw=false)
	{
		if ($this->_txTime === null) {
			
			//egress requests are only done by clients, there is only one socket
			$stdObj				= new \stdClass();
			$stdObj->guid		= $this->getGuid();
			$stdObj->time		= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
			$stdObj->type		= $this->getType();
			$stdObj->event		= $this->getEvent();
			$stdObj->data		= base64_encode(serialize($this->getTxData()));
			foreach ($this->getReceivers() as $peerObj) {
				$peerObj->send($stdObj, $throw);
			}
			$this->_txTime		= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
		}
		return $this;
	}
}