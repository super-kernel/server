<?php

declare(strict_types=1);

namespace SuperKernel\Server\Config;

use SuperKernel\Server\Constants\Protocol;
use SuperKernel\Server\Exception\InvalidServerConfigurationException;

use function array_key_exists;
use function get_debug_type;
use function is_array;
use function sprintf;

/**
 * Validates the fields of one configured listener array without normalizing it.
 *
 * @internal
 */
final class ListenerConfigurationValidator
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
     * @param int $position one-based listener position used in failure messages
     *
     * @throws InvalidServerConfigurationException When the listener is not an array, misses a required field,
     *                                             contains an unknown or mistyped field, or has an empty name or host
     */
    public static function validate(mixed $listener, int $position): void
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
}
