<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Virtualizor;

use Illuminate\Support\Arr;
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
use Upmind\ProvisionProviders\Servers\Data\SshConnectionCommandResult;
use Upmind\ProvisionProviders\Servers\Virtualizor\Data\Configuration;

/**
 * Deploy and manage Virtualizor virtual servers using KVM, Xen, OpenVZ, Proxmox, Virtuozzo, LXC and more.
 */
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
            ->setName('Virtualizor')
            ->setLogoUrl('https://www.virtualizor.com/sitepad-data/uploads/2022/01/virtualizor_logo.png')
            ->setDescription('Deploy and manage Virtualizor virtual servers using KVM,'
                . ' Xen, OpenVZ, Proxmox, Virtuozzo, LXC and more');
    }

    /**
     * @inheritDoc
     */
    public function create(CreateParams $params): ServerInfoResult
    {
        if (is_numeric($params->size)) {
            $planId = $params->size;
            $planName = null;
        } else {
            $planId = null;
            $planName = $params->size;
        }

        if (is_numeric($params->image)) {
            $osId = $params->image;
            $osName = null;
        } else {
            $osId = null;
            $osName = $params->image;
        }

        if ($this->configuration->location_type === Configuration::LOCATION_TYPE_SERVER) {
            if (is_numeric($params->location)) {
                $serverId = $params->location;
                $serverName = null;
            } else {
                $serverId = null;
                $serverName = $params->location;
            }
            $serverLocation = null;
        } else {
            $serverId = null;
            $serverName = null;
            $serverLocation = $params->location;
        }

        $virtualizationType = $params->virtualization_type
            ?? $this->configuration->default_virtualization_type
            ?? 'kvm';

        $plan = $this->api()->getPlan($planId, $planName, $virtualizationType);
        $template = $this->api()->getOsTemplate($osId, $osName);
        $server = $this->api()->getServer($serverId, $serverName, $serverLocation);

        $data = $this->api()->createVirtualServer(
            $plan['virt'],
            $plan['plid'],
            $template['osid'],
            $server['serid'],
            $params->label,
            $params->email,
            $params->root_password
        );

        return $this->getServerInfoResult($data['vpsid'])
            ->setMessage('Virtual server creating')
            ->setState('Creating');
    }

    /**
     * @inheritDoc
     */
    public function getInfo(ServerIdentifierParams $params): ServerInfoResult
    {
        return $this->getServerInfoResult($params->instance_id);
    }

    /**
     * @inheritDoc
     */
    public function getSshConnectionCommand(ServerIdentifierParams $params): SshConnectionCommandResult
    {
        // $vnc = $this->api()->getVncInfo($params->instance_id);
        $info = $this->getServerInfoResult($params->instance_id);

        return SshConnectionCommandResult::create()
            ->setMessage('SSH command generated')
            ->setCommand(sprintf('ssh root@%s', $info['ip_address']));
    }

    /**
     * @inheritDoc
     */
    public function changeRootPassword(ChangeRootPasswordParams $params): ServerInfoResult
    {
        $data = $this->api()->changeVirtualServerRootPass($params->instance_id, $params->root_password);

        return $this->getServerInfoResult($params->instance_id)
            ->setMessage($data['done_msg'] ?? 'Root password changed');
    }

    /**
     * @inheritDoc
     */
    public function resize(ResizeParams $params): ServerInfoResult
    {
        if (is_numeric($params->size)) {
            $planId = $params->size;
            $planName = null;
        } else {
            $planId = null;
            $planName = $params->size;
        }

        $info = $this->getServerInfoResult($params->instance_id);

        if ($info->state === 'On' && !$params->resize_running) {
            throw $this->errorResult('Resize not available while server is running');
        }

        $plan = $this->api()->getPlan($planId, $planName, $info->virtualization_type);
        $data = $this->api()->changeVirtualServerPlan($params->instance_id, $plan['plid']);

        return $info->setMessage($data['done_msg'] ?? 'Virtual server plan updated')
            ->setSize($plan['plan_name']);
    }

    /**
     * @inheritDoc
     */
    public function reinstall(ReinstallParams $params): ServerInfoResult
    {
        if (is_numeric($params->image)) {
            $osId = $params->image;
            $osName = null;
        } else {
            $osId = null;
            $osName = $params->image;
        }

        $info = $this->getServerInfoResult($params->instance_id);

        $template = $this->api()->getOsTemplate($osId, $osName);

        $data = $this->api()->rebuildVirtualServer($params->instance_id, $template['osid']);

        return $info->setMessage($data['done_msg'] ?? 'Virtual server reinstalling')
            ->setImage($template['name'])
            ->setState('Rebuilding');
    }

    /**
     * @inheritDoc
     */
    public function reboot(ServerIdentifierParams $params): ServerInfoResult
    {
        $info = $this->getServerInfoResult($params->instance_id);

        $data = $this->api()->runVirtualServerAction($params->instance_id, 'restart');

        return $info->setMessage($data['done_msg'] ?? 'Virtual server restarting')
            ->setState('Restarting');
    }

    /**
     * @inheritDoc
     */
    public function shutdown(ServerIdentifierParams $params): ServerInfoResult
    {
        $info = $this->getServerInfoResult($params->instance_id);

        if ($info->state === 'Off') {
            return $info->setMessage('Virtual server already off');
        }

        $data = $this->api()->runVirtualServerAction($params->instance_id, 'stop');
        // $data = $this->api()->runVirtualServerAction($params->instance_id, 'poweroff');

        return $info->setMessage($data['done_msg'] ?? 'Virtual server stopping')
            ->setState('Stopping');
    }

    /**
     * @inheritDoc
     */
    public function powerOn(ServerIdentifierParams $params): ServerInfoResult
    {
        $info = $this->getServerInfoResult($params->instance_id);

        if ($info->state === 'On') {
            return $info->setMessage('Virtual server already on');
        }

        $data = $this->api()->runVirtualServerAction($params->instance_id, 'start');

        return $info->setMessage($data['done_msg'] ?? 'Virtual server starting')
            ->setState('Starting');
    }

    /**
     * @inheritDoc
     */
    public function terminate(ServerIdentifierParams $params): EmptyResult
    {
        $this->api()->deleteVirtualServer($params->instance_id);

        return EmptyResult::create()->setMessage('Virtual server deleted');
    }

    protected function getServerInfoResult($serverId): ServerInfoResult
    {
        // $info = $this->api()->getVirtualServer($serverId); // this is kinda pointless compared to editvs
        // $state = $this->api()->getVirtualServerStatus($serverId); // this isn't returning `status` for some reason
        $allInfo = $this->api()->getAllVirtualServerInfo($serverId); // this gives us everything, woo hoo!

        $vps = $allInfo['vps'];
        $plan = $allInfo['plans'][$vps['plid']] ?? $this->api()->getPlan($vps['plid'], null, null, false);
        $server = $allInfo['servers'][$vps['serid']] ?? $this->api()->getServer($vps['serid']);

        if ($this->configuration->location_type === Configuration::LOCATION_TYPE_SERVER) {
            $location = $server['server_name'];
        } else {
            $location = ApiClient::locationJsonToString($server['location']);
        }

        return ServerInfoResult::create()
            ->setInstanceId($vps['vpsid'])
            ->setState(ApiClient::statusNumberToString($vps['stats']['status'] ?? null))
            ->setLabel(sprintf('%s [%s]', $vps['hostname'], $vps['vps_name']))
            ->setHostname($vps['hostname'])
            ->setSize($plan['plan_name'] ?? 'Custom')
            ->setImage($vps['os_name'])
            ->setLocation($location)
            ->setNode($server['server_name'])
            ->setVirtualizationType($vps['virt'])
            ->setIpAddress(Arr::first($vps['ips']));
    }

    /**
     * Get a Guzzle HTTP client instance.
     */
    protected function api(): ApiClient
    {
        return $this->apiClient ??= new ApiClient(
            $this->configuration,
            $this->getGuzzleHandlerStack(boolval($this->configuration->debug))
        );
    }
}
