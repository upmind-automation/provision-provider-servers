<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\SolusVM\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * SolusVM API credentials.
 *
 * @property-read string $hostname Master server hostname
 * @property-read int|string|null $port Master server port
 * @property-read string $api_id API user ID
 * @property-read string $api_key API user key
 * @property-read string $location_type What entity location refers to: node or node group
 * @property-read string|null $default_virtualization_type One of: openvz, xen, xen hvm, kvm
 * @property-read bool|null $single_server_owner Whether all servers should be created under a single username
 * @property-read string|null $server_owner_username Username to own new servers if `single_server_owner` is true
 * @property-read bool|null $debug Whether or not to log API requests and responses
 */
class Configuration extends DataSet
{
    public const VIRTUALIZATION_TYPE_OPENVZ = 'openvz';
    public const VIRTUALIZATION_TYPE_XEN = 'xen';
    public const VIRTUALIZATION_TYPE_XEN_HVM = 'xen hvm';
    public const VIRTUALIZATION_TYPE_KVM = 'kvm';

    public const VIRTUALIZATION_TYPES = [
        self::VIRTUALIZATION_TYPE_OPENVZ,
        self::VIRTUALIZATION_TYPE_XEN,
        self::VIRTUALIZATION_TYPE_XEN_HVM,
        self::VIRTUALIZATION_TYPE_KVM,
    ];

    public const LOCATION_TYPE_NODE = 'node';
    public const LOCATION_TYPE_NODE_GROUP = 'node_group';

    public const LOCATION_TYPES = [
        self::LOCATION_TYPE_NODE,
        self::LOCATION_TYPE_NODE_GROUP,
    ];

    public static function rules(): Rules
    {
        return new Rules([
            'hostname' => ['required', 'domain_name'],
            'port' => ['nullable', 'numeric'],
            'api_id' => ['required', 'string'],
            'api_key' => ['required', 'string'],
            'location_type' => ['required', 'in:' . implode(',', self::LOCATION_TYPES)],
            'default_virtualization_type' => ['nullable', 'in:' . implode(',', self::VIRTUALIZATION_TYPES)],
            'single_server_owner' => ['nullable', 'boolean'],
            'server_owner_username' => ['required_if:single_server_owner,1', 'nullable', 'string'],
            'debug' => ['nullable', 'boolean'],
        ]);
    }
}
