<?php

declare(strict_types=1);

namespace SuperKernel\Server\Provider;

use InvalidArgumentException;
use LogicException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Contract\AnnotationCollectorInterface;
use SuperKernel\Server\Attribute\UserProcess;
use SuperKernel\Server\Contract\UserProcessInterface;
use SuperKernel\Server\Contract\UserProcessManagerInterface;
use SuperKernel\Server\Manager\UserProcessManager;

use function is_a;

#[FactoryProvider(UserProcessManagerInterface::class)]
final class UserProcessManagerProvider
{
    /**
     * @throws InvalidArgumentException    if a marked class or a pipe type is invalid
     * @throws ContainerExceptionInterface if the container cannot resolve a task with nonzero workNums
     * @throws LogicException              if names collide or registration exceeds 1024 workers
     */
    public function __invoke(
        AnnotationCollectorInterface $collector,
        ContainerInterface           $container,
    ): UserProcessManagerInterface
    {
        $manager = new UserProcessManager();
        foreach ($collector->getClassesWithAttribute(UserProcess::class) as $class => $attributes) {
            if (!is_a($class, UserProcessInterface::class, true)) {
                throw new InvalidArgumentException($class . ' must implement ' . UserProcessInterface::class . '.');
            }
            $task = null;
            foreach ($attributes as $metadata) {
                $attribute = $metadata->attribute();
                if (0 === $attribute->workNums) {
                    continue;
                }
                $task ??= $container->get($class);
                $manager->addBatch(
                    $attribute->workNums,
                    $task,
                    $attribute->enableCoroutine,
                    $attribute->name ?? $class,
                );
            }
        }
        return $manager;
    }
}
