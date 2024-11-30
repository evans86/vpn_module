<?php

namespace App\Services\External;

use Cloudflare\API\Adapter\Guzzle;
use Cloudflare\API\Endpoints\DNS;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use stdClass;

class CloudflareAPI
{
    private const ZONE_ID = 'ecd4115fa760df3dd0a5f9c0e2caee2d';
    private const RECORD_TYPE = 'A';
    private const DOMAIN = 'bot-t.ru';

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
            $key = new \Cloudflare\API\Auth\APIKey($email, $api_key);
            return new \Cloudflare\API\Adapter\Guzzle($key);
        } catch (\Exception $e) {
            Log::error('Failed to create Cloudflare adapter', [
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('Failed to initialize Cloudflare API: ' . $e->getMessage());
        }
    }

    /**
     * Cоздать DNS запись
     *
     * @param string $name
     * @param string $ip
     * @return stdClass Объект с информацией о созданной DNS записи
     * @throws RuntimeException
     */
    public function createDNSRecord(string $name, string $ip): stdClass
    {
        if (empty($name) || empty($ip)) {
            throw new RuntimeException('Name and IP are required for DNS record creation');
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('Invalid IP address: ' . $ip);
        }

        try {
            // Добавляем домен к имени, если его нет
            if (strpos($name, self::DOMAIN) === false) {
                $name = $name . '.' . self::DOMAIN;
            }

            Log::info('Creating DNS record', [
                'name' => $name,
                'ip' => $ip,
                'type' => self::RECORD_TYPE,
                'zone_id' => self::ZONE_ID
            ]);

            $DNSRecord = new DNS(self::getAdapter());
            $result = $DNSRecord->addRecord(self::ZONE_ID, self::RECORD_TYPE, $name, $ip);

            if (!isset($result->id) || !isset($result->name)) {
                throw new RuntimeException('Invalid response from Cloudflare API: missing id or name');
            }

            Log::info('DNS record created successfully', [
                'name' => $name,
                'ip' => $ip,
                'record_id' => $result->id
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to create DNS record', [
                'name' => $name,
                'ip' => $ip,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Failed to create DNS record: ' . $e->getMessage());
        }
    }

    /**
     * Обновить существующую DNS запись
     *
     * @param string $record_id
     * @param string $name
     * @param string $ip
     * @return stdClass Объект с информацией об обновленной DNS записи
     * @throws RuntimeException
     */
    public function updateDNSRecord(string $record_id, string $name, string $ip): stdClass
    {
        if (empty($record_id) || empty($name) || empty($ip)) {
            throw new RuntimeException('Record ID, name and IP are required for DNS record update');
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('Invalid IP address: ' . $ip);
        }

        try {
            // Добавляем домен к имени, если его нет
            if (strpos($name, self::DOMAIN) === false) {
                $name = $name . '.' . self::DOMAIN;
            }

            Log::info('Updating DNS record', [
                'record_id' => $record_id,
                'name' => $name,
                'ip' => $ip
            ]);

            $DNSRecord = new DNS(self::getAdapter());
            $result = $DNSRecord->updateRecord(
                self::ZONE_ID,
                $record_id,
                [
                    'type' => self::RECORD_TYPE,
                    'name' => $name,
                    'content' => $ip
                ]
            );

            if (!isset($result->id) || !isset($result->name)) {
                throw new RuntimeException('Invalid response from Cloudflare API: missing id or name');
            }

            Log::info('DNS record updated successfully', [
                'record_id' => $record_id,
                'name' => $name,
                'ip' => $ip
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to update DNS record', [
                'record_id' => $record_id,
                'name' => $name,
                'ip' => $ip,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Failed to update DNS record: ' . $e->getMessage());
        }
    }

    /**
     * Удалить DNS запись
     *
     * @param string $dns_record_id
     * @return bool
     * @throws RuntimeException
     */
    public function deleteDNSRecord(string $dns_record_id): bool
    {
        if (empty($dns_record_id)) {
            throw new RuntimeException('Record ID is required for DNS record deletion');
        }

        try {
            Log::info('Deleting DNS record', [
                'record_id' => $dns_record_id
            ]);

            $DNSRecord = new DNS(self::getAdapter());
            $result = $DNSRecord->deleteRecord(self::ZONE_ID, $dns_record_id);

            Log::info('DNS record deleted successfully', [
                'record_id' => $dns_record_id
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to delete DNS record', [
                'record_id' => $dns_record_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Failed to delete DNS record: ' . $e->getMessage());
        }
    }

    /**
     * Получить список всех DNS записей
     *
     * @return array
     * @throws RuntimeException
     */
    public function getRecords(): array
    {
        try {
            Log::info('Getting DNS records list');

            $DNSRecord = new DNS(self::getAdapter());
            $result = $DNSRecord->listRecords(self::ZONE_ID)->result;

            Log::info('DNS records retrieved successfully', [
                'count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to get DNS records', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Failed to get DNS records: ' . $e->getMessage());
        }
    }
}
