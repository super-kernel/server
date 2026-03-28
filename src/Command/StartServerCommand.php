<?php
declare(strict_types=1);

namespace SuperKernel\Server\Command;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SuperKernel\Command\AbstractCommand;
use SuperKernel\Command\Annotation\Command;
use SuperKernel\Config\Contract\ConfigInterface;
use SuperKernel\Server\Contract\ServerConfigInterface;
use Swoole\Coroutine;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[
	Command(
		name       : 'swoole:start',
		description: 'Start super-kernel server.',
	),
]
final class StartServerCommand extends AbstractCommand
{
	public function __construct(private readonly ContainerInterface $container)
	{
		parent::__construct($container);
	}

	/**
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function execute(InputInterface $input, OutputInterface $output): int
	{
		/* @var ServerConfigInterface $serverConfig */
		$serverConfig = $this->container->get(ConfigInterface::class)->get(ServerConfigInterface::class);

		Coroutine::set(['hook_flags' => $serverConfig->getHookFlags()]);

		$serverMode = $serverConfig->getType();

		$this->container->get($serverMode->value)->start();

		return 0;
	}
}