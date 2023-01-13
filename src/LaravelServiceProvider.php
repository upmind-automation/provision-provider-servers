<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers;

use Upmind\ProvisionBase\Laravel\ProvisionServiceProvider;
use Upmind\ProvisionProviders\Servers\Category as ServersCategory;
use Upmind\ProvisionProviders\Servers\Linode\Provider as LinodeProvider;

class LaravelServiceProvider extends ProvisionServiceProvider
{
    public function boot()
    {
        $this->bindCategory('servers', ServersCategory::class);

        $this->bindProvider('servers', 'linode', LinodeProvider::class);
    }
}
