<?php
declare(strict_types=1);

namespace SuperKernel\Server\Command;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SuperKernel\Server\Event\AfterMainServerStop;
use SuperKernel\Server\Event\BeforeMainServerStart;
use SuperKernel\Server\Contract\ServerInterface;
use SuperKernel\Server\Event\BeforeServerStart;
use SuperKernel\Server\ServerConfigInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[
	AsCommand(
		name       : 'start',
		description: 'Start super-kernel server.',
	),
]
final class StartServerCommand extends Command
{
	private ?ServerInterface $server = null {
		get => $this->server ??= $this->container->get(ServerInterface::class);
	}

	private ?ServerConfigInterface $serverConfig = null {
		get => $this->serverConfig ??= $this->container->get(ServerConfigInterface::class);
	}

	private ?EventDispatcherInterface $eventDispatcher = null {
		get => $this->eventDispatcher ??= $this->container->get(EventDispatcherInterface::class);
	}

	public function __construct(private readonly ContainerInterface $container)
	{
		parent::__construct();
	}

	/**
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->eventDispatcher->dispatch(new BeforeMainServerStart());

		foreach ($this->serverConfig->getServers() as $config) {
			$server = $this->server->addServer($config);

			$this->eventDispatcher->dispatch(new BeforeServerStart($server, $config));
		}

		$this->server->start();

		$this->eventDispatcher->dispatch(new AfterMainServerStop());

		return Command::SUCCESS;
	}
}