<?php

namespace App\Services\Server;

use App\Services\External\VdsinaAPI;

class ServerService
{
    public function getAccount()
    {
        $api_key = '81470cecb8eb6f8da025c94b60aaba6e0563fb75794844b429d85cbf23800d4f';
        $vdsinaApi = new VdsinaAPI($api_key);

        return $vdsinaApi->getServerPlan();
    }
}
