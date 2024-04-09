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
class ConnectionResult extends ResultData
{
    public static function rules(): Rules
    {
        return new Rules([
            'type' => ['required', 'in:' . implode(',', self::VALID_TYPES)],
            'command' => [
                'required_if:type,' . self::TYPE_SSH,
                'string',
                'regex:/^ssh [a-z0-9\.\-\_]+@[a-z0-9\.\-]+/i',
            ],
            'url' => [
                'required_if:type,' . self::TYPE_REDIRECT,
                'required_if:type,' . self::TYPE_VNC,
                'url',
            ],
            'password' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],
        ]);
    }

    public const VALID_TYPES = [
        self::TYPE_SSH,
        self::TYPE_VNC,
        self::TYPE_REDIRECT,
    ];

    /**
     * Connection is an SSH command.
     *
     * @var string
     */
    public const TYPE_SSH = 'ssh';

    /**
     * Connection is over VNC.
     *
     * @var string
     */
    public const TYPE_VNC = 'vnc';

    /**
     * Connection is in-browser via a redirect URL.
     *
     * @var string
     */
    public const TYPE_REDIRECT = 'redirect';

    /**
     * @return self $this
     */
    public function setType(string $type): self
    {
        $this->setValue('type', $type);
        return $this;
    }

    /**
     * @return self $this
     */
    public function setCommand(?string $command): self
    {
        $this->setValue('command', $command);
        return $this;
    }

    /**
     * @return self $this
     */
    public function setUrl(?string $url): self
    {
        $this->setValue('url', $url);
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
