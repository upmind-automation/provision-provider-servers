<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * VNC Connection details.
 *
 * @property-read string|null $websocket_url VNC WebSocket URL
 * @property-read string|null $host VNC host
 * @property-read int|null $port VNC port
 * @property-read string|null $username VNC username
 * @property-read string|null $password VNC password
 */
class VncConnection extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'websocket_url' => ['url', 'starts_with:wss://', 'nullable'],
            'host' => ['required_without:websocket_url', 'string', 'nullable'],
            'port' => ['required_without:websocket_url', 'integer', 'nullable'],
            'username' => ['string', 'nullable'],
            'password' => ['required_without:websocket_url', 'string', 'nullable'],
        ]);
    }

    /**
     * @return self $this
     */
    public function setWebsocketUrl(string $url): self
    {
        $this->setValue('websocket_url', $url);
        return $this;
    }

    /**
     * @return self $this
     */
    public function setHost(string $host): self
    {
        $this->setValue('host', $host);
        return $this;
    }

    /**
     * @return self $this
     */
    public function setPort(int $port): self
    {
        $this->setValue('port', $port);
        return $this;
    }

    /**
     * @return self $this
     */
    public function setUsername(?string $username): self
    {
        $this->setValue('username', $username);
        return $this;
    }

    /**
     * @return self $this
     */
    public function setPassword(string $password): self
    {
        $this->setValue('password', $password);
        return $this;
    }
}
