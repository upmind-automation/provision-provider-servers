<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read string|int|null $customer_identifier Existing customer id
 * @property-read string $email Customer email address
 * @property-read string $label Server instance label/name
 * @property-read string $location Server dc/location/region
 * @property-read string $image Image name/identifier
 * @property-read string $size Server specs/size name
 * @property-read integer $memory_mb Server video memory in megabytes
 * @property-read integer $cpu_cores Server number of CPUs
 * @property-read integer $disk_mb Server RAM size in megabytes
 * @property-read string|null $root_password Server root password
 * @property-read string|null $virtualization_type Virtualization type
 * @property-read string[]|null $software Software to install
 * @property-read string[]|null $licenses Licenses to create
 * @property-read array|null $metadata Additional metadata
 */
class CreateParams extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'customer_identifier' => ['nullable'],
            'email' => ['required', 'email'],
            'label' => ['required', 'alpha_dash_dot'],
            'location' => ['required', 'string'],
            'image' => ['required', 'string'],
            'size' => ['required_without:memory_mb,cpu_cores,disk_mb', 'string'],
            'memory_mb' => ['required_without:size', 'integer'],
            'cpu_cores' => ['required_without:size', 'integer'],
            'disk_mb' => ['required_without:size', 'integer'],
            'root_password' => ['nullable', 'string'],
            'virtualization_type' => ['nullable', 'string'],
            'software' => ['nullable', 'array'],
            'licenses' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);
    }
}
