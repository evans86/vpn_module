<?php

namespace App\Services\Cloudflare;

use App\Dto\Server\ServerDto;
use App\Dto\Server\ServerFactory;
use App\Services\External\CloudflareAPI;

class CloudflareService
{
    //Создание поддомена для созданного сервера
    //Надо решить куда записать созданный поддомен
    public function createSubdomain(string $name, string $ip)
    {
        $cloudflare = new CloudflareAPI();
        $subdomain = $cloudflare->createDNSRecord($name, $ip);

        return $subdomain;
    }

    public function deleteSubdomain(string $dns_record_id): bool
    {
        $cloudflare = new CloudflareAPI();
        return $cloudflare->deleteRecord($dns_record_id);
    }

    public function testAPI()
    {
        $cloudflare = new CloudflareAPI();
        $cloudflare->getRecordID();
    }
}
