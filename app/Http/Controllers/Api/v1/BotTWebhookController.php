<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\BotT\BotTService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BotTWebhookController extends Controller
{
    private BotTService $botTService;

    public function __construct(BotTService $botTService)
    {
        $this->botTService = $botTService;
    }

    /**
     * Обработка вебхука после успешной оплаты заказа
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function handleOrderPayment(Request $request): JsonResponse
    {
        try {
            Log::info('BOT-T Webhook: Order payment received', [
                'source' => 'bott_webhook',
                'request_data' => $request->all()
            ]);

            // Валидация данных заказа
            // Согласно реальным данным BOT-T, category - это объект с полями id, api_id, category_id и т.д.
            $validator = Validator::make($request->all(), [
                'id' => 'required',
                'count' => 'required|integer|min:1',
                'category' => 'required|array',
                'user' => 'required|array',
                'user.telegram_id' => 'required|integer',
                'product' => 'required|array',
                'status' => 'required|string',
                'amount' => 'required|integer'
            ]);
            
            if ($validator->fails()) {
                Log::warning('BOT-T Webhook: Validation failed', [
                    'source' => 'bott_webhook',
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Invalid request data',
                    'errors' => $validator->errors()
                ], 400);
            }
            
            // Проверяем наличие category.id или category.api_id (хотя бы одного)
            $category = $request->input('category', []);
            $hasCategoryId = isset($category['id']);
            $hasCategoryApiId = isset($category['api_id']);
            
            if (!$hasCategoryId && !$hasCategoryApiId) {
                Log::warning('BOT-T Webhook: Category ID or API ID is missing', [
                    'source' => 'bott_webhook',
                    'category' => $category,
                    'request_data' => $request->all()
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Category ID or API ID is required',
                    'category_structure' => $category
                ], 400);
            }

            if ($validator->fails()) {
                Log::warning('BOT-T Webhook: Validation failed', [
                    'source' => 'bott_webhook',
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Invalid request data',
                    'errors' => $validator->errors()
                ], 400);
            }

            $orderData = $request->all();
            
            Log::info('BOT-T Webhook: Starting order processing', [
                'source' => 'bott_webhook',
                'order_id' => $orderData['id'] ?? 'unknown',
                'category_id' => $orderData['category']['id'] ?? null,
                'category_api_id' => $orderData['category']['api_id'] ?? null,
                'user_telegram_id' => $orderData['user']['telegram_id'] ?? null,
                'count' => $orderData['count'] ?? null
            ]);

            // Обрабатываем заказ
            $result = $this->botTService->processOrder($orderData);
            
            Log::info('BOT-T Webhook: Order processing result', [
                'source' => 'bott_webhook',
                'order_id' => $orderData['id'] ?? 'unknown',
                'success' => $result['success'] ?? false,
                'error' => $result['error'] ?? null,
                'pack_salesman_id' => $result['pack_salesman_id'] ?? null,
                'keys_created' => $result['keys_created'] ?? 0
            ]);

            if ($result['success']) {
                Log::info('BOT-T Webhook: Order processed successfully', [
                    'source' => 'bott_webhook',
                    'order_id' => $orderData['id'],
                    'pack_salesman_id' => $result['pack_salesman_id'] ?? null,
                    'keys_created' => $result['keys_created'] ?? 0
                ]);

                return response()->json(['success' => true], 200);
            } else {
                Log::error('BOT-T Webhook: Order processing failed', [
                    'source' => 'bott_webhook',
                    'order_id' => $orderData['id'],
                    'error' => $result['error'] ?? 'Unknown error'
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Order processing failed'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('BOT-T Webhook: Exception during order processing', [
                'source' => 'bott_webhook',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Проверка товара перед выдачей клиенту (для уникальных товаров)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validateProduct(Request $request): JsonResponse
    {
        try {
            Log::info('BOT-T Webhook: Product validation requested', [
                'source' => 'bott_webhook',
                'product' => $request->input('product')
            ]);

            $validator = Validator::make($request->all(), [
                'product' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Product field is required'
                ], 400);
            }

            $product = $request->input('product');

            // Проверяем, не использован ли уже этот ключ
            $isValid = $this->botTService->validateProduct($product);

            if ($isValid) {
                Log::info('BOT-T Webhook: Product validated successfully', [
                    'source' => 'bott_webhook',
                    'product' => substr($product, 0, 50) . '...'
                ]);

                return response()->json(['success' => true], 200);
            } else {
                Log::warning('BOT-T Webhook: Product validation failed (already used)', [
                    'source' => 'bott_webhook',
                    'product' => substr($product, 0, 50) . '...'
                ]);

                return response()->json(['success' => false], 200);
            }
        } catch (\Exception $e) {
            Log::error('BOT-T Webhook: Exception during product validation', [
                'source' => 'bott_webhook',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // При ошибке возвращаем false, чтобы товар не был выдан
            return response()->json(['success' => false], 200);
        }
    }
}

