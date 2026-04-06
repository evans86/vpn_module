<?php

namespace App\Services\Cloudflare;

use App\Services\External\CloudflareAPI;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use stdClass;

class CloudflareService
{
    private CloudflareAPI $api;

    public function __construct()
    {
        $this->api = new CloudflareAPI();
    }

    /**
     * Случайная зона из пула (новые записи не используют vpn-telegram.com — её нет в dns_zones).
     *
     * @return array{zone_id: string, domain: string}
     */
    private function pickRandomDnsZone(): array
    {
        $zones = config('services.cloudflare.dns_zones', []);
        if (!is_array($zones) || $zones === []) {
            throw new RuntimeException(
                'Не задан пул зон Cloudflare (config services.cloudflare.dns_zones). ' .
                'Задайте CLOUDFLARE_DNS_ZONE_KVN_FREE / CLOUDFLARE_DNS_ZONE_KVN_COCO и домены в .env.'
            );
        }

        return $zones[array_rand($zones)];
    }

    /**
     * Совпадение имени записи с меткой поддомена в конкретной зоне (FQDN).
     */
    private function recordMatchesSubdomainInZone(string $recordName, string $nameLabel, string $zoneDomain): bool
    {
        $r = strtolower(rtrim($recordName, '.'));
        $fqdn = strtolower($nameLabel . '.' . rtrim($zoneDomain, '.'));

        return $r === $fqdn;
    }

    /**
     * Создание поддомена: случайная зона из пула, без vpn-telegram.com.
     *
     * @return stdClass Ответ API + поле cloudflare_zone_id
     */
    public function createSubdomain(string $name, string $ip): stdClass
    {
        try {
            if (empty($name) || empty($ip)) {
                throw new RuntimeException('Name and IP are required for subdomain creation');
            }

            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                throw new RuntimeException('Invalid IP address: ' . $ip);
            }

            $zone = $this->pickRandomDnsZone();
            $zoneId = $zone['zone_id'];
            $domain = $zone['domain'];

            Log::info('Starting subdomain creation/update', [
                'name' => $name,
                'ip' => $ip,
                'zone_id' => $zoneId,
                'zone_domain' => $domain,
                'source' => 'cloudflare',
            ]);

            $records = $this->api->getRecords($zoneId);
            foreach ($records as $record) {
                if (!$this->recordMatchesSubdomainInZone((string) $record->name, $name, $domain)) {
                    continue;
                }

                if ($record->content === $ip) {
                    Log::info('DNS record already exists with same IP', [
                        'name' => $record->name,
                        'ip' => $ip,
                        'record_id' => $record->id,
                        'zone_id' => $zoneId,
                        'source' => 'cloudflare',
                    ]);
                    $record->cloudflare_zone_id = $zoneId;

                    return $record;
                }

                Log::info('Updating existing DNS record with new IP', [
                    'name' => $record->name,
                    'old_ip' => $record->content,
                    'new_ip' => $ip,
                    'record_id' => $record->id,
                    'source' => 'cloudflare',
                ]);

                $this->api->deleteDNSRecord($zoneId, $record->id);
                $result = $this->api->createDNSRecord($zoneId, $name, $ip);

                if (!isset($result->id) || !isset($result->name)) {
                    throw new RuntimeException('Invalid response from Cloudflare API: missing id or name');
                }

                $result->cloudflare_zone_id = $zoneId;

                Log::info('DNS record updated successfully', [
                    'name' => $result->name,
                    'ip' => $ip,
                    'record_id' => $result->id,
                    'source' => 'cloudflare',
                ]);

                return $result;
            }

            Log::info('Creating new DNS record', [
                'name' => $name,
                'ip' => $ip,
                'zone_id' => $zoneId,
                'source' => 'cloudflare',
            ]);

            $result = $this->api->createDNSRecord($zoneId, $name, $ip);

            if (!isset($result->id) || !isset($result->name)) {
                throw new RuntimeException('Invalid response from Cloudflare API: missing id or name');
            }

            $result->cloudflare_zone_id = $zoneId;

            Log::info('DNS record created successfully', [
                'name' => $result->name,
                'ip' => $ip,
                'record_id' => $result->id,
                'zone_id' => $zoneId,
                'source' => 'cloudflare',
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to create/update DNS record', [
                'name' => $name,
                'ip' => $ip,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'cloudflare',
            ]);
            throw $e;
        }
    }

    /**
     * Удаление записи. $zoneId — из server.cloudflare_zone_id; иначе legacy (старые записи в vpn-telegram.com).
     */
    public function deleteSubdomain(string $recordId, ?string $zoneId = null): void
    {
        try {
            if (empty($recordId)) {
                throw new RuntimeException('Record ID is required for deletion');
            }

            $zoneId = $zoneId ?: (string) config('services.cloudflare.legacy_zone_id');
            if ($zoneId === '') {
                throw new RuntimeException('Cloudflare legacy_zone_id не задан в конфиге');
            }

            $records = $this->api->getRecords($zoneId);
            $recordExists = false;
            foreach ($records as $record) {
                if ($record->id === $recordId) {
                    $recordExists = true;
                    break;
                }
            }

            if (!$recordExists) {
                Log::info('DNS record not found in zone, skipping deletion', [
                    'record_id' => $recordId,
                    'zone_id' => $zoneId,
                    'source' => 'cloudflare',
                ]);

                return;
            }

            Log::info('Deleting DNS record', [
                'record_id' => $recordId,
                'zone_id' => $zoneId,
                'source' => 'cloudflare',
            ]);

            $this->api->deleteDNSRecord($zoneId, $recordId);

            Log::info('DNS record deleted successfully', [
                'record_id' => $recordId,
                'zone_id' => $zoneId,
                'source' => 'cloudflare',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete DNS record', [
                'record_id' => $recordId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'cloudflare',
            ]);
            throw $e;
        }
    }

    /**
     * Все записи из пула зон + legacy (для отладки).
     *
     * @return array<int, stdClass>
     */
    public function getAllRecords(): array
    {
        try {
            Log::info('Getting all DNS records (multi-zone)', ['source' => 'cloudflare']);
            $merged = [];
            $seenZones = [];
            $zones = config('services.cloudflare.dns_zones', []);
            if (is_array($zones)) {
                foreach ($zones as $z) {
                    if (empty($z['zone_id']) || isset($seenZones[$z['zone_id']])) {
                        continue;
                    }
                    $seenZones[$z['zone_id']] = true;
                    $merged = array_merge($merged, $this->api->getRecords($z['zone_id']));
                }
            }
            $legacy = (string) config('services.cloudflare.legacy_zone_id');
            if ($legacy !== '' && !isset($seenZones[$legacy])) {
                $merged = array_merge($merged, $this->api->getRecords($legacy));
            }

            Log::info('Retrieved DNS records successfully', [
                'count' => count($merged),
                'source' => 'cloudflare',
            ]);

            return $merged;
        } catch (Exception $e) {
            Log::error('Failed to get DNS records', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'cloudflare',
            ]);

            return [];
        }
    }
}
