<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Data;

use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read string $instance_id Server instance identifier
 * @property-read string $state Server state e.g., ready/suspended/pending etc
 * @property-read string $label Server instance label/name
 * @property-read string|null $hostname Server hostname
 * @property-read string $ip_address Server IP address
 * @property-read string $image Image name/identifier
 * @property-read string $size Server specs/size name
 * @property-read string $location Server/node dc/location/region
 * @property-read string|null $virtualization_type
 * @property-read string|int|null $customer_identifier
 * @property-read string|null $created_at
 * @property-read string|null $updated_at
 */
class ServerInfoResult extends ResultData
{
    public static function rules(): Rules
    {
        return new Rules([
            'instance_id' => ['required', 'string'],
            'state' => ['required', 'string'],
            'label' => ['required', 'string'],
            'hostname' => ['nullable', 'alpha_dash_dot'],
            'ip_address' => ['required', 'ip'],
            'image' => ['required', 'string'],
            'size' => ['required', 'string'],
            'location' => ['required', 'string'],
            'virtualization_type' => ['nullable', 'string'],
            'customer_identifier' => ['nullable'],
            'created_at' => ['date_format:Y-m-d H:i:s'],
            'updated_at' => ['date_format:Y-m-d H:i:s'],
        ]);
    }

    /**
     * @return static $this
     */
    public function setState(string $state)
    {
        $this->setValue('state', $state);
        return $this;
    }

    /**
     * @return static $this
     */
    public function setInstanceId(string $instanceId)
    {
        $this->setValue('instance_id', $instanceId);
        return $this;
    }

    /**
     * @return static $this
     */
    public function setLabel(string $label)
    {
        $this->setValue('label', $label);
        return $this;
    }

    /**
     * @return static $this
     */
    public function setHostname(?string $hostname)
    {
        $this->setValue('hostname', $hostname);
        return $this;
    }

    /**
     * @return static $this
     */
    public function setIpAddress(string $ipAddress)
    {
        $this->setValue('ip_address', $ipAddress);
        return $this;
    }

    /**
     * @return static $this
     */
    public function setImage(string $image)
    {
        $this->setValue('image', $image);
        return $this;
    }

    /**
     * @return static $this
     */
    public function setSize(string $size)
    {
        $this->setValue('size', $size);
        return $this;
    }

    /**
     * @return static $this
     */
    public function setLocation(string $location)
    {
        $this->setValue('location', $location);
        return $this;
    }

    /**
     * @return static $this
     */
    public function setVirtualizationType(?string $type)
    {
        $this->setValue('virtualization_type', $type);
        return $this;
    }

    /**
     * @param string|int|null $customerIdentifier
     *
     * @return static $this
     */
    public function setCustomerIdentifier($customerIdentifier)
    {
        $this->setValue('customer_identifier', $customerIdentifier);
        return $this;
    }

    /**
     * @return static $this
     */
    public function setCreatedAt(string $createdAt)
    {
        $this->setValue('created_at', $createdAt);
        return $this;
    }

    /**
     * @return static $this
     */
    public function setUpdatedAt(string $updatedAt)
    {
        $this->setValue('updated_at', $updatedAt);
        return $this;
    }
}
