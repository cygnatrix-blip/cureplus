<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CustomDomain;
use App\Services\Domains\DomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DomainController extends Controller
{
    public function index(): View
    {
        return view('app.domains.index', [
            'domains' => CustomDomain::query()->latest('id')->get(),
            'connectHost' => config('cureplus.custom_domains.connect_host'),
            'ipv4' => config('cureplus.custom_domains.ipv4'),
            'ipv6' => config('cureplus.custom_domains.ipv6'),
        ]);
    }

    public function store(Request $request, DomainService $domains): RedirectResponse
    {
        $request->merge(['hostname' => strtolower(trim((string) $request->input('hostname')))]);

        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:190', 'regex:/^(?!-)([a-z0-9-]{1,63}\.)+[a-z]{2,}$/', Rule::unique('custom_domains', 'hostname')],
            'type' => ['required', 'in:subdomain,root'],
        ], ['hostname.regex' => 'Enter a domain like book.yourclinic.com.', 'hostname.unique' => 'This domain is already in use.']);

        // Our own hosts must never become a tenant's "custom domain".
        $platform = strtolower((string) config('cureplus.domain'));

        if ($data['hostname'] === $platform || str_ends_with($data['hostname'], '.'.$platform)) {
            throw ValidationException::withMessages(['hostname' => 'Use your own domain here.']);
        }

        $domain = CustomDomain::create($data);
        $result = $domains->checkDns($domain);

        return back()->with($result['ok'] ? 'success' : 'status', $result['ok'] ? 'DNS looks right. Our team will finish connecting it.' : 'Domain saved. Add the DNS record below, then press Check DNS.');
    }

    public function check(CustomDomain $domain, DomainService $domains): RedirectResponse
    {
        $result = $domains->checkDns($domain);

        return back()->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'DNS looks right. Our team will finish connecting it.' : (string) $result['problem']);
    }

    public function destroy(CustomDomain $domain): RedirectResponse
    {
        $domain->delete();

        return back()->with('success', 'Domain removed.');
    }
}
