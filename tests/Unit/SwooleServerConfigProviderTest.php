<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use SuperKernel\Config\Exception\ConfigurationNotFoundException;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Enum\Mode;
use SuperKernel\Server\Enum\Protocol;
use SuperKernel\Server\Exception\InvalidServerConfigurationException;
use SuperKernel\Server\Provider\SwooleServerConfigProvider;
use SuperKernel\Server\Tests\Fixture\ConfigurationRegistry;
use SuperKernel\Server\Tests\Fixture\SwooleServerConfig;
use Throwable;

use const SWOOLE_SOCK_TCP6;

final class SwooleServerConfigProviderTest extends TestCase
{
    /** @return iterable<string, array{array<array-key, mixed>, string}> */
    public static function invalidListenerFields(): iterable
    {
        yield 'name type' => [
            [
                'name' => 12,
            ],
            '"name"',
        ];
        yield 'host type' => [
            [
                'host' => false,
            ],
            '"host"',
        ];
        yield 'numeric string port' => [
            [
                'port' => '9501',
            ],
            '"port"',
        ];
        yield 'socket type' => [
            [
                'socketType' => 'tcp',
            ],
            '"socketType"',
        ];
        yield 'ssl type' => [
            [
                'ssl' => 1,
            ],
            '"ssl"',
        ];
        yield 'reuse port type' => [
            [
                'reusePort' => 'false',
            ],
            '"reusePort"',
        ];
        yield 'protocol string' => [
            [
                'protocol' => 'http',
            ],
            '"protocol"',
        ];
        yield 'null optional value' => [
            [
                'protocol' => null,
            ],
            '"protocol"',
        ];
        yield 'unknown field' => [
            [
                'protcol' => Protocol::Http,
            ],
            'unknown field "protcol"',
        ];
        yield 'positional field' => [
            [
                0 => 'unexpected',
            ],
            'unknown field "0"',
        ];
        yield 'empty host' => [
            [
                'host' => '',
            ],
            'non-empty host',
        ];
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRequiresARegisteredApplicationConfigurationSource(): void
    {
        $this->expectException(ConfigurationNotFoundException::class);
        $this->expectExceptionMessageIsOrContains(SwooleServerConfigInterface::class);

        new SwooleServerConfigProvider()(new ConfigurationRegistry([]));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testReturnsTheValidatedApplicationObject(): void
    {
        $listener = [
            'name' => 'main',
            'host' => '127.0.0.1',
            'port' => 9501,
        ];
        $settings = [
            'worker_num' => 2,
        ];
        $source = new SwooleServerConfig(
            Mode::Process,
            [
                $listener,
            ],
            $settings,
        );
        $configuration = $this->create($source);

        self::assertSame($source, $configuration);
        self::assertSame(Mode::Process, $configuration->mode());
        self::assertTrue($configuration->enable());
        self::assertSame([
            $listener,
        ], $configuration->listeners());
        self::assertSame($settings, $configuration->settings());
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testPreservesExplicitListenerOptions(): void
    {
        $configuration = $this->create(new SwooleServerConfig(Mode::Process, [
            [
                'name'       => 'https',
                'host'       => '::1',
                'port'       => 0,
                'socketType' => SWOOLE_SOCK_TCP6,
                'ssl'        => true,
                'reusePort'  => true,
                'protocol'   => Protocol::WebSocket,
            ],
        ]));
        $listener = $configuration->listeners()[0];

        self::assertSame('https', $listener['name']);
        self::assertSame('::1', $listener['host']);
        self::assertSame(0, $listener['port']);
        self::assertSame(SWOOLE_SOCK_TCP6, $listener['socketType']);
        self::assertTrue($listener['ssl']);
        self::assertTrue($listener['reusePort']);
        self::assertSame(Protocol::WebSocket, $listener['protocol']);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testPreservesTheApplicationSourcesOwnReferenceSemantics(): void
    {
        $name = 'main';
        $configuration = $this->create(new SwooleServerConfig(Mode::Base, [
            [
                'name' => &$name,
                'host' => '127.0.0.1',
                'port' => 0,
            ],
        ]));
        $name = 'changed';

        self::assertSame('changed', $configuration->listeners()[0]['name']);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsAnIncompatibleRegisteredSource(): void
    {
        $this->expectException(InvalidServerConfigurationException::class);
        $this->expectExceptionMessageIsOrContains(SwooleServerConfigInterface::class);

        new SwooleServerConfigProvider()(new ConfigurationRegistry([
            SwooleServerConfigInterface::class => new stdClass(),
        ]));
    }

    /**
     * @param array<array-key, mixed> $fields
     *
     * @throws Throwable When fixture setup or the operation under test throws
     */
    #[DataProvider('invalidListenerFields')]
    public function testRejectsInvalidListenerFields(array $fields, string $message): void
    {
        $this->expectException(InvalidServerConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->create(new SwooleServerConfig(Mode::Base, [
            $fields + [
                'name' => 'main',
                'host' => '127.0.0.1',
                'port' => 0,
            ],
        ]));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRequiresAllMandatoryListenerFields(): void
    {
        foreach ([
            'name',
            'host',
            'port',
        ] as $field) {
            $listener = [
                'name' => 'main',
                'host' => '127.0.0.1',
                'port' => 0,
            ];
            unset($listener[$field]);

            try {
                $this->create(new SwooleServerConfig(Mode::Base, [
                    $listener,
                ]));
                self::fail('A listener with a missing required field must be rejected.');
            } catch (InvalidServerConfigurationException $exception) {
                self::assertStringContainsString('missing required field "' . $field . '"', $exception->getMessage());
            }
        }
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsListenerObjectsAsApplicationInput(): void
    {
        $this->expectException(InvalidServerConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('must be an array');

        $this->create(new SwooleServerConfig(Mode::Base, [
            new stdClass(),
        ]));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRequiresNamedServerSettings(): void
    {
        $this->expectException(InvalidServerConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('settings must use string keys');

        $this->create(new SwooleServerConfig(Mode::Base, settings: [
            4,
        ]));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testPreservesTheDisabledServerSetting(): void
    {
        $configuration = $this->create(new SwooleServerConfig(Mode::Base, enable: false));

        self::assertFalse($configuration->enable());
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRequiresAListenerList(): void
    {
        $this->expectException(InvalidServerConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('must define listeners as a list');

        $this->create(new SwooleServerConfig(Mode::Process, [
            1 => [
                'name' => 'main',
                'host' => '127.0.0.1',
                'port' => 9501,
            ],
        ]));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRequiresAtLeastOneNativeListener(): void
    {
        $this->expectException(InvalidServerConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('must define at least one listener');

        $this->create(new SwooleServerConfig(Mode::Process, []));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsAnIncompatibleListenerEntry(): void
    {
        $this->expectException(InvalidServerConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('must be an array');

        $this->create(new SwooleServerConfig(Mode::Process, [
            'invalid',
        ]));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRequiresEveryListenerToHaveANonEmptyName(): void
    {
        $this->expectException(InvalidServerConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('must define a non-empty name');

        $this->create(new SwooleServerConfig(Mode::Process, [
            [
                'name' => '',
                'host' => '127.0.0.1',
                'port' => 9501,
            ],
        ]));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRequiresUniqueListenerNames(): void
    {
        $this->expectException(InvalidServerConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Listener name "http" at position 2 duplicates');

        $this->create(new SwooleServerConfig(Mode::Process, [
            [
                'name' => 'http',
                'host' => '0.0.0.0',
                'port' => 9501,
            ],
            [
                'name' => 'http',
                'host' => '127.0.0.1',
                'port' => 9502,
            ],
        ]));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRequiresThePrimaryListenerToHaveTheHighestProtocolCapability(): void
    {
        $this->expectException(InvalidServerConfigurationException::class);
        $this->expectExceptionMessageIsOrContains(
            'Listener "http" at position 2 requires the "http" protocol, which exceeds primary listener "tcp"',
        );

        $this->create(new SwooleServerConfig(Mode::Process, [
            [
                'name' => 'tcp',
                'host' => '127.0.0.1',
                'port' => 9501,
            ],
            [
                'name'     => 'http',
                'host'     => '127.0.0.1',
                'port'     => 9502,
                'protocol' => Protocol::Http,
            ],
        ]));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testCoroutineModeRequiresExactlyOneRawListener(): void
    {
        try {
            $this->create(new SwooleServerConfig(Mode::Coroutine, [
                [
                    'name' => 'primary',
                    'host' => '127.0.0.1',
                    'port' => 9501,
                ],
                [
                    'name' => 'secondary',
                    'host' => '127.0.0.1',
                    'port' => 9502,
                ],
            ]));
            self::fail('Coroutine mode must reject multiple listeners.');
        } catch (InvalidServerConfigurationException $exception) {
            self::assertStringContainsString('exactly one listener', $exception->getMessage());
        }

        $this->expectException(InvalidServerConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('supports only the raw listener protocol');

        $this->create(new SwooleServerConfig(Mode::Coroutine, [
            [
                'name'     => 'http',
                'host'     => '127.0.0.1',
                'port'     => 9501,
                'protocol' => Protocol::Http,
            ],
        ]));
    }

    /** @throws Throwable When fixture setup or a supplied test callback fails */
    private function create(SwooleServerConfig $configuration): SwooleServerConfigInterface
    {
        return new SwooleServerConfigProvider()(new ConfigurationRegistry([
            SwooleServerConfigInterface::class => $configuration,
        ]));
    }
}
