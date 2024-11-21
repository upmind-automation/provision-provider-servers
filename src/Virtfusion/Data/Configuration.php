<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Virtfusion\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * Virtfusion API credentials.
 *
 * @property-read string $hostname Hostname
 * @property-read string $api_token API token
 * @property-read int $hypervisorId Hypervisor ID
 * @property-read int|null $timeout API request timeout
 */
class Configuration extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'hostname' => ['required', 'domain_name'],
            'api_token' => ['required', 'string'],
            'hypervisorId' => ['required', 'integer'],
            'timeout' => ['integer', 'min:1', 'max:180'],
        ]);
    }
}
