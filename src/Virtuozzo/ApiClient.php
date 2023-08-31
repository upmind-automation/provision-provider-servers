<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Virtuozzo;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionBase\Helper;
use Upmind\ProvisionProviders\Servers\Data\CreateParams;
use Upmind\ProvisionProviders\Servers\Data\ResizeParams;
use Upmind\ProvisionProviders\Servers\Virtuozzo\Data\Configuration;
use Upmind\ProvisionProviders\Servers\Virtuozzo\Helper\SocketClient;
use Upmind\ProvisionProviders\Servers\Virtuozzo\Helper\XMLCommand;
use Log;

class ApiClient
{
    protected Configuration $configuration;

    protected bool $connected = false;

    protected SocketClient $client;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;

        $this->client = new SocketClient($this->configuration->hostname, 4433, 10);

        $this->connect();
    }

    public function __destruct()
    {
        if ($this->connected) {
            $this->client->close();
        }

        $this->connected = false;
    }

    private function connect(): void
    {
        if (!$this->connected) {
            $this->client->connect(
                STREAM_CLIENT_CONNECT
            );
        }

        $this->connected = true;
    }

    private function makeRequest($xml): SimpleXMLElement
    {
        $login = $this->login();

        $resultXml = $this->client->request($login . "\0" . $xml);

        if ($resultXml == []) {
            throw new RuntimeException('Empty provider api response');
        }

        return $this->parseResponseData($resultXml[1]);
    }

    /**
     * @throws ProvisionFunctionError
     */
    private function parseResponseData(string $result): SimpleXMLElement
    {
        try {
            $xml = new SimpleXMLElement($result);
        } catch (\Exception $e) {
            throw ProvisionFunctionError::create("Can't parse response", $e);
        }

        // Check the XML for errors
        if ($error = $this->parseXmlError($xml->xpath('//ns1:message'))) {
            throw ProvisionFunctionError::create($error)
                ->withData([
                    'response' => $xml,
                ]);
        }

        return $xml;
    }

    private function parseXmlError(array $errors): ?string
    {
        $result = [];

        foreach ($errors as $error) {
            if (Str::contains($error, 'System errors')) {
                $result[] = $error;
            }
        }

        if ($result) {
            return sprintf("Provider API Error: %s", implode(', ', $result));
        }

        return null;
    }

    /**
     * @throws ProvisionFunctionError
     */
    private function login(): string
    {
        $username = base64_encode($this->configuration->username);
        $pass = base64_encode($this->configuration->password);

        $login = new XMLCommand();

        return $login->makeLogin($username, $pass);
    }

    public function getServerInfo(string $serverId): ?array
    {
        $info = new XMLCommand();
        $xml = $info->makeServerInfo($serverId);

        $response = $this->makeRequest($xml);

        $type = $response->origin;

        $env = $response->data->{$type}->env;

        $state = (string)$env->status->state ?? '0';

        return [
            'instance_id' => (string)$env->eid ?? 'Unknown',
            'state' => $this->parseState($state),
            'label' => (string)$env->virtual_config->name ?? 'Unknown',
            'ip_address' => (string)$env->virtual_config->address->ip ?? '0.0.0.0',
            'image' => (string)$env->virtual_config->os_template->name ?? 'Unknown',
            'memory_mb' => (int)$env->virtual_config->video_memory_size ?? 0,
            'cpu_cores' => (int)$env->virtual_config->cpu_count ?? 0,
            'disk_mb' => (int)$env->virtual_config->memory_size ?? 0,
            'location' => (string)$env->virtual_config->home_path ?? 'Unknown',
            'node' => (string)$env->virtual_config->hostname ?? 'Unknown',
            'virtualization_type' => $type ?? 'Unknown',
        ];
    }


    private function parseState(string $state): string
    {
        $states = [
            '0' => 'unknown',
            '1' => 'non-existent',
            '2' => 'config',
            '3' => 'down',
            '4' => 'mounted',
            '5' => 'suspended',
            '6' => 'running',
            '7' => 'repairing',
            '8' => 'license violation'
        ];

        return $states[$state];
    }

    public function create(CreateParams $params): string
    {
        $create = new XMLCommand();

        if (isset($params->virtualization_type)) {
            $create->setInterface($params->virtualization_type);
        }

        $xml = $create->makeCreateServer(
            $params->label,
            $params->location,
            $params->image,
            intval($params->memory_mb),
            intval($params->cpu_cores),
            intval($params->disk_mb),
        );

        $response = $this->makeRequest($xml);

        return (string)$response->data->vzpenvm->env->eid;
    }

    public function changePassword(string $serverId, string $password): void
    {
        $create = new XMLCommand();

        $xml = $create->makeSetRootPassword(
            $serverId,
            base64_encode($password)
        );

        $this->makeRequest($xml);
    }

    public function resize(string $serverId, ResizeParams $params): void
    {
        $create = new XMLCommand();

        $xml = $create->makeSetServerConfig(
            $serverId,
            intval($params->memory_mb),
            intval($params->cpu_cores),
            intval($params->disk_mb),
        );

        $this->makeRequest($xml);
    }

    public function restart(string $serverId): void
    {
        $create = new XMLCommand();

        $xml = $create->makeRestartServer($serverId);

        $this->makeRequest($xml);
    }

    public function stop(string $serverId): void
    {
        $create = new XMLCommand();

        $xml = $create->makeStopServer($serverId);

        $this->makeRequest($xml);
    }

    public function start(string $serverId): void
    {
        $create = new XMLCommand();

        $xml = $create->makeStartServer($serverId);

        $this->makeRequest($xml);
    }

    public function destroy(string $serverId): void
    {
        $create = new XMLCommand();

        $xml = $create->makeDestroyServer($serverId);

        $this->makeRequest($xml);
    }
}


