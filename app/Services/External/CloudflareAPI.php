<?php

namespace App\Services\External;

use Cloudflare\API\Adapter\Guzzle;
use Cloudflare\API\Auth\APIKey;
use Cloudflare\API\Endpoints\DNS;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use stdClass;

class CloudflareAPI
{
    private const RECORD_TYPE = 'A';

    /**
     * Возвращает адаптер для работы с API
     *
     * @return Guzzle
     * @throws RuntimeException
     */
    public static function getAdapter(): Guzzle
    {
        $email = config('services.cloudflare.email');
        $api_key = config('services.cloudflare.api_key');

        if (!$email || !$api_key) {
            throw new RuntimeException('Cloudflare credentials not configured');
        }

        try {
            $key = new APIKey($email, $api_key);
            return new Guzzle($key);
        } catch (Exception $e) {
            Log::error('Failed to create Cloudflare adapter', [
                'error' => $e->getMessage(),
                'source' => 'api',
            ]);
            throw new RuntimeException('Failed to initialize Cloudflare API: ' . $e->getMessage());
        }
    }

    /**
     * Cоздать DNS запись в указанной зоне
     *
     * @param string $zoneId ID зоны Cloudflare
     * @param string $name Имя записи (поддомен без зоны или FQDN — как в API Cloudflare)
     * @param string $ip IPv4
     */
    public function createDNSRecord(string $zoneId, string $name, string $ip): stdClass
    {
        if (empty($zoneId) || empty($name) || empty($ip)) {
            throw new RuntimeException('Zone ID, name and IP are required for DNS record creation');
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('Invalid IP address: ' . $ip);
        }

        try {
            Log::info('Creating DNS record', [
                'name' => $name,
                'ip' => $ip,
                'type' => self::RECORD_TYPE,
                'zone_id' => $zoneId,
                'source' => 'api',
            ]);

            $DNSRecord = new DNS(self::getAdapter());
            /** @var stdClass|bool $result */
            $result = $DNSRecord->addRecord($zoneId, self::RECORD_TYPE, $name, $ip, 0, false);

            if (!is_object($result) || !isset($result->id) || !isset($result->name)) {
                throw new RuntimeException('Invalid response from Cloudflare API: missing id or name');
            }

            Log::info('DNS record created successfully', [
                'name' => $name,
                'ip' => $ip,
                'record_id' => $result->id,
                'source' => 'api',
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to create DNS record', [
                'name' => $name,
                'ip' => $ip,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'api',
            ]);
            throw new RuntimeException('Failed to create DNS record: ' . $e->getMessage());
        }
    }

    /**
     * Обновить существующую DNS запись (удаление + создание в той же зоне)
     */
    public function updateDNSRecord(string $zoneId, string $record_id, string $name, string $ip): stdClass
    {
        if (empty($zoneId) || empty($record_id) || empty($name) || empty($ip)) {
            throw new RuntimeException('Zone ID, record ID, name and IP are required for DNS record update');
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('Invalid IP address: ' . $ip);
        }

        try {
            Log::info('Updating DNS record', [
                'zone_id' => $zoneId,
                'record_id' => $record_id,
                'name' => $name,
                'ip' => $ip,
                'source' => 'api',
            ]);

            $DNSRecord = new DNS(self::getAdapter());
            $DNSRecord->deleteRecord($zoneId, $record_id);

            /** @var stdClass|bool $result */
            $result = $DNSRecord->addRecord($zoneId, self::RECORD_TYPE, $name, $ip, 0, false);

            if (!is_object($result) || !isset($result->id) || !isset($result->name)) {
                throw new RuntimeException('Invalid response from Cloudflare API: missing id or name');
            }

            Log::info('DNS record updated successfully', [
                'record_id' => $record_id,
                'name' => $name,
                'ip' => $ip,
                'source' => 'api',
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to update DNS record', [
                'record_id' => $record_id,
                'name' => $name,
                'ip' => $ip,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'api',
            ]);
            throw new RuntimeException('Failed to update DNS record: ' . $e->getMessage());
        }
    }

    /**
     * Удалить DNS запись
     */
    public function deleteDNSRecord(string $zoneId, string $dns_record_id): bool
    {
        if (empty($zoneId) || empty($dns_record_id)) {
            throw new RuntimeException('Zone ID and record ID are required for DNS record deletion');
        }

        try {
            Log::info('Deleting DNS record', [
                'zone_id' => $zoneId,
                'record_id' => $dns_record_id,
                'source' => 'api',
            ]);

            $DNSRecord = new DNS(self::getAdapter());
            $result = $DNSRecord->deleteRecord($zoneId, $dns_record_id);

            Log::info('DNS record deleted successfully', [
                'record_id' => $dns_record_id,
                'source' => 'api',
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to delete DNS record', [
                'record_id' => $dns_record_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'api',
            ]);
            throw new RuntimeException('Failed to delete DNS record: ' . $e->getMessage());
        }
    }

    /**
     * Получить список DNS записей зоны
     *
     * @return array<int, stdClass>
     */
    public function getRecords(string $zoneId): array
    {
        if (empty($zoneId)) {
            throw new RuntimeException('Zone ID is required');
        }

        try {
            Log::info('Getting DNS records list', ['zone_id' => $zoneId, 'source' => 'api']);

            $DNSRecord = new DNS(self::getAdapter());
            $result = $DNSRecord->listRecords($zoneId)->result;

            Log::info('DNS records retrieved successfully', [
                'zone_id' => $zoneId,
                'count' => count($result),
                'source' => 'api',
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to get DNS records', [
                'zone_id' => $zoneId,
                'error' => $e->getMessage(),
                'source' => 'api',
                'trace' => $e->getTraceAsString(),
            ]);
            throw new RuntimeException('Failed to get DNS records: ' . $e->getMessage());
        }
    }
}
