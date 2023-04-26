<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\SolusVM;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionBase\Helper;
use Upmind\ProvisionProviders\Servers\SolusVM\Data\Configuration;

class ApiClient
{
    protected Configuration $configuration;
    protected Client $client;

    public function __construct(Configuration $configuration, ?HandlerStack $handler = null)
    {
        $this->configuration = $configuration;
        $this->client = new Client([
            'handler' => $handler,
            'base_uri' => sprintf('https://%s:%s/', $this->configuration->hostname, $this->configuration->port ?: 5656),
            'headers' => [
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
            'verify' => false,
        ]);
    }

    /**
     * @return string Customer username
     */
    public function createCustomer(string $email, ?string $password = null): string
    {
        $response = $this->apiCall('client-create', [
            'username' => $email,
            'email' => $email,
            'password' => $password ?: Helper::generatePassword(15),
        ]);

        return $response['username'];
    }

    /**
     * @param string|int|null $nodeGroupId
     * @return string|int Server ID
     */
    public function createServer(
        string $virtualizationType,
        string $customerUsername,
        string $label,
        string $planName,
        string $templateId,
        string $password,
        $nodeGroupId,
        ?string $node
    ): string {
        $params = [
            'type' => $virtualizationType,
            'username' => $customerUsername,
            'hostname' => $label,
            'plan' => $planName,
            'template' => $templateId,
            'password' => $password,
            'ips' => 1,
        ];

        if (isset($nodeGroupId)) {
            $params['nodegroup'] = $nodeGroupId;
        }

        if (isset($node)) {
            $params['node'] = $node;
        }

        $response = $this->apiCall('vserver-create', $params);

        return $response['vserverid'];
    }

    /**
     * @param string|int $serverId
     */
    public function changeServerPlan($serverId, string $planName): void
    {
        $this->apiCall('vserver-change', [
            'vserverid' => $serverId,
            'plan' => $planName,
        ]);
    }

    /**
     * @param string|int $serverId
     */
    public function rebuildServer($serverId, string $templateId): void
    {
        $this->apiCall('vserver-rebuild', [
            'vserverid' => $serverId,
            'template' => $templateId,
        ]);
    }

    /**
     * @param string|int $serverId
     */
    public function changeRootPassword(string $serverId, string $password): void
    {
        $this->apiCall('vserver-rootpassword', [
            'vserverid' => $serverId,
            'rootpassword' => $password,
        ]);
    }

    /**
     * @param string|int $serverId
     */
    public function bootServer(string $serverId): void
    {
        $this->apiCall('vserver-boot', ['vserverid' => $serverId]);
    }

    /**
     * @param string|int $serverId
     */
    public function rebootServer(string $serverId): void
    {
        $this->apiCall('vserver-reboot', ['vserverid' => $serverId]);
    }

    /**
     * @param string|int $serverId
     */
    public function shutdownServer(string $serverId): void
    {
        $this->apiCall('vserver-shutdown', ['vserverid' => $serverId]);
    }

    /**
     * @param string|int $serverId
     */
    public function terminateServer(string $serverId): void
    {
        $this->apiCall('vserver-terminate', ['vserverid' => $serverId, 'deleteclient' => false]);
    }

    /**
     * Get information about a server.
     *
     * @param string|int $serverId
     */
    public function getServerInfo($serverId): array
    {
        $promises = [
            'infoall' => $this->apiPromise('vserver-infoall', ['vserverid' => $serverId, 'nographs' => 1]),
            'info' => $this->apiPromise('vserver-info', ['vserverid' => $serverId]),
        ];

        $info = PromiseUtils::all($promises)->wait();

        return array_merge($info['infoall'], $info['info']);
    }

    /**
     * Get a map of all available templates as template => label.
     *
     * @param string|null $type Optionally, for a specific virtualization type
     *
     * @return array<string,string>
     */
    public function listTemplates(?string $type = null): array
    {
        $types = array_filter(Arr::wrap($type)) ?: Configuration::VIRTUALIZATION_TYPES;

        $promises = array_map(function (string $type): Promise {
            return $this->apiPromise('listtemplates', ['type' => $type, 'listpipefriendly' => 1]);
        }, $types);

        $data = PromiseUtils::all($promises)->wait();

        return collect($data)->reduce(function (array $allTemplates, array $data) {
            $csv = implode(',', [
                $data['templates'] ?? '',
                $data['templateshvm'] ?? '',
                $data['templateskvm'] ?? '',
            ]);

            $templates = collect(explode(',', $csv))
                ->filter(fn (string $template) => $template !== '--none--')
                ->mapWithKeys(function (string $template) {
                    @[$template, $label] = explode('|', $template, 2);

                    return [$template => $label];
                });

            return array_merge($allTemplates, $templates->toArray());
        }, []);
    }

    /**
     * Get a list of plan objects for the given virtualization type.
     */
    public function listPlans(string $type): array
    {
        try {
            return $this->apiCall('list-plans', ['type' => $type])['plans'];
        } catch (Throwable $e) {
            if (Str::contains($e->getMessage(), 'No plans found')) {
                if (in_array($type, Configuration::VIRTUALIZATION_TYPES)) {
                    return [];
                }
            }

            throw $e;
        }
    }

    /**
     * Get a map of all node groups as id => name.
     *
     * @return array<int,string>
     */
    public function listNodeGroups(): array
    {
        $data = $this->apiCall('listnodegroups');

        return collect(explode(',', $data['nodegroups']))
            ->filter(fn (string $group) => $group !== '--none--')
            ->mapWithKeys(function (string $group) {
                @[$id, $name] = explode('|', $group, 2);

                return [$id => $name];
            })
            ->all();
    }

    /**
     * @param int|string $serverId
     * @param int $hours Number of hours for session to last
     *
     * @return mixed[] Session data
     *
     * @link https://docs.solusvm.com/v1/api/admin/virtual-server-functions/Serial%2BConsole.html
     */
    public function createConsoleSession($serverId, int $hours = 1): array
    {
        $requestedSeconds = $hours * 60 * 60;

        $data = $this->apiCall('vserver-console', [
            'vserverid' => $serverId,
            'time' => $hours,
            'access' => 'enable',
        ]);

        if ($data['sessionexpire'] < ($requestedSeconds / 4)) {
            // less than a quarter of a session left, so create a new one
            $this->apiCall('vserver-console', [
                'vserverid' => $serverId,
                'access' => 'disable',
            ]);

            $data = $this->apiCall('vserver-console', [
                'vserverid' => $serverId,
                'time' => $hours,
                'access' => 'enable',
            ]);
        }

        return $data;
    }

    /**
     * Make an API call to the SolusVM Admin API and return the response data.
     */
    public function apiCall(string $action, array $params = []): array
    {
        return $this->apiPromise($action, $params)->wait();
    }

    /**
     * Make an asynchronous API call to the SolusVM Admin API, returning a promise
     * that will resolve to the response data.
     *
     * @return Promise<mixed[]>
     */
    public function apiPromise(string $action, array $params): Promise
    {
        $params = array_merge($params, [
            'id' => $this->configuration->api_id,
            'key' => $this->configuration->api_key,
            'action' => $action,
            'rdtype' => 'json',
        ]);

        return $this->client->postAsync('/api/admin/command.php', [
            'form_params' => $params,
        ])->then(function (Response $response) {
            $responseData = json_decode($response->getBody()->__toString(), true) ?? [];

            $this->assertApiResponseSuccess($response, $responseData);

            return $responseData;
        })->otherwise(function (Throwable $e) {
            throw $this->handleException($e);
        });
    }

    /**
     * @throws ProvisionFunctionError
     */
    protected function assertApiResponseSuccess(Response $response, ?array $responseData = null): void
    {
        $responseData = $responseData ?? json_decode($response->getBody()->__toString(), true);
        $responseStatus = $responseData['status'] ?? 'unknown';

        if ($response->getStatusCode() === 200 && $responseStatus === 'success') {
            return;
        }

        // Handle API response error

        $errorMessage = sprintf('API Response %s', ucfirst($responseStatus));
        $errorData = [
            'http_code' => $response->getStatusCode(),
            'response_data' => $responseData,
        ];
        $errorDebug = [];

        if (is_null($responseData)) {
            $errorDebug['response_body'] = $response->getBody()->__toString();
        }

        if (!empty($responseData['statusmsg'])) {
            $errorMessage = sprintf('%s: %s', $errorMessage, $responseData['statusmsg']);
        }

        throw (new ProvisionFunctionError($errorMessage, (int)$response->getStatusCode()))
            ->withData($errorData)
            ->withDebug($errorDebug);
    }

    /**
     * @return no-return
     * @throws ProvisionFunctionError
     */
    protected function handleException(Throwable $e): void
    {
        if ($e instanceof ProvisionFunctionError) {
            // Already wrapped; re-throw back to caller
            throw $e;
        }

        if ($e instanceof TransferException) {
            // Re-throw wrapped in a ProvisionFunctionError
            throw (new ProvisionFunctionError('API Request Failed', (int)$e->getCode(), $e))
                ->withData([
                    'exception' => [
                        'class' => get_class($e),
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                    ],
                ]);
        }

        // Unknown exception; re-throw as-is
        throw $e;
    }
}
