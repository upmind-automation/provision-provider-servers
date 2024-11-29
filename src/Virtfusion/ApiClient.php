<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Virtfusion;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Str;
use Throwable;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionProviders\Servers\Data\CreateParams;
use Upmind\ProvisionProviders\Servers\Data\ResizeParams;
use Upmind\ProvisionProviders\Servers\Virtfusion\Data\Configuration;

class ApiClient
{
    protected Configuration $configuration;
    protected Client $client;

    public function __construct(Configuration $configuration, ?HandlerStack $handler = null)
    {
        $this->configuration = $configuration;

        $this->client = new Client([
            'base_uri' => sprintf('https://%s', $configuration->hostname),
            'headers' => [
                'Accept' => 'application/json',
                'Content-type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->configuration->api_token,
            ],
            'connect_timeout' => 10,
            'timeout' => $configuration->timeout ?? 120,
            'handler' => $handler,
        ]);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function makeRequest(
        string  $command,
        ?array  $params = null,
        ?array  $body = null,
        ?string $method = 'GET'
    ): ?array
    {
        try {
            $requestParams = [];

            if ($params) {
                $requestParams['query'] = $params;
            }

            if ($body) {
                $requestParams['json'] = $body;
            }

            $response = $this->client->request($method, '/api/v1/' . ltrim($command, '/'), $requestParams);
            $result = $response->getBody()->getContents();

            $response->getBody()->close();

            if ($result === '') {
                return null;
            }

            return $this->parseResponseData($result);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    private function parseResponseData(string $response): array
    {
        $parsedResult = json_decode($response, true);

        if (!$parsedResult) {
            throw ProvisionFunctionError::create('Unknown Provider API Error')
                ->withData([
                    'response' => $response,
                ]);
        }

        return $parsedResult;
    }


    /**
     * @return no-return
     *
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    private function handleException(Throwable $e): void
    {
        if ($e instanceof ProvisionFunctionError) {
            throw $e;
        }

        $errorData = [
            'exception' => get_class($e),
        ];

        if ($e instanceof ConnectException) {
            $errorData['connection_error'] = $e->getMessage();
            throw ProvisionFunctionError::create('Provider API Connection error', $e)
                ->withData($errorData);
        }

        if ($e instanceof TransferException) {
            if ($e instanceof BadResponseException && $e->hasResponse()) {
                $response = $e->getResponse();
                $reason = $response->getReasonPhrase();
                $errorData['http_code'] = $response->getStatusCode();
                $responseBody = $response->getBody()->__toString();
                $responseData = json_decode($responseBody, true);

                $errorMessage = $responseData['errors'][0] ?? null;

                if (!$errorMessage) {
                    $errorMessage = $responseData['msg'] ?? null;
                }

                if (!$errorMessage) {
                    $errorMessage = $responseData['message'] ?? null;
                }
            }

            $errorMessage = sprintf('Provider API error: %s', $errorMessage ?? $reason ?? 'Unknown');
            throw ProvisionFunctionError::create($errorMessage)
                ->withData($errorData);
        }

        throw ProvisionFunctionError::create('Unknown Provider API Error', $e)
            ->withData($errorData);
    }


    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function getServerInfo(string $serverId): ?array
    {
        $response = $this->makeRequest("/servers/{$serverId}", ['remoteState' => 'true']);
        $data = $response['data'];

        $state = ucfirst((string)($data['remoteState']['state'] ?? $data['state'] ?? 'unknown'));

        return [
            'customer_identifier' => (int)$data['ownerId'],
            'instance_id' => (string)($data['id'] ?? 'Unknown'),
            'state' => $state,
            'suspended' => (bool)$data['suspended'],
            'label' => $data['name'] ?? 'Unknown',
            'hostname' => $data['hostname'] ?? 'Unknown',
            'ip_address' => $data['vnc']['ip'],
            'image' => ((int)$data['settings']['osTemplateInstallId'] != 0 ? $this->getImageName($serverId, (int)$data['settings']['osTemplateInstallId']) : "Unknown"),
            'memory_mb' => (int)($data['settings']['resources']['memory'] ?? 0),
            'cpu_cores' => (int)($data['settings']['resources']['cpuCores'] ?? 0),
            'disk_mb' => (isset($data['settings']['resources']['storage']) ? ((int)$data['settings']['resources']['storage']) : 0) * 1024,
            'location' => $data['hypervisor']['dataDir'] ?? 'Unknown',
            'virtualization_type' => $data['settings']['hyperv']['vendorIdValue'] ?? 'Unknown',
            'created_at' => isset($data['created'])
                ? Carbon::parse((string)$data['created'])->format('Y-m-d H:i:s')
                : null,
            'updated_at' => isset($data['updated'])
                ? Carbon::parse((string)$data['updated'])->format('Y-m-d H:i:s')
                : null,
        ];
    }


    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function getImageName(string $serverId, int $imageId): string
    {
        $response = $this->makeRequest("/servers/{$serverId}/templates");
        $data = $response['data'];
        foreach ($data as $group) {
            foreach ($group['templates'] as $template) {
                if ($template['id'] == $imageId) {
                    return $template['name'] . ' ' . $template['version'];
                }
            }
        }

        return 'Unknown';
    }


    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function getVNC(string $serverId): array
    {
        $body = [
            'action' => 'enable'
        ];

        $response = $this->makeRequest("/servers/{$serverId}/vnc", null, $body, 'POST');
        $vnc = $response['data']['vnc'];

        return [
            'ip_address' => $vnc['ip'],
            'port' => $vnc['port'],
            'password' => $vnc['password']
        ];
    }


    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function create(CreateParams $params): string
    {
        if (!is_numeric($params->size)) {
            $size = $this->getPackageId($params->size);
        } else {
            $size = $params->size;
        }

        $body = [
            'userId' => $params->customer_identifier,
            'packageId' => $size,
            'cpuCores' => $params->cpu_cores,
            'storage' => (int)round(($params->disk_mb) / 1024),
            'memory' => $params->memory_mb,
            'hypervisorId' => $this->configuration->hypervisorId,
        ];

        $response = $this->makeRequest("/servers", null, $body, 'POST');

        if (!$id = $response['data']['id']) {
            throw ProvisionFunctionError::create('Server creation failed')
                ->withData([
                    'result_data' => $response,
                ]);
        }

        if ((int)$params->image == $params->image) {
            $image = $params->image;
        } else {
            $image = null;
        }

        try {
            $body = [
                'name' => $params->label,
                'hostname' => $params->label,
                'operatingSystemId' => $image,
            ];

            $this->makeRequest("/servers/{$id}/build", null, $body, 'POST');
        } catch (\Exception $e) {
            throw ProvisionFunctionError::create('Server building failed')
                ->withData([
                    'result_data' => $response,
                ]);
        }

        return (string)$id;
    }


    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function resetPassword(string $serverId): void
    {
        $body = [
            'user' => 'root',
        ];

        $this->makeRequest("/servers/{$serverId}/resetPassword", null, $body, 'POST');
    }


    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function suspend(string $serverId): void
    {
        $this->makeRequest("/servers/$serverId}/suspend", null, null, 'POST');
    }


    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function unsuspend(string $serverId): void
    {
        $this->makeRequest("/servers/$serverId}/unsuspend", null, null, 'POST');
    }


    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function power(string $serverId, string $action): void
    {
        $this->makeRequest("/servers/{$serverId}/power/{$action}", null, null, 'POST');
    }


    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function destroy(string $serverId): void
    {
        $params = [
            'delay' => 0,
        ];

        $this->makeRequest("/servers/{$serverId}", $params, null, 'DELETE');
    }


    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function changePackage(string $serverId, string $size): void
    {
        if (!is_numeric($size)) {
            $size = $this->getPackageId($size);
        }

        $this->makeRequest("/servers/{$serverId}/package/{$size}", null, null, 'PUT');
    }

    /**
     * @param $packageName
     * @return int|null
     * @throws Throwable
     */
    private function getPackageId($packageName): ?int
    {
        $response = $this->makeRequest("/packages");
        $data = $response['data'];
        foreach ($data as $package) {
            if ($package['name'] == $packageName) {
                return $package['id'];
            }
        }

        throw ProvisionFunctionError::create("Package {$packageName} not found")
            ->withData([
                'response' => $response,
            ]);
    }

    public function rebuildServer(string $serverId, ?string $hostname, int $image): void
    {
        try {
            $body = [
                'name' => $hostname,
                'hostname' => $hostname,
                'operatingSystemId' =>$image,
            ];

            $this->makeRequest("/servers/{$serverId}/build", null, $body, 'POST');
        } catch (\Exception $e) {
            throw ProvisionFunctionError::create('Server building failed');
        }
    }
}
