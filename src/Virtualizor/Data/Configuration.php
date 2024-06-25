<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Virtualizor\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * Virtualizor API credentials.
 *
 * @property-read string $hostname Hostname
 * @property-read int|string|null $port Port
 * @property-read string $api_key API Key
 * @property-read string $api_password API Password
 * @property-read string $location_type What location refers to: geographic location or host server
 * @property-read string|null $default_virtualization_type Default virtualization type e.g., kvm
 * @property-read bool|null $ignore_ssl_errors Whether or not to ignore SSL errors
 */
class Configuration extends DataSet
{
    public const LOCATION_TYPE_SERVER = 'server';
    public const LOCATION_TYPE_SERVER_GROUP = 'server_group';
    public const LOCATION_TYPE_GEOGRAPHIC = 'geographic';

    public const LOCATION_TYPES = [
        self::LOCATION_TYPE_SERVER,
        self::LOCATION_TYPE_SERVER_GROUP,
        self::LOCATION_TYPE_GEOGRAPHIC,
    ];

    public static function rules(): Rules
    {
        return new Rules([
            'hostname' => ['required', 'domain_name'],
            'port' => ['nullable', 'numeric'],
            'api_key' => ['required', 'string'],
            'api_password' => ['required', 'string'],
            'location_type' => ['required', 'in:' . implode(',', self::LOCATION_TYPES)],
            'default_virtualization_type' => ['nullable', 'in:' . implode(',', [
                'kvm',
                'xen',
                'xenhvm',
                'xcp',
                'xcphvm',
                'openvz',
                'lxc',
                'vzo',
                'vzk',
                'proxo',
                'proxk',
                'proxl',
            ])],
            'ignore_ssl_errors' => ['boolean'],
        ]);
    }
}
