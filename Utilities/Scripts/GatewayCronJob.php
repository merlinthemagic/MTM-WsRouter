<?php
//© 2019 Martin Peter Madsen

$gwObj		= \MTM\WsRouter\Factories::getNodes()->getGateway("myGateway", "10.16.65.150", 5678);
if ($gwObj->isRunning() === false) {
	
	//called for every new registration method must accept ($cObj, $msgObj) and return a boolean
	$regObj		= new \MyClass\Something();
	$method		= "registrationValidator";
	$gwObj->run($regObj, $method);
}