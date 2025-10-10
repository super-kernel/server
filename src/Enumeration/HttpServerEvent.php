<?php
declare(strict_types=1);

namespace SuperKernel\Server\Enumeration;

use Swoole\Http\Server;

/**
 * @link https://wiki.swoole.com/en/#/http_server?id=on
 */
enum HttpServerEvent: string
{
	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onstart
	 * @see  Server::on()
	 */
	case ON_REQUEST = 'request';
}