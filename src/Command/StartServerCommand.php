<?php
declare(strict_types=1);

namespace SuperKernel\Server\Command;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use SuperKernel\Command\AbstractCommand;
use SuperKernel\Command\Annotation\Command;
use Swoole\Server;
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
		$this->container->get(Server::class)->start();

		return self::SUCCESS;
	}
}