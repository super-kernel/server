<?php
declare(strict_types=1);

namespace SuperKernel\Server\Enumeration;

use Swoole\WebSocket\Server;

/**
 * @link https://wiki.swoole.com/en/#/websocket_server
 */
enum WebsocketServerEvent: string
{
	/**
	 * @link https://wiki.swoole.com/en/#/websocket_server?id=onbeforehandshakeresponse
	 * @see  Server::onBeforeHandshakeResponse()
	 */
	case ON_BEFORE_HAND_SHAKE_RESPONSE = 'beforeHandshakeResponse';

	/**
	 * @link https://wiki.swoole.com/en/#/websocket_server?id=onhandshake
	 * @see  Server::onHandShake()
	 */
	case ON_HAND_SHAKE = 'handShake';

	/**
	 * @link https://wiki.swoole.com/en/#/websocket_server?id=onopen
	 * @see  Server::onOpen()
	 */
	case ON_OPEN = 'open';

	/**
	 * @link https://wiki.swoole.com/en/#/websocket_server?id=onmessage
	 * @see  Server::onMessage()
	 */
	case ON_MESSAGE = 'message';

	/**
	 * @link https://wiki.swoole.com/en/#/websocket_server?id=onrequest
	 * @see  Server::onRequest()
	 */
	case ON_REQUEST = 'request';

	/**
	 * @link https://wiki.swoole.com/en/#/websocket_server?id=ondisconnect
	 * @see  Server::onDisconnect()
	 */
	case ON_DISCONNECT = 'disconnect';
}