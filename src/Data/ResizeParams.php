<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read string $instance_id Server instance identifier
 * @property-read string $size Server specs/size name
 * @property-read integer $memory_mb Server video memory in megabytes
 * @property-read integer $cpu_cores Server number of CPUs
 * @property-read integer $disk_mb Server RAM size in megabytes
 * @property-read bool|null $resize_running Whether or not to allow resize of running servers
 */
class ResizeParams extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'instance_id' => ['required', 'string'],
            'size' => ['required_without:memory_mb,cpu_cores,disk_mb', 'string'],
            'memory_mb' => ['required_without:size', 'integer'],
            'cpu_cores' => ['required_without:size', 'integer'],
            'disk_mb' => ['required_without:size', 'integer'],
            'resize_running' => ['bool'],
        ]);
    }
}
