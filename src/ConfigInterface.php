<?php
declare (strict_types=1);

namespace SuperKernel\Server;

interface ConfigInterface
{
	public function getMode(): Mode;

    /**
     * @return ServerConfig
     */
	public function getServerConfigs(): ServerConfig;
}