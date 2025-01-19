<?php

namespace App\Services\External;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VdsinaAPI
{
    const HOST_RU = 'https://userapi.vdsina.ru/v1/';
    const HOST_COM = 'https://userapi.vdsina.com/v1/';
    private string $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    //интересуют id = 2 Standard servers

    /**
     * @throws GuzzleException
     */
    public function getServerGroup(): array
    {
        try {
            $action = 'server-group';

            $headers = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ]
            ];

            Log::info('Getting server group from VDSina', [
                'action' => $action
            ]);

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->get($action, $headers);
            $result = $response->getBody()->getContents();
            $data = json_decode($result, true);

            if (!is_array($data)) {
                Log::error('Invalid JSON response from VDSina', [
                    'response' => $result
                ]);
                throw new RuntimeException('Invalid JSON response from VDSina');
            }

            if (!isset($data['status']) || $data['status'] !== 'ok') {
                Log::error('Error response from VDSina', [
                    'response' => $data
                ]);
                throw new RuntimeException('Error response from VDSina: ' . ($data['status_msg'] ?? 'Unknown error'));
            }

            Log::info('Successfully got server group from VDSina');

            return $data;

        } catch (GuzzleException $e) {
            Log::error('HTTP error while getting server group from VDSina', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        } catch (Exception $e) {
            Log::error('Error while getting server group from VDSina', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Error getting server group from VDSina: ' . $e->getMessage());
        }
    }

    //Возвращается список дата-центров
    //id = 1 - Amsterdam
    /**
     * @throws GuzzleException
     */
    public function getDatacenter(): array
    {
        try {
            $action = 'datacenter';

            $headers = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ]
            ];

            Log::info('Getting datacenter from VDSina', [
                'action' => $action
            ]);

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->get($action, $headers);
            $result = $response->getBody()->getContents();
            $data = json_decode($result, true);

            if (!is_array($data)) {
                Log::error('Invalid JSON response from VDSina', [
                    'response' => $result
                ]);
                throw new RuntimeException('Invalid JSON response from VDSina');
            }

            if (!isset($data['status']) || $data['status'] !== 'ok') {
                Log::error('Error response from VDSina', [
                    'response' => $data
                ]);
                throw new RuntimeException('Error response from VDSina: ' . ($data['status_msg'] ?? 'Unknown error'));
            }

            Log::info('Successfully got datacenter from VDSina');

            return $data;

        } catch (GuzzleException $e) {
            Log::error('HTTP error while getting datacenter from VDSina', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        } catch (Exception $e) {
            Log::error('Error while getting datacenter from VDSina', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Error getting datacenter from VDSina: ' . $e->getMessage());
        }
    }

    //Список шаблонов операционных систем, доступных для установки или переустановки сервера
    //Интересует id = 23, Ubuntu 24.04
    /**
     * @throws GuzzleException
     */
    public function getTemplate(): array
    {
        try {
            $action = 'template';

            $headers = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ]
            ];

            Log::info('Getting template from VDSina', [
                'action' => $action
            ]);

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->get($action, $headers);
            $result = $response->getBody()->getContents();
            $data = json_decode($result, true);

            if (!is_array($data)) {
                Log::error('Invalid JSON response from VDSina', [
                    'response' => $result
                ]);
                throw new RuntimeException('Invalid JSON response from VDSina');
            }

            if (!isset($data['status']) || $data['status'] !== 'ok') {
                Log::error('Error response from VDSina', [
                    'response' => $data
                ]);
                throw new RuntimeException('Error response from VDSina: ' . ($data['status_msg'] ?? 'Unknown error'));
            }

            Log::info('Successfully got template from VDSina');

            return $data;

        } catch (GuzzleException $e) {
            Log::error('HTTP error while getting template from VDSina', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        } catch (Exception $e) {
            Log::error('Error while getting template from VDSina', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Error getting template from VDSina: ' . $e->getMessage());
        }
    }

    //Список тарифных планов, доступен по ID группы сервера
    //Интересует Standard Server id = 2
    //Из полученного списка тарифных планов выбираем по индексу 0 с id = 1
    /**
     * @throws GuzzleException
     */
    public function getServerPlan(): array
    {
        try {
            $action = 'server-plan/2';

            $headers = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ]
            ];

            Log::info('Getting server plan from VDSina', [
                'action' => $action
            ]);

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->get($action, $headers);
            $result = $response->getBody()->getContents();
            $data = json_decode($result, true);

            if (!is_array($data)) {
                Log::error('Invalid JSON response from VDSina', [
                    'response' => $result
                ]);
                throw new RuntimeException('Invalid JSON response from VDSina');
            }

            if (!isset($data['status']) || $data['status'] !== 'ok') {
                Log::error('Error response from VDSina', [
                    'response' => $data
                ]);
                throw new RuntimeException('Error response from VDSina: ' . ($data['status_msg'] ?? 'Unknown error'));
            }

            Log::info('Successfully got server plan from VDSina');

            return $data;

        } catch (GuzzleException $e) {
            Log::error('HTTP error while getting server plan from VDSina', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        } catch (Exception $e) {
            Log::error('Error while getting server plan from VDSina', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Error getting server plan from VDSina: ' . $e->getMessage());
        }
    }

    //получить список серверов

    /**
     * @throws GuzzleException
     */
    public function getServers(): array
    {
        try {
            $action = 'server';

            $headers = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ]
            ];

            Log::info('Getting servers from VDSina', [
                'action' => $action
            ]);

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->get($action, $headers);
            $result = $response->getBody()->getContents();
            $data = json_decode($result, true);

            if (!is_array($data)) {
                Log::error('Invalid JSON response from VDSina', [
                    'response' => $result
                ]);
                throw new RuntimeException('Invalid JSON response from VDSina');
            }

            if (!isset($data['status']) || $data['status'] !== 'ok') {
                Log::error('Error response from VDSina', [
                    'response' => $data
                ]);
                throw new RuntimeException('Error response from VDSina: ' . ($data['status_msg'] ?? 'Unknown error'));
            }

            Log::info('Successfully got servers from VDSina');

            return $data;

        } catch (GuzzleException $e) {
            Log::error('HTTP error while getting servers from VDSina', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        } catch (Exception $e) {
            Log::error('Error while getting servers from VDSina', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Error getting servers from VDSina: ' . $e->getMessage());
        }
    }

    /**
     * @param string $server_name //имя сервера
     * @param int $server_plan //id = 1 - базовый тарифный план
     * @param int $autoprolong //0 - без авто продления, 1 - авто продление
     * @param int $datacenter //id = 1 - Amsterdam
     * @param int $template //id = 23 - Ubuntu 24.04
     * @return array
     * @throws GuzzleException
     */
    public function createServer(
        string $server_name,
        int    $server_plan,
        int    $autoprolong = 0,
        int    $datacenter = 1,
        int    $template = 23
    ): array
    {
        try {
            $action = 'server';

            $headers = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ],
                RequestOptions::JSON => [
                    'name' => $server_name,
                    'server-plan' => $server_plan,
                    'autoprolong' => $autoprolong,
                    'datacenter' => $datacenter,
                    'template' => $template,
                    'backup_auto' => 0 //авто бекап
                ]
            ];

            Log::info('Creating server in VDSina', [
                'action' => $action,
                'name' => $server_name,
                'server-plan' => $server_plan,
                'datacenter' => $datacenter,
                'template' => $template
            ]);

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->post($action, $headers);
            $result = $response->getBody()->getContents();
            $data = json_decode($result, true);

            if (!is_array($data)) {
                Log::error('Invalid JSON response from VDSina', [
                    'response' => $result
                ]);
                throw new RuntimeException('Invalid JSON response from VDSina');
            }

            if (!isset($data['status']) || $data['status'] !== 'ok') {
                Log::error('Error response from VDSina', [
                    'response' => $data
                ]);
                throw new RuntimeException('Error response from VDSina: ' . ($data['status_msg'] ?? 'Unknown error'));
            }

            Log::info('Successfully created server in VDSina', [
                'server_id' => $data['data']['id'] ?? null
            ]);

            return $data;

        } catch (GuzzleException $e) {
            Log::error('HTTP error while creating server in VDSina', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        } catch (Exception $e) {
            Log::error('Error while creating server in VDSina', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Error creating server in VDSina: ' . $e->getMessage());
        }
    }

    /**
     * @param int $provider_id
     * @return array
     * @throws GuzzleException
     */
    public function getServerById(int $provider_id): array
    {
        try {
            $action = 'server/' . $provider_id;

            $headers = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ]
            ];

            Log::info('Getting server from VDSina', [
                'provider_id' => $provider_id,
                'action' => $action
            ]);

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->get($action, $headers);
            $result = $response->getBody()->getContents();
            $data = json_decode($result, true);

            if (!is_array($data)) {
                Log::error('Invalid JSON response from VDSina', [
                    'provider_id' => $provider_id,
                    'response' => $result
                ]);
                throw new RuntimeException('Invalid JSON response from VDSina');
            }

            if (!isset($data['status']) || $data['status'] !== 'ok') {
                Log::error('Error response from VDSina', [
                    'provider_id' => $provider_id,
                    'response' => $data
                ]);
                throw new RuntimeException('Error response from VDSina: ' . ($data['status_msg'] ?? 'Unknown error'));
            }

            if (!isset($data['data']) || !is_array($data['data'])) {
                Log::error('Invalid data structure from VDSina', [
                    'provider_id' => $provider_id,
                    'response' => $data
                ]);
                throw new RuntimeException('Invalid data structure from VDSina');
            }

            Log::info('Successfully got server from VDSina', [
                'provider_id' => $provider_id,
                'status' => $data['data']['status'] ?? 'unknown'
            ]);

            return $data;

        } catch (GuzzleException $e) {
            Log::error('HTTP error while getting server from VDSina', [
                'provider_id' => $provider_id,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        } catch (Exception $e) {
            Log::error('Error while getting server from VDSina', [
                'provider_id' => $provider_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Error getting server from VDSina: ' . $e->getMessage());
        }
    }

    /**
     * @param int $provider_id
     * @param string $password
     * @return array
     * @throws GuzzleException
     */
    public function updatePassword(int $provider_id, string $password): array
    {
        try {
            $action = 'server.password/' . $provider_id;

            $requestParam = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ],
                'json' => [
                    'password' => $password
                ],
            ];

            Log::info('Updating server password in VDSina', [
                'provider_id' => $provider_id,
                'action' => $action
            ]);

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->put($action, $requestParam);
            $result = $response->getBody()->getContents();
            $data = json_decode($result, true);

            if (!is_array($data)) {
                Log::error('Invalid JSON response from VDSina', [
                    'provider_id' => $provider_id,
                    'response' => $result
                ]);
                throw new RuntimeException('Invalid JSON response from VDSina');
            }

            if (!isset($data['status']) || $data['status'] !== 'ok') {
                Log::error('Error response from VDSina', [
                    'provider_id' => $provider_id,
                    'response' => $data
                ]);
                throw new RuntimeException('Error response from VDSina: ' . ($data['status_msg'] ?? 'Unknown error'));
            }

            Log::info('Successfully updated server password in VDSina', [
                'provider_id' => $provider_id
            ]);

            return $data;

        } catch (GuzzleException $e) {
            Log::error('HTTP error while updating password in VDSina', [
                'provider_id' => $provider_id,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        } catch (Exception $e) {
            Log::error('Error while updating password in VDSina', [
                'provider_id' => $provider_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Error updating password in VDSina: ' . $e->getMessage());
        }
    }

    /**
     * @param int $provider_id
     * @return array
     * @throws GuzzleException
     */
    public function deleteServer(int $provider_id): array
    {
        try {
            $action = 'server/' . $provider_id;

            $requestParam = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ]
            ];

            Log::info('Deleting server from VDSina', [
                'provider_id' => $provider_id,
                'action' => $action
            ]);

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->delete($action, $requestParam);
            $result = $response->getBody()->getContents();
            $data = json_decode($result, true);

            if (!is_array($data)) {
                Log::error('Invalid JSON response from VDSina', [
                    'provider_id' => $provider_id,
                    'response' => $result
                ]);
                throw new RuntimeException('Invalid JSON response from VDSina');
            }

            if (!isset($data['status']) || $data['status'] !== 'ok') {
                Log::error('Error response from VDSina', [
                    'provider_id' => $provider_id,
                    'response' => $data
                ]);
                throw new RuntimeException('Error response from VDSina: ' . ($data['status_msg'] ?? 'Unknown error'));
            }

            Log::info('Successfully deleted server from VDSina', [
                'provider_id' => $provider_id
            ]);

            return $data;

        } catch (GuzzleException $e) {
            Log::error('HTTP error while deleting server from VDSina', [
                'provider_id' => $provider_id,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        } catch (Exception $e) {
            Log::error('Error while deleting server from VDSina', [
                'provider_id' => $provider_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Error deleting server from VDSina: ' . $e->getMessage());
        }
    }
}
