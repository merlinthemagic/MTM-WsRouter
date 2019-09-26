<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsRouter\Models\Peers;

class Gateway extends Base
{
	public function getGuid()
	{
		if ($this->_guid === null) {
			$this->_guid	= \MTM\Utilities\Factories::getGuids()->getV4()->get(false);
		}
		return $this->_guid;
	}
	public function terminate()
	{
		$this->setTerminated();
		parent::terminate();
	}
}