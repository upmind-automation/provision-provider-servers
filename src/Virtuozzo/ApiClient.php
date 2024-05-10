<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Virtuozzo;

use SimpleXMLElement;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionProviders\Servers\Helper\Utils;
use Upmind\ProvisionProviders\Servers\Data\CreateParams;
use Upmind\ProvisionProviders\Servers\Data\ResizeParams;
use Upmind\ProvisionProviders\Servers\Virtuozzo\Data\Configuration;
use Upmind\ProvisionProviders\Servers\Virtuozzo\Helper\SocketClient;
use Upmind\ProvisionProviders\Servers\Virtuozzo\Helper\XMLCommand;
use Psr\Log\LoggerInterface;

class ApiClient
{
    protected Configuration $configuration;

    protected SocketClient $client;

    public function __construct(Configuration $configuration, ?LoggerInterface $logger)
    {
        $this->configuration = $configuration;

        $this->client = new SocketClient($this->configuration->hostname, 4433);
        $this->client->setPsrLogger($logger);
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    private function makeRequest($xml): SimpleXMLElement
    {
        $this->client->connect(STREAM_CLIENT_CONNECT, 50000);

        $login = $this->login();

        $resultXml = $this->client->request($login . "\0" . $xml);

        $this->client->close();

        if ($resultXml->count() === 0) {
            throw ProvisionFunctionError::create('Empty provider api response');
        }

        return $resultXml;
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    private function login(): string
    {
        $username = base64_encode($this->configuration->username);
        $pass = base64_encode($this->configuration->password);

        return (new XMLCommand())->login($username, $pass);
    }

    public function getServerInfo(string $serverId): ?array
    {
        $info = new XMLCommand();
        $xml = $info->serverInfo($serverId);

        $response = $this->makeRequest($xml);

        $type = $response->origin;

        $env = $response->data->{$type}->env;

        $state = (string) ($env->status->state ?? '0');

        $diskSize = isset($env->virtual_config->device_list->device[0]->size)
            ? (int)$env->virtual_config->device_list->device[0]->size
            : 0;

        return [
            'instance_id' => (string) ($env->eid ?? 'Unknown'),
            'state' => $this->parseState($state),
            'label' => $env->virtual_config->name ? (string)($env->virtual_config->name) : 'Unknown',
            'hostname' => $env->virtual_config->hostname != '' ? (string)$env->virtual_config->hostname : 'Unknown',
            'ip_address' => $env->virtual_config->address->ip ? (string)$env->virtual_config->address->ip : null,
            'image' => $env->virtual_config->os->name != '' ? (string)$env->virtual_config->os->name : 'Unknown',
            'memory_mb' => (int) ($env->virtual_config->memory_size ?? 0),
            'cpu_cores' => (int) ($env->virtual_config->cpu_count ?? 0),
            'disk_mb' => $diskSize,
            'location' => (string) ($env->virtual_config->home_path ?? 'Unknown'),
            'virtualization_type' => (string) ($type ?? 'Unknown'),
            'updated_at' => $env->virtual_config->last_modified_date
                ? Utils::formatDate((string)$env->virtual_config->last_modified_date)
                : null,
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

        $platform = $this->getGuestOSPlatform($params->image);

        $xml = $create->createServer(
            $params->label,
            $params->location,
            $params->image,
            $platform,
            intval($params->memory_mb),
            intval($params->cpu_cores),
            intval($params->disk_mb)
        );

        $response = $this->makeRequest($xml);

        return (string)$response->data->vzpenvm->env->eid;
    }

    public function installGuestTools(string $serverId): void
    {
        $create = new XMLCommand();
        $xml = $create->installGuestTools($serverId);
        $this->makeRequest($xml);
    }

    public function getConsoleInfo(string $serverId): array
    {
        $create = new XMLCommand();
        $xml = $create->getConsoleInfo($serverId);
        $response = $this->makeRequest($xml);

        return json_decode(json_encode($response->data->vzpenvm->console_info), true);
    }

    public function changePassword(string $serverId, string $password): void
    {
        $create = new XMLCommand();

        $xml = $create->setRootPassword(
            $serverId,
            base64_encode($password)
        );

        $this->makeRequest($xml);
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function resize(string $serverId, ResizeParams $params): void
    {
        $info = new XMLCommand();
        $xml = $info->serverInfo($serverId);

        $response = $this->makeRequest($xml);

        $type = $response->origin;

        $env = $response->data->{$type}->env;
        $sysName = isset($env->virtual_config->device_list->device[0]->sys_name) ? (string)$env->virtual_config->device_list->device[0]->sys_name : null;

        $serverConfig = new XMLCommand();

        if (!$sysName) {
            throw ProvisionFunctionError::create('Disk not found');
        }

        $xml = $serverConfig->setServerConfig(
            $serverId,
            intval($params->memory_mb),
            intval($params->cpu_cores),
            $sysName,
            intval($params->disk_mb),
            $env->virtual_config->address->ip ? (string)$env->virtual_config->address->ip : '0.0.0.0'
        );

        $this->makeRequest($xml);
    }

    public function restart(string $serverId): void
    {
        $create = new XMLCommand();

        $xml = $create->restartServer($serverId);

        $this->makeRequest($xml);
    }

    public function stop(string $serverId): void
    {
        $create = new XMLCommand();

        $xml = $create->stopServer($serverId);

        $this->makeRequest($xml);
    }

    public function start(string $serverId): void
    {
        $create = new XMLCommand();

        $xml = $create->startServer($serverId);

        $this->makeRequest($xml);
    }

    public function destroy(string $serverId): void
    {
        $create = new XMLCommand();

        $xml = $create->destroyServer($serverId);

        $this->makeRequest($xml);
    }

    public function rebuildServer(string $serverId, string $image): void
    {
        $platform = $this->getGuestOSPlatform($image);

        $update = new XMLCommand();

        $xml = $update->setServerImage(
            $serverId,
            $image,
            $platform
        );

        $this->makeRequest($xml);
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function getGuestOSPlatform(string $image): string
    {
        $info = new XMLCommand();
        $xml = $info->getVTSettings();

        $response = $this->makeRequest($xml);

        $type = $response->origin;
        $settings = $response->data->{$type}->vt_settings;

        foreach ($settings->vm_memory as $vm) {
            if ((string)$vm->guest_os_name == $image) {
                return (string)$vm->guest_os_platform;
            }
        }

        throw ProvisionFunctionError::create('Image not found');
    }
}
