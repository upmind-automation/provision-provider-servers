<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\OnApp\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * OnApp API credentials.
 *
 * @property-read string $hostname Hostname
 * @property-read string username Username
 * @property-read string $password Password
 * @property-read bool $debug Whether or not to log API requests and responses
 */
class Configuration extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'hostname' => ['required', 'domain_name'],
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'debug' => ['boolean'],
        ]);
    }
}
