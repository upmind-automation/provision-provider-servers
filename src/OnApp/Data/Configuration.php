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
 * @property-read int|null $timeout API request timeout
 */
class Configuration extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'hostname' => ['required', 'domain_name'],
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'timeout' => ['integer', 'min:1', 'max:180'],
        ]);
    }
}
