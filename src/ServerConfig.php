<?php
declare(strict_types=1);

namespace SuperKernel\Server;

final readonly class ServerConfig
{
    public function __construct(
        public string     $name,
        public ServerType $type,
        public string     $host,
        public int        $port,
        public int        $sockType,
    )
    {
    }
}