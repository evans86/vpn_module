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
     * Создание поддомена для созданного сервера
     *
     * @param string $name
     * @param string $ip
     * @return stdClass Объект с информацией о созданной DNS записи
     * @throws Exception
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

            Log::info('Starting subdomain creation/update', [
                'name' => $name,
                'ip' => $ip
            ]);

            // Проверяем существующие записи
            $records = $this->api->getRecords();
            foreach ($records as $record) {
                if ($record->name === $name || $record->name === $name . '.vpn-telegram.com') {
                    // Если запись существует и IP тот же - возвращаем существующую запись
                    if ($record->content === $ip) {
                        Log::info('DNS record already exists with same IP', [
                            'name' => $record->name,
                            'ip' => $ip,
                            'record_id' => $record->id
                        ]);
                        return $record;
                    }
                    // Если IP отличается - обновляем запись
                    Log::info('Updating existing DNS record with new IP', [
                        'name' => $record->name,
                        'old_ip' => $record->content,
                        'new_ip' => $ip,
                        'record_id' => $record->id
                    ]);
//                    return $this->api->updateDNSRecord($record->id, $name, $ip);
                }
            }

            // Если запись не найдена - создаем новую
            Log::info('Creating new DNS record', [
                'name' => $name,
                'ip' => $ip
            ]);

            $result = $this->api->createDNSRecord($name, $ip);

            if (!isset($result->id) || !isset($result->name)) {
                throw new RuntimeException('Invalid response from Cloudflare API: missing id or name');
            }

            Log::info('DNS record created successfully', [
                'name' => $result->name,
                'ip' => $ip,
                'record_id' => $result->id
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to create/update DNS record', [
                'name' => $name,
                'ip' => $ip,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Удаление поддомена сервера
     *
     * @param string $dns_record_id
     * @return bool
     */
    public function deleteSubdomain(string $dns_record_id): bool
    {
        try {
            if (empty($dns_record_id)) {
                throw new RuntimeException('DNS record ID is required for deletion');
            }

            Log::info('Deleting DNS record', [
                'record_id' => $dns_record_id
            ]);

            $result = $this->api->deleteDNSRecord($dns_record_id);

            Log::info('DNS record deleted successfully', [
                'record_id' => $dns_record_id
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to delete DNS record', [
                'dns_record_id' => $dns_record_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Получение списка всех DNS записей
     *
     * @return array
     */
    public function getAllRecords(): array
    {
        try {
            Log::info('Getting all DNS records');
            $records = $this->api->getRecords();
            Log::info('Retrieved DNS records successfully', [
                'count' => count($records)
            ]);
            return $records;
        } catch (Exception $e) {
            Log::error('Failed to get DNS records', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
}
