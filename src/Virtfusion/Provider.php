<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Virtfusion;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Arr;
use Throwable;
use Upmind\ProvisionBase\Provider\Contract\ProviderInterface;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;
use Upmind\ProvisionProviders\Servers\Category;
use Upmind\ProvisionProviders\Servers\Data\ChangeRootPasswordParams;
use Upmind\ProvisionProviders\Servers\Data\CreateParams;
use Upmind\ProvisionProviders\Servers\Data\EmptyResult;
use Upmind\ProvisionProviders\Servers\Data\ReinstallParams;
use Upmind\ProvisionProviders\Servers\Data\ResizeParams;
use Upmind\ProvisionProviders\Servers\Data\ServerIdentifierParams;
use Upmind\ProvisionProviders\Servers\Data\ServerInfoResult;
use Upmind\ProvisionProviders\Servers\Data\ConnectionResult;
use Upmind\ProvisionProviders\Servers\Virtfusion\Data\Configuration;

class Provider extends Category implements ProviderInterface
{
    protected Configuration $configuration;
    protected ApiClient|null $apiClient = null;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }


    /**
     * @inheritDoc
     */
    public static function aboutProvider(): AboutData
    {
        return AboutData::create()
            ->setName('Virtfusion')
            ->setDescription('Deploy and manage Virtfusion virtual servers')
            ->setLogoUrl('https://api.upmind.io/images/logos/provision/virtfusion-logo@2x.png');
    }


    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function create(CreateParams $params): ServerInfoResult
    {
        if (!Arr::has($params, 'customer_identifier')) {
            $this->errorResult('customer_identifier field is required!');
        }

        if (!Arr::has($params, 'size')) {
            $this->errorResult('size field is required!');
        }

        try {
            $serverId = $this->api()->create($params);

            return $this->getServerInfoResult($serverId)->setMessage('Server created successfully!');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function getInfo(ServerIdentifierParams $params): ServerInfoResult
    {
        try {
            return $this->getServerInfoResult($params->instance_id)->setMessage('Server info obtained');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    protected function getServerInfoResult($serverId): ServerInfoResult
    {
        $info = $this->api()->getServerInfo($serverId);

        return ServerInfoResult::create($info);
    }


    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function getConnection(ServerIdentifierParams $params): ConnectionResult
    {
        try {
            $vnc = $this->api()->getVNC($params->instance_id);

            return ConnectionResult::create()
                ->setMessage('SSH command generated')
                ->setType(ConnectionResult::TYPE_SSH)
                ->setCommand(sprintf('ssh root@%s:%s', $vnc['ip_address'], $vnc['port']))
                ->setPassword($vnc['password']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function changeRootPassword(ChangeRootPasswordParams $params): ServerInfoResult
    {
        try {
            $this->api()->resetPassword($params->instance_id);

            return $this->getServerInfoResult($params->instance_id)->setMessage('Root password has been updated');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function resize(ResizeParams $params): ServerInfoResult
    {
        if (!Arr::has($params, 'size')) {
            $this->errorResult('size field is required!');
        }

        try {
            $this->api()->changePackage($params->instance_id, $params->size);

            return $this->getServerInfoResult($params->instance_id)->setMessage('Server is rebooting');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function reinstall(ReinstallParams $params): ServerInfoResult
    {
        try {
            if (!is_numeric($params->image)) {
                $this->errorResult('Image field must be integer.');
            }

            $info = $this->api()->getServerInfo($params->instance_id);

            $this->api()->rebuildServer($params->instance_id, $info['hostname'] ?? null, (int)$params->image);

            return $this->getServerInfoResult($params->instance_id)->setMessage('Server rebuilding with fresh image/template');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function reboot(ServerIdentifierParams $params): ServerInfoResult
    {
        try {
            $info = $this->getServerInfoResult($params->instance_id);

            if ($info->state == 'failed') {
                $this->errorResult('Virtual server is not operational.');
            }

            $this->api()->power($params->instance_id, 'restart');

            return $this->getServerInfoResult($params->instance_id)->setMessage('Server is rebooting');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function shutdown(ServerIdentifierParams $params): ServerInfoResult
    {
        try {
            $info = $this->getServerInfoResult($params->instance_id);

            if ($info->state == 'failed') {
                $this->errorResult('Virtual server is not operational.');
            }

            $this->api()->power($params->instance_id, 'shutdown');

            return $info->setMessage('Server is shutting down')->setState('Stopping');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function powerOn(ServerIdentifierParams $params): ServerInfoResult
    {
        try {
            $info = $this->getServerInfoResult($params->instance_id);

            if ($info->state == 'failed') {
                $this->errorResult('Virtual server is not operational.');
            }

            $this->api()->power($params->instance_id, 'boot');

            return $info->setMessage('Server is booting')->setState('Starting');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function suspend(ServerIdentifierParams $params): ServerInfoResult
    {
        try {
            $info = $this->getServerInfoResult($params->instance_id);

            if ($info->suspended) {
                $this->errorResult('Virtual server already off');
            }

            $this->api()->suspend($params->instance_id);

            return $info->setMessage('Server is shutting down')->setState('Stopping');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function unsuspend(ServerIdentifierParams $params): ServerInfoResult
    {
        try {
            $info = $this->getServerInfoResult($params->instance_id);

            if (!$info->suspended) {
                return $info->setMessage('Virtual server already on');
            }

            $this->api()->unsuspend($params->instance_id);

            return $info->setMessage('Server is booting')->setState('Stopping');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    /**
     * @inheritDoc
     *
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function attachRecoveryIso(ServerIdentifierParams $params): ServerInfoResult
    {
        $this->errorResult('Operation not supported');
    }


    /**
     * @inheritDoc
     *
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function detachRecoveryIso(ServerIdentifierParams $params): ServerInfoResult
    {
        $this->errorResult('Operation not supported');
    }


    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function terminate(ServerIdentifierParams $params): EmptyResult
    {
        try {
            $this->getServerInfoResult($params->instance_id);

            $this->api()->destroy($params->instance_id);

            return EmptyResult::create()->setMessage('Server is deleting');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    /**
     * @return ApiClient
     */
    protected function api(): ApiClient
    {
        return $this->apiClient ??= new ApiClient(
            $this->configuration,
            $this->getGuzzleHandlerStack()
        );
    }


    /**
     * @return no-return
     *
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    protected function handleException(Throwable $e): void
    {
        throw $e;
    }
}
