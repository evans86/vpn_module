<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod\PaymentMethod;
use App\Logging\DatabaseLogger;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    private DatabaseLogger $logger;

    public function __construct(DatabaseLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Список способов оплаты (редирект на главную страницу с вкладками)
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.module.order.index', ['tab' => 'payment-methods']);
    }

    /**
     * Создать способ оплаты
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'type' => 'required|string|in:bank,crypto,ewallet,other',
                'details' => 'required|string',
                'instructions' => 'nullable|string',
                'is_active' => 'nullable|boolean',
                'sort_order' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return redirect()->route('admin.module.order.index', ['tab' => 'payment-methods'])
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', 'Пожалуйста, исправьте ошибки в форме.');
            }

            PaymentMethod::create([
                'name' => $request->name,
                'type' => $request->type,
                'details' => $request->details,
                'instructions' => $request->instructions ?? null,
                'is_active' => $request->has('is_active') ? (bool)$request->is_active : true,
                'sort_order' => $request->sort_order ? (int)$request->sort_order : 0,
            ]);

            $this->logger->info('Payment method created', [
                'name' => $request->name,
                'source' => 'admin'
            ]);

            return redirect()->route('admin.module.order.index', ['tab' => 'payment-methods'])
                ->with('success', 'Способ оплаты создан');
        } catch (ValidationException $e) {
            return redirect()->route('admin.module.order.index', ['tab' => 'payment-methods'])
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Ошибка валидации данных.');
        } catch (Exception $e) {
            $this->logger->error('Error creating payment method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'admin'
            ]);

            return redirect()->route('admin.module.order.index', ['tab' => 'payment-methods'])
                ->withInput()
                ->with('error', 'Ошибка при создании способа оплаты: ' . $e->getMessage());
        }
    }

    /**
     * Обновить способ оплаты
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'type' => 'required|string|in:bank,crypto,ewallet,other',
                'details' => 'required|string',
                'instructions' => 'nullable|string',
                'is_active' => 'nullable|boolean',
                'sort_order' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return redirect()->route('admin.module.order.index', ['tab' => 'payment-methods'])
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', 'Пожалуйста, исправьте ошибки в форме.');
            }

            $paymentMethod = PaymentMethod::findOrFail($id);
            $paymentMethod->update([
                'name' => $request->name,
                'type' => $request->type,
                'details' => $request->details,
                'instructions' => $request->instructions ?? null,
                'is_active' => $request->has('is_active') ? (bool)$request->is_active : false,
                'sort_order' => $request->sort_order ? (int)$request->sort_order : 0,
            ]);

            $this->logger->info('Payment method updated', [
                'payment_method_id' => $id,
                'source' => 'admin'
            ]);

            return redirect()->route('admin.module.order.index', ['tab' => 'payment-methods'])
                ->with('success', 'Способ оплаты обновлен');
        } catch (ValidationException $e) {
            return redirect()->route('admin.module.order.index', ['tab' => 'payment-methods'])
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Ошибка валидации данных.');
        } catch (Exception $e) {
            $this->logger->error('Error updating payment method', [
                'payment_method_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'admin'
            ]);

            return redirect()->route('admin.module.order.index', ['tab' => 'payment-methods'])
                ->withInput()
                ->with('error', 'Ошибка при обновлении способа оплаты: ' . $e->getMessage());
        }
    }

    /**
     * Удалить способ оплаты
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $paymentMethod = PaymentMethod::findOrFail($id);
            $paymentMethod->delete();

            $this->logger->info('Payment method deleted', [
                'payment_method_id' => $id,
                'source' => 'admin'
            ]);

            return redirect()->route('admin.module.order.index', ['tab' => 'payment-methods'])
                ->with('success', 'Способ оплаты удален');
        } catch (Exception $e) {
            $this->logger->error('Error deleting payment method', [
                'payment_method_id' => $id,
                'error' => $e->getMessage(),
                'source' => 'admin'
            ]);

            return redirect()->route('admin.module.order.index', ['tab' => 'payment-methods'])
                ->with('error', 'Ошибка при удалении способа оплаты: ' . $e->getMessage());
        }
    }
}

