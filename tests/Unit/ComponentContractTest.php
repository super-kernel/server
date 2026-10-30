<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use ReflectionParameter;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Attribute\Provider;
use SuperKernel\Contract\ConfigInterface;
use SuperKernel\Contract\EventInterface;
use SuperKernel\Contract\StdoutLoggerInterface;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Constants\Mode;
use SuperKernel\Server\Contract\ServerInterface;
use SuperKernel\Server\Contract\UserProcessManagerInterface;
use SuperKernel\Server\Event\AfterMainServerCreated;
use SuperKernel\Server\Event\AfterServerCreated;
use SuperKernel\Server\Provider\CoroutineServerProvider;
use SuperKernel\Server\Provider\ServerProvider;
use SuperKernel\Server\Provider\SwooleServerConfigProvider;
use SuperKernel\Server\Provider\SwooleServerProvider;
use SuperKernel\Server\Tests\Fixture\NativeServer;
use Swoole\Coroutine\Server as CoroutineServer;
use Swoole\Server;
use Throwable;

use function array_map;

final class ComponentContractTest extends TestCase
{
    public function testDeclaresUserOwnedTypedConfiguration(): void
    {
        $configuration = new ReflectionClass(SwooleServerConfigInterface::class);

        self::assertTrue($configuration->isInterface());
        self::assertSame('bool', (string) $configuration->getMethod('enable')->getReturnType());
        self::assertSame('array', (string) $configuration->getMethod('listeners')->getReturnType());
        self::assertSame(Mode::class, (string) $configuration->getMethod('mode')->getReturnType());
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testDeclaresTheInterfaceProviderAndNativeFactoryMappings(): void
    {
        $provider = new ReflectionClass(ServerProvider::class);
        $attributes = $provider->getAttributes(Provider::class);

        self::assertTrue($provider->isFinal());
        self::assertFalse($provider->isReadOnly());
        self::assertTrue($provider->implementsInterface(ServerInterface::class));
        self::assertCount(1, $attributes);
        self::assertSame(ServerInterface::class, $attributes[0]->newInstance()->className);
        self::assertSame([
            SwooleServerConfigInterface::class,
            ContainerInterface::class,
            EventDispatcherInterface::class,
            StdoutLoggerInterface::class,
            UserProcessManagerInterface::class,
        ], self::parameterTypes($provider->getConstructor()?->getParameters() ?? []));
        self::assertFalse($provider->hasMethod('__invoke'));

        self::assertFactory(SwooleServerProvider::class, Server::class, [
            SwooleServerConfigInterface::class,
            ContainerInterface::class,
            StdoutLoggerInterface::class,
        ]);
        self::assertFactory(CoroutineServerProvider::class, CoroutineServer::class, [
            SwooleServerConfigInterface::class,
        ]);
        self::assertFactory(SwooleServerConfigProvider::class, SwooleServerConfigInterface::class, [
            ConfigInterface::class,
        ]);
    }

    /**
     * These assertions protect the public interface contract even when static types already imply it.
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function testServerBootstrapEventsBelongToServerAndExposeTheirPayloads(): void
    {
        $main = new NativeServer();
        $configuration = [
            'name' => 'main',
            'host' => '127.0.0.1',
            'port' => 9501,
        ];
        $mainEvent = new AfterMainServerCreated($main);
        $listenerEvent = new AfterServerCreated($main, $configuration);

        self::assertInstanceOf(EventInterface::class, $mainEvent);
        self::assertSame($main, $mainEvent->server());
        self::assertInstanceOf(EventInterface::class, $listenerEvent);
        self::assertSame($main, $listenerEvent->server());
        self::assertSame($configuration, $listenerEvent->configuration());
    }

    /**
     * @param class-string       $factoryClass
     * @param class-string       $serviceClass
     * @param list<class-string> $parameterTypes
     *
     * @throws Throwable When fixture setup or a supplied test callback fails
     */
    private static function assertFactory(string $factoryClass, string $serviceClass, array $parameterTypes): void
    {
        $factory = new ReflectionClass($factoryClass);
        $attributes = $factory->getAttributes(FactoryProvider::class);

        self::assertTrue($factory->isFinal());
        self::assertTrue($factory->isReadOnly());
        self::assertCount(1, $attributes);
        self::assertSame($serviceClass, $attributes[0]->newInstance()->className);
        self::assertSame($serviceClass, (string) $factory->getMethod('__invoke')->getReturnType());
        self::assertSame($parameterTypes, self::parameterTypes($factory->getMethod('__invoke')->getParameters()));
    }

    /**
     * @param list<ReflectionParameter> $parameters
     *
     * @return list<string>
     */
    private static function parameterTypes(array $parameters): array
    {
        return array_map(
            static fn(ReflectionParameter $parameter): string => (string) $parameter->getType(),
            $parameters,
        );
    }
}
