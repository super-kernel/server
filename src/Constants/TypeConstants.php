<?php
declare(strict_types=1);

namespace SuperKernel\Server\Constants;

enum TypeConstants
{
	case SERVER_BASE;

	case SERVER_HTTP;

	case SERVER_WEBSOCKET;
}