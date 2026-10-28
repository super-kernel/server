<?php

declare(strict_types=1);

namespace SuperKernel\Server\Listener;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use SuperKernel\Attribute\Listener;
use SuperKernel\Contract\ConfigInterface;
use SuperKernel\Contract\ListenerInterface;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Contract\ServerInterface;
use SuperKernel\Server\Exception\InvalidServerConfigurationException;
use SuperKernel\Swoole\Event\MainProcessCreated;
use UnexpectedValueException;

use function get_debug_type;
use function sprintf;

#[Listener]
final readonly class MainProcessCreatedListener implements ListenerInterface
{
    public function __construct(private ContainerInterface $container, private ConfigInterface $config)
    {
    }

    public function listen(): array
    {
        return [
            MainProcessCreated::class,
        ];
    }

    /**
     * Server lookup and startup run in a child; MainProcess reports child failure after shutdown and reaping.
     *
     * @psalm-param MainProcessCreated $event
     *
     * @throws ContainerExceptionInterface         When declared configuration cannot be resolved or validated
     * @throws InvalidServerConfigurationException When the container returns incompatible configuration
     */
    public function process(object $event): void
    {
        if (!$this->config->has(SwooleServerConfigInterface::class)) {
            return;
        }

        $configuration = $this->container->get(SwooleServerConfigInterface::class);
        if (!$configuration instanceof SwooleServerConfigInterface) {
            throw new InvalidServerConfigurationException(sprintf(
                'Configuration container entry %s must implement that interface; %s provided.',
                SwooleServerConfigInterface::class,
                get_debug_type($configuration),
            ));
        }

        if (!$configuration->enable()) {
            return;
        }

        $event->mainProcess->addProcess(function (): void {
            $server = $this->container->get(ServerInterface::class);
            if (!$server instanceof ServerInterface) {
                throw new UnexpectedValueException('Invalid ServerInterface container entry.');
            }
            $server->start();
        });
    }
}
