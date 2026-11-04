<?php

namespace App\Http\Controllers;

use App\Models\CustomDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * For the VPS setup: Caddy asks this before requesting a certificate. Only the
 * machine itself may ask, and only domains that passed verification get a yes.
 */
class DomainApprovalController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), 404);

        $allowed = CustomDomain::withoutTenancy()
            ->where('hostname', strtolower((string) $request->query('domain')))
            ->whereIn('status', ['awaiting_parking', 'parked', 'active'])
            ->exists();

        return response($allowed ? 'ok' : 'no', $allowed ? 200 : 404);
    }
}
