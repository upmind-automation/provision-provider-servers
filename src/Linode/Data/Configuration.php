<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Linode\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read string $access_token
 */
class Configuration extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'access_token' => ['required', 'string'],
        ]);
    }
}
