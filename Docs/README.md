### What is this?

A way use websockets as a router for messages

Run gateway:
$testObj	= new \MTM\WsRouter\Docs\Examples\Server();
$rData		= $testObj->run();

Run client 1:
$testObj	= new \MTM\WsRouter\Docs\Examples\Client();
$rData		= $testObj->runAsTimeServer();

Run client 2:
$testObj	= new \MTM\WsRouter\Docs\Examples\Client();
$rData		= $testObj->runAsTimeClient();

Terminate server:
$testObj	= new \MTM\WsRouter\Docs\Examples\Client();
$testObj->terminateGateway();
