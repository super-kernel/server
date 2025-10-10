<?php
declare(strict_types=1);

namespace SuperKernel\Server\Enumeration;

use Swoole\Server;

/**
 * @link https://wiki.swoole.com/en/#/server/events
 */
enum ServerEvent: string
{
	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onstart
	 * @see  Server::onStart()
	 */
	case ON_START = 'start';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onbeforeshutdown
	 * @see  Server::onBeforeShutdown()
	 */
	case ON_BEFORE_SHUTDOWN = 'beforeShutdown';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onshutdown
	 * @see  Server::onShutdown()
	 */
	case ON_SHUTDOWN = 'Shutdown';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onworkerstart
	 * @see  Server::onWorkerStart()
	 */
	case ON_WORKER_START = 'workerStart';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onworkerstop
	 * @see  Server::onWorkerStop()
	 */
	case ON_WORKER_STOP = 'workerStop';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onworkerexit
	 * @see  Server::onWorkerExit()
	 */
	case ON_WORKER_EXIT = 'workerExit';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onconnect
	 * @see  Server::onConnect()
	 */
	case ON_CONNECT = 'connect';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onreceive
	 * @see  Server::onReceive()
	 */
	case ON_RECEIVE = 'receive';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onpacket
	 * @see  Server::onPacket()
	 */
	case ON_PACKET = 'packet';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onclose
	 * @see  Server::onClose()
	 */
	case ON_CLOSE = 'close';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=ontask
	 * @see  Server::onTask()
	 */
	case ON_TASK = 'task';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onfinish
	 * @see  Server::onFinish()
	 */
	case ON_FINISH = 'finish';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onpipemessage
	 * @see  Server::onPipeMessage()
	 */
	case ON_PIPE_MESSAGE = 'pipeMessage';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onworkererror
	 * @see  Server::onWorkerError()
	 */
	case ON_WORKER_ERROR = 'workerError';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onmanagerstart
	 * @see  Server::onManagerStart()
	 */
	case ON_MANAGER_START = 'managerStart';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onmanagerstop
	 * @see  Server::onManagerStop()
	 */
	case ON_MANAGER_STOP = 'managerStop';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onbeforereload
	 * @see  Server::onBeforeReload()
	 */
	case ON_BEFORE_RELOAD = 'beforeReload';

	/**
	 * @link https://wiki.swoole.com/en/#/server/events?id=onafterreload
	 * @see  Server::onAfterReload()
	 */
	case ON_AFTER_RELOAD = 'afterReload';
}