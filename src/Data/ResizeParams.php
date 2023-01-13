<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read string $instance_id Server instance identifier
 * @property-read string $size Server specs/size name
 * @property-read bool|null $resize_running Whether or not to allow resize of running servers
 */
class ResizeParams extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'instance_id' => ['required', 'string'],
            'size' => ['required', 'string'],
            'resize_running' => ['bool'],
        ]);
    }
}
