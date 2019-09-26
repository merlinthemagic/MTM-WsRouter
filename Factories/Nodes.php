<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsRouter\Factories;

class Nodes extends Base
{	
	//USE: $nodeObj		= \MTM\WsRouter\Factories::getNodes()->__METHOD__();
	
	public function getGateway($id, $ip, $port)
	{
		$rObj	= new \MTM\WsRouter\Models\Nodes\Gateway();
		$rObj->setConfiguration($id, $ip, $port);
		return $rObj;
	}
	public function getClient($id, $ip, $port)
	{
		$rObj	= new \MTM\WsRouter\Models\Nodes\Client();
		$rObj->setConfiguration($id, $ip, $port);
		return $rObj;
	}
}