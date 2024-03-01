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
 * @property-read string|null $ip_address Server IP address
 * @property-read string $image Image name/identifier
 * @property-read string $size Server specs/size name
 * @property-read integer $memory_mb Server video memory in megabytes
 * @property-read integer $cpu_cores Server number of CPUs
 * @property-read integer $disk_mb Server RAM size in megabytes
 * @property-read string $location Server dc/location/region
 * @property-read string|null $node Server host node name
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
            'ip_address' => ['nullable', 'ip'],
            'image' => ['required', 'string'],
            'size' => ['required_without:memory_mb,cpu_cores,disk_mb', 'string'],
            'memory_mb' => ['required_without:size', 'integer'],
            'cpu_cores' => ['required_without:size', 'integer'],
            'disk_mb' => ['required_without:size', 'integer'],
            'location' => ['required', 'string'],
            'node' => ['nullable', 'string'],
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
    public function setIpAddress(?string $ipAddress)
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
    public function setSize(?string $size)
    {
        $this->setValue('size', $size);
        return $this;
    }

    /**
     * @return static $this
     */
    public function setMemoryMb(?int $memoryMb)
    {
        $this->setValue('memory_mb', $memoryMb);
        return $this;
    }

    /**
     * @return static $this
     */
    public function setCpuCores(?int $cpuCores)
    {
        $this->setValue('cpu_cores', $cpuCores);
        return $this;
    }

    /**
     * @return static $this
     */
    public function setDiskMb(?int $diskMb)
    {
        $this->setValue('disk_mb', $diskMb);
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
    public function setNode(?string $node)
    {
        $this->setValue('node', $node);
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
