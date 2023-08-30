<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Virtuozzo;

use GuzzleHttp\Client;
use Throwable;
use Illuminate\Support\Arr;
use Upmind\ProvisionBase\Provider\Contract\ProviderInterface;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionBase\Helper;
use Upmind\ProvisionProviders\Servers\Category;
use Upmind\ProvisionProviders\Servers\Data\ChangeRootPasswordParams;
use Upmind\ProvisionProviders\Servers\Data\CreateParams;
use Upmind\ProvisionProviders\Servers\Data\EmptyResult;
use Upmind\ProvisionProviders\Servers\Data\ReinstallParams;
use Upmind\ProvisionProviders\Servers\Data\ResizeParams;
use Upmind\ProvisionProviders\Servers\Data\ServerIdentifierParams;
use Upmind\ProvisionProviders\Servers\Data\ServerInfoResult;
use Upmind\ProvisionProviders\Servers\Data\SshConnectionCommandResult;
use Upmind\ProvisionProviders\Servers\Virtuozzo\Data\Configuration;

class Provider extends Category implements ProviderInterface
{
    protected Configuration $configuration;
    protected ApiClient $apiClient;

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
            ->setName('Virtuozzo Hybrid Server 7')
            ->setDescription('Deploy and manage Virtuozzo Hybrid Server 7 virtual servers')
            ->setLogoUrl('https://api.upmind.io/images/logos/provision/virtuozzo-logo@2x.png');
    }

    /**
     * @inheritDoc
     */
    public function create(CreateParams $params): ServerInfoResult
    {
        try {
            $serverId = $this->api()->create($params);

            return $this->getServerInfoResult($serverId)->setMessage('Server created successfully!');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function getInfo(ServerIdentifierParams $params): ServerInfoResult
    {
        try {
            return $this->getServerInfoResult($params->instance_id)->setMessage('Server info obtained');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    protected function getServerInfoResult($serverId): ServerInfoResult
    {
        $info = $this->api()->getServerInfo($serverId);

        return ServerInfoResult::create($info);
    }

    /**
     * @inheritDoc
     */
    public function getSshConnectionCommand(ServerIdentifierParams $params): SshConnectionCommandResult
    {
        try {
            $info = $this->api()->getServerInfo($params->instance_id);

            return SshConnectionCommandResult::create()
                ->setMessage('SSH command generated')
                ->setCommand(sprintf('ssh root@%s', $info['ip_address']));
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function changeRootPassword(ChangeRootPasswordParams $params): ServerInfoResult
    {
        try {
            $this->api()->changePassword($params->instance_id, $params->root_password);

            return $this->getServerInfoResult($params->instance_id)->setMessage('Root password changed');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function resize(ResizeParams $params): ServerInfoResult
    {
        try {
            $this->api()->resize($params->instance_id, $params->size);

            return $this->getServerInfoResult($params->instance_id)->setMessage('Server is resizing');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function reinstall(ReinstallParams $params): ServerInfoResult
    {
        throw $this->errorResult('Operation not supported');
    }

    /**
     * @inheritDoc
     */
    public function reboot(ServerIdentifierParams $params): ServerInfoResult
    {
        try {
            $this->api()->restart($params->instance_id);

            return $this->getServerInfoResult($params->instance_id)->setMessage('Server is rebooting');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function shutdown(ServerIdentifierParams $params): ServerInfoResult
    {
        try {
            $info = $this->getServerInfoResult($params->instance_id);

            if ($info->state === 'down') {
                return $info->setMessage('Virtual server already off');
            }

            $this->api()->stop($params->instance_id);

            return $info->setMessage('Server is shutting down')->setState('Stopping');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function powerOn(ServerIdentifierParams $params): ServerInfoResult
    {
        try {
            $info = $this->getServerInfoResult($params->instance_id);

            if ($info->state === 'running') {
                return $info->setMessage('Virtual server already on');
            }

            $this->api()->start($params->instance_id);

            return $info->setMessage('Server is booting')->setState('Starting');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function terminate(ServerIdentifierParams $params): EmptyResult
    {
        try {
            $this->api()->stop($params->instance_id);

            $this->api()->destroy($params->instance_id);

            return EmptyResult::create()->setMessage('Server permanently deleted');

        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    protected function api(): ApiClient
    {
        return $this->apiClient ??= new ApiClient($this->configuration);
    }


    /**
     * @return no-return
     * @throws ProvisionFunctionError
     * @throws Throwable
     */
    protected function handleException(Throwable $e): void
    {
        if (!$e instanceof ProvisionFunctionError) {
            $e = new ProvisionFunctionError('Unexpected Provider Error', $e->getCode(), $e);
        }

        throw $e;
    }
}
