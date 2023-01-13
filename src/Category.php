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
use Upmind\ProvisionProviders\Servers\Data\ServerIdentifierParams;
use Upmind\ProvisionProviders\Servers\Data\ServerInfoResult;

abstract class Category extends BaseCategory
{
    public static function aboutCategory(): AboutData
    {
        return AboutData::create()
            ->setName('Servers')
            ->setDescription('Create and manage servers on popular Cloud provider networks')
            ->setIcon('server');
    }

    abstract public function create(CreateParams $params): ServerInfoResult;

    abstract public function getInfo(ServerIdentifierParams $params): ServerInfoResult;

    abstract public function changeRootPassword(ChangeRootPasswordParams $params): ServerInfoResult;

    abstract public function resize(ResizeParams $params): ServerInfoResult;

    abstract public function reinstall(ReinstallParams $params): ServerInfoResult;

    abstract public function reboot(ServerIdentifierParams $params): ServerInfoResult;

    abstract public function shutdown(ServerIdentifierParams $params): ServerInfoResult;

    abstract public function powerOn(ServerIdentifierParams $params): ServerInfoResult;

    abstract public function terminate(ServerIdentifierParams $params): EmptyResult;
}
