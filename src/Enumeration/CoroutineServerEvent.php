<?php
declare(strict_types=1);

namespace SuperKernel\Server\Enumeration;

enum CoroutineServerEvent: string
{
	case SERVER_HANDLE = 'tcp';

	case SERVER_HTTP_HANDLE = 'http';

	case SERVER_WEBSOCKET_HANDLE = 'websocket';
}