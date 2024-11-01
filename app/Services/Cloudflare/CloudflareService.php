<?php

namespace App\Services\Cloudflare;

use App\Services\External\CloudflareAPI;

class CloudflareService
{
    //Создание поддомена для созданного сервера
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

    /**
     * TODO: Сервисный метод
     */
    public function testAPI()
    {
        $cloudflare = new CloudflareAPI();
        $cloudflare->getRecordID();
    }
}
