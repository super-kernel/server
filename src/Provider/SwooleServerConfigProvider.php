<?php

declare(strict_types=1);

namespace SuperKernel\Server\Provider;

use Psr\Container\ContainerExceptionInterface;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Config\Exception\ConfigurationNotFoundException;
use SuperKernel\Contract\ConfigInterface;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Enum\Mode;
use SuperKernel\Server\Enum\Protocol;
use SuperKernel\Server\Exception\InvalidServerConfigurationException;

use function array_is_list;
use function array_key_exists;
use function array_keys;
use function count;
use function get_debug_type;
use function is_array;
use function is_string;
use function sprintf;

#[FactoryProvider(SwooleServerConfigInterface::class)]
final readonly class SwooleServerConfigProvider
{
    /** @var array<string, string> */
    private const array LISTENER_FIELD_TYPES = [
        'name'       => 'string',
        'host'       => 'string',
        'port'       => 'int',
        'socketType' => 'int',
        'ssl'        => 'bool',
        'reusePort'  => 'bool',
        'protocol'   => Protocol::class,
    ];

    /**
     * @throws ContainerExceptionInterface         When the declared application source cannot be loaded
     * @throws ConfigurationNotFoundException      When no Server configuration source is declared
     * @throws InvalidServerConfigurationException When the source violates the Server configuration contract
     */
    public function __invoke(ConfigInterface $config): SwooleServerConfigInterface
    {
        $configuration = $config->get(SwooleServerConfigInterface::class)
            ?? throw new ConfigurationNotFoundException(sprintf(
                'No configuration source is registered for %s.',
                SwooleServerConfigInterface::class,
            ));

        if (!$configuration instanceof SwooleServerConfigInterface) {
            throw new InvalidServerConfigurationException(sprintf(
                'Configuration registry entry %s must implement that interface; %s provided.',
                SwooleServerConfigInterface::class,
                get_debug_type($configuration),
            ));
        }

        $mode = $configuration->mode();
        $this->validateListeners($configuration, $mode);
        $this->validateSettings($configuration->settings());

        return $configuration;
    }

    private function validateListeners(
        SwooleServerConfigInterface $configuration,
        Mode                        $mode,
    ): void
    {
        $configuredListeners = $configuration->listeners();

        if (!array_is_list($configuredListeners)) {
            throw new InvalidServerConfigurationException(
                'The Swoole server configuration must define listeners as a list.',
            );
        }

        if ([] === $configuredListeners) {
            throw new InvalidServerConfigurationException(
                Mode::Coroutine === $mode
                    ? 'Swoole coroutine mode supports exactly one listener.'
                    : 'The Swoole server configuration must define at least one listener.',
            );
        }

        if (Mode::Coroutine === $mode && 1 !== count($configuredListeners)) {
            throw new InvalidServerConfigurationException(
                'Swoole coroutine mode supports exactly one listener.',
            );
        }

        /** @var array<string, positive-int> $listenerNames */
        $listenerNames = [];

        foreach ($configuredListeners as $position => $listener) {
            $this->validateListener($listener, $position + 1);
            $name = $listener['name'];

            if (isset($listenerNames[$name])) {
                throw new InvalidServerConfigurationException(sprintf(
                    'Listener name "%s" at position %d duplicates the listener at position %d.',
                    $name,
                    $position + 1,
                    $listenerNames[$name],
                ));
            }

            $listenerNames[$name] = $position + 1;
        }

        if (Mode::Coroutine === $mode) {
            $listener = $configuredListeners[0];
            $protocol = $listener['protocol'] ?? Protocol::Raw;

            if (Protocol::Raw !== $protocol) {
                throw new InvalidServerConfigurationException(sprintf(
                    'Swoole coroutine mode supports only the raw listener protocol; listener "%s" requires "%s".',
                    $listener['name'],
                    $protocol->value,
                ));
            }

            return;
        }

        $primaryProtocol = $configuredListeners[0]['protocol'] ?? Protocol::Raw;

        foreach ($configuredListeners as $position => $listener) {
            $protocol = $listener['protocol'] ?? Protocol::Raw;

            if ($this->protocolRank($protocol) <= $this->protocolRank($primaryProtocol)) {
                continue;
            }

            throw new InvalidServerConfigurationException(sprintf(
                'Listener "%s" at position %d requires the "%s" protocol, '
                . 'which exceeds primary listener "%s" using "%s"; '
                . 'place a listener with the highest protocol capability first.',
                $listener['name'],
                $position + 1,
                $protocol->value,
                $configuredListeners[0]['name'],
                $primaryProtocol->value,
            ));
        }
    }

    private function validateListener(mixed $listener, int $position): void
    {
        if (!is_array($listener)) {
            throw new InvalidServerConfigurationException(sprintf(
                'Listener configuration at position %d must be an array; %s provided.',
                $position,
                get_debug_type($listener),
            ));
        }

        foreach ([
            'name',
            'host',
            'port',
        ] as $field) {
            if (!array_key_exists($field, $listener)) {
                throw new InvalidServerConfigurationException(sprintf(
                    'Listener configuration at position %d is missing required field "%s".',
                    $position,
                    $field,
                ));
            }
        }

        foreach ($listener as $field => $value) {
            $expectedType = self::LISTENER_FIELD_TYPES[$field] ?? null;

            if (null === $expectedType) {
                throw new InvalidServerConfigurationException(sprintf(
                    'Listener configuration at position %d contains unknown field "%s".',
                    $position,
                    $field,
                ));
            }

            if (get_debug_type($value) !== $expectedType) {
                throw new InvalidServerConfigurationException(sprintf(
                    'Listener field "%s" at position %d must be %s; %s provided.',
                    $field,
                    $position,
                    $expectedType,
                    get_debug_type($value),
                ));
            }
        }

        foreach ([
            'name',
            'host',
        ] as $field) {
            if ('' === $listener[$field]) {
                throw new InvalidServerConfigurationException(sprintf(
                    'Listener configuration at position %d must define a non-empty %s.',
                    $position,
                    $field,
                ));
            }
        }
    }

    /**
     * @param array<array-key, mixed> $settings
     */
    private function validateSettings(array $settings): void
    {
        foreach (array_keys($settings) as $name) {
            if (!is_string($name)) {
                throw new InvalidServerConfigurationException('Swoole server settings must use string keys.');
            }
        }
    }

    private function protocolRank(Protocol $protocol): int
    {
        return match ($protocol) {
            Protocol::Raw       => 0,
            Protocol::Http      => 1,
            Protocol::WebSocket => 2,
        };
    }
}
