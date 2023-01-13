<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers;

use Upmind\ProvisionBase\Provider\BaseCategory;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;
use Upmind\ProvisionProviders\Servers\Data\ChangeRootPasswordParams;
use Upmind\ProvisionProviders\Servers\Data\CreateParams;
use Upmind\ProvisionProviders\Servers\Data\EmptyResult;
use Upmind\ProvisionProviders\Servers\Data\ReinstallParams;
use Upmind\ProvisionProviders\Servers\Data\ResizeParams;
use Upmind\ProvisionProviders\Servers\Data\ServerIdentifier;
use Upmind\ProvisionProviders\Servers\Data\ServerInfo;

abstract class Category extends BaseCategory
{
    public static function aboutCategory(): AboutData
    {
        return AboutData::create()
            ->setName('Servers')
            ->setDescription('Create and manage servers on popular Cloud provider networks')
            ->setIcon('server');
    }

    abstract public function create(CreateParams $params): ServerInfo;

    abstract public function getInfo(ServerIdentifier $params): ServerInfo;

    abstract public function changeRootPassword(ChangeRootPasswordParams $params): ServerInfo;

    abstract public function resize(ResizeParams $params): ServerInfo;

    abstract public function reinstall(ReinstallParams $params): ServerInfo;

    abstract public function reboot(ServerIdentifier $params): ServerInfo;

    abstract public function shutdown(ServerIdentifier $params): ServerInfo;

    abstract public function powerOn(ServerIdentifier $params): ServerInfo;

    abstract public function terminate(ServerIdentifier $params): EmptyResult;
}
