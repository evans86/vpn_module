<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\VPN\VpnDirectDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class VpnDirectDomainController extends Controller
{
    public function edit(VpnDirectDomain $vpnDirectDomain): View
    {
        return view('module.vpn-direct-domains.edit', [
            'item' => $vpnDirectDomain,
        ]);
    }

    public function index(): View
    {
        $items = VpnDirectDomain::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('module.vpn-direct-domains.index', [
            'items' => $items,
            'publicJsonUrl' => url('/vpn/routing/direct-domains.json'),
            'publicRuleSetUrl' => url('/vpn/routing/direct-domains-rule-set.json'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'domain' => ['required', 'string', 'max:253'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $normalized = VpnDirectDomain::normalizeDomain((string) $request->input('domain', ''));
            if ($normalized === '') {
                $validator->errors()->add('domain', 'Укажите домен или ссылку с корректным хостом.');

                return;
            }
            if (! self::isValidDomainPattern($normalized)) {
                $validator->errors()->add('domain', 'Недопустимый формат. Пример: bank.ru, .ru, *.com, *.gosuslugi.ru');

                return;
            }
            if (VpnDirectDomain::query()->where('domain', $normalized)->exists()) {
                $validator->errors()->add('domain', 'Такой домен уже есть в списке.');
            }
        });

        if ($validator->fails()) {
            return redirect()->route('admin.module.vpn-direct-domains.index')
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Проверьте поля формы.');
        }

        $normalized = VpnDirectDomain::normalizeDomain((string) $request->input('domain'));

        VpnDirectDomain::create([
            'domain' => $normalized,
            'sort_order' => $request->input('sort_order') !== null && $request->input('sort_order') !== ''
                ? (int) $request->input('sort_order')
                : 0,
            'is_enabled' => true,
            'note' => $request->input('note') ?: null,
        ]);

        return redirect()->route('admin.module.vpn-direct-domains.index')
            ->with('success', 'Домен добавлен. Список доступен клиентам по публичному JSON.');
    }

    public function update(Request $request, VpnDirectDomain $vpnDirectDomain): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'domain' => ['required', 'string', 'max:253'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'note' => ['nullable', 'string', 'max:500'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request, $vpnDirectDomain): void {
            $normalized = VpnDirectDomain::normalizeDomain((string) $request->input('domain', ''));
            if ($normalized === '') {
                $validator->errors()->add('domain', 'Укажите домен или ссылку с корректным хостом.');

                return;
            }
            if (! self::isValidDomainPattern($normalized)) {
                $validator->errors()->add('domain', 'Недопустимый формат. Пример: bank.ru, .ru, *.com, *.gosuslugi.ru');

                return;
            }
            $exists = VpnDirectDomain::query()
                ->where('domain', $normalized)
                ->where('id', '!=', $vpnDirectDomain->id)
                ->exists();
            if ($exists) {
                $validator->errors()->add('domain', 'Такой домен уже есть в списке.');
            }
        });

        if ($validator->fails()) {
            return redirect()->route('admin.module.vpn-direct-domains.edit', $vpnDirectDomain)
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Проверьте поля формы.');
        }

        $normalized = VpnDirectDomain::normalizeDomain((string) $request->input('domain'));

        $vpnDirectDomain->update([
            'domain' => $normalized,
            'sort_order' => $request->input('sort_order') !== null && $request->input('sort_order') !== ''
                ? (int) $request->input('sort_order')
                : 0,
            'note' => $request->input('note') ?: null,
            'is_enabled' => $request->boolean('is_enabled'),
        ]);

        return redirect()->route('admin.module.vpn-direct-domains.index')
            ->with('success', 'Запись обновлена.');
    }

    public function destroy(VpnDirectDomain $vpnDirectDomain): RedirectResponse
    {
        $vpnDirectDomain->delete();

        return redirect()->route('admin.module.vpn-direct-domains.index')
            ->with('success', 'Домен удалён из списка.');
    }

    public function toggle(VpnDirectDomain $vpnDirectDomain): RedirectResponse
    {
        $vpnDirectDomain->is_enabled = ! $vpnDirectDomain->is_enabled;
        $vpnDirectDomain->save();

        return redirect()->route('admin.module.vpn-direct-domains.index')
            ->with('success', $vpnDirectDomain->is_enabled ? 'Домен включён.' : 'Домен отключён (остаётся в списке, но не отдаётся в JSON).');
    }

    private static function isValidDomainPattern(string $domain): bool
    {
        if (strlen($domain) > 253) {
            return false;
        }

        if (! preg_match('/^(\*\.)?[a-z0-9*]([a-z0-9*.-]*[a-z0-9*])?$/i', $domain)) {
            return false;
        }

        if (strpos($domain, '*.') === 0) {
            $suffix = substr($domain, 2);
            if ($suffix === '') {
                return false;
            }
            // Вся зона: *.ru, *.com (одна метка после *.)
            if (strpos($suffix, '.') === false) {
                return self::isValidPublicSuffixLabel($suffix);
            }

            return substr_count($domain, '.') >= 2;
        }

        // TLD / публичная зона одной меткой: ru, com, xn--p1ai
        if (strpos($domain, '.') === false) {
            return self::isValidPublicSuffixLabel($domain);
        }

        return true;
    }

    /**
     * Одна метка зоны (ccTLD/gTLD/punycode), 2–63 символа.
     */
    private static function isValidPublicSuffixLabel(string $label): bool
    {
        $len = strlen($label);
        if ($len < 2 || $len > 63) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/i', $label);
    }
}
