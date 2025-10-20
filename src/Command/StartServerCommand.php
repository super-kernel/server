<?php
declare(strict_types=1);

namespace SuperKernel\Server\Command;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SuperKernel\Server\Event\AfterMainServerStop;
use SuperKernel\Server\Event\BeforeMainServerStart;
use SuperKernel\Server\Contract\ServerInterface;
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
	public function __construct(
		private readonly ContainerInterface       $container,
		private readonly EventDispatcherInterface $eventDispatcher,
	)
	{
		parent::__construct();
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
		$serverHandler = $this->container->get(ServerInterface::class);

		$this->eventDispatcher->dispatch(new BeforeMainServerStart());

		$serverHandler->start();

		$this->eventDispatcher->dispatch(new AfterMainServerStop());

		return Command::SUCCESS;
	}
}