<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Virtuozzo\Helper;

use Upmind\ProvisionBase\Exception\ProvisionFunctionError;

class SocketClient
{
    /** @var resource */
    protected $client;

    protected string $host;

    protected int $port;

    protected int $connectionTimeout = 10;

    public function __construct(string $host, int $port, int $timeout)
    {
        $this->host = $host;
        $this->port = $port;
        $this->connectionTimeout = $timeout;
    }

    protected function makeAddress(): string
    {
        return sprintf("tcp://%s:%s", $this->host, $this->port);
    }

    public function connect(int $flags): void
    {
        if (
            false === $client = stream_socket_client(
                $this->makeAddress(),
                $errorCode,
                $errorMessage,
                $this->connectionTimeout,
                $flags,
            )
        ) {
            throw ProvisionFunctionError::create("Can't connect to socket: $errorCode, $errorMessage");
        }

        if (!is_resource($client) || $errorCode !== 0) {
            throw ProvisionFunctionError::create("Can't connect to socket: $errorCode, $errorMessage");
        }

        $this->client = $client;
    }


    /**
     * @param int $length
     *
     * @return string
     * @throws ProvisionFunctionError
     */
    public function request(string $xml, int $length = 4096): array
    {
        $resultXml = [];

        $written = $this->write($xml);

        while (!feof($this->client)) {
            if ($written > 0) {
                if (false === $read = fread($this->client, $length)) {
                    throw ProvisionFunctionError::create("Error occurred on reading from the socket");
                }

                $resultXml[] = $read;
            }
        }

        return $resultXml;
    }

    /**
     * @param string $content
     *
     * @return int
     * @throws ProvisionFunctionError
     */
    public function write(string $content): int
    {
        if (false === $written = fwrite($this->client, $content . "\0")) {
            throw ProvisionFunctionError::create("Error occurred on writing to the socket");
        }

        return $written;
    }

    /**
     * @return void
     * @throws \ProvisionFunctionError
     */
    public function close(): void
    {
        if (!is_null($this->client)) {
            if (false === fclose($this->client)) {
                throw ProvisionFunctionError::create("Can't close the socket");
            };
        }
    }
}
