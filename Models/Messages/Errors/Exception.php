<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsRouter\Models\Messages\Errors;

class Exception extends \MTM\WsRouter\Models\Messages\Base
{
	protected $_type="exception";
	protected $_txTime=null;

	public function exec($sockObj, $throw=false)
	{
		if ($this->_txTime === null) {
			
			//egress requests are only done by clients, there is only one socket
			$stdObj					= new \stdClass();
			$stdObj->guid			= $this->getGuid();
			$stdObj->time			= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
			$stdObj->type			= $this->getType();
			$stdObj->event			= $this->getEvent();
			$stdObj->error			= new \stdClass();
			$stdObj->error->msg		= $this->getError()->getMessage();
			$stdObj->error->code	= $this->getError()->getCode();
			$stdObj->src			= $this->getParent()->getGuid();
			$stdObj->data			= base64_encode(serialize($this->getTxData()));
			
			try {
				$sockObj->sendMessage(json_encode($stdObj, JSON_PRETTY_PRINT));
				$this->_txTime	= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
			} catch (\Exception $e) {
				if ($throw === true) {
					throw $e;
				}
			}		
		}
		return $this;
	}
}