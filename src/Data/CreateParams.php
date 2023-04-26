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
 * @property-read string|null $root_password Server root password
 * @property-read string|null $virtualization_type Virtualization type
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
            'size' => ['required', 'string'],
            'root_password' => ['nullable', 'string'],
            'virtualization_type' => ['nullable', 'string'],
        ]);
    }
}
