<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read string $command SSH command
 * @property-read string|null $password
 * @property-read string|null $expires_at
 */
class SshConnectionCommandResult extends ResultData
{
    public static function rules(): Rules
    {
        return new Rules([
            'command' => ['string', 'regex:/^ssh [a-z0-9\.\-\_]+@[a-z0-9\.\-]+/i'],
            'password' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],
        ]);
    }

    /**
     * @return self $this
     */
    public function setCommand(string $command): self
    {
        $this->setValue('command', $command);
        return $this;
    }

    /**
     * @return self $this
     */
    public function setPassword(?string $password): self
    {
        $this->setValue('password', $password);
        return $this;
    }

    /**
     * @return self $this
     */
    public function setExpiresAt(?string $expires_at): self
    {
        $this->setValue('expires_at', $expires_at);
        return $this;
    }
}
