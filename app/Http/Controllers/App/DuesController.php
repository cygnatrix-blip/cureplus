<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\Money;
use Illuminate\View\View;

class DuesController extends Controller
{
    public function __invoke(): View
    {
        $query = Invoice::query()->whereIn('status', ['unpaid', 'partial']);

        return view('app.invoices.dues', [
            'invoices' => (clone $query)->with('patient:id,name,mobile')->oldest('issued_at')->paginate(50),
            'totalDue' => Money::format((int) (clone $query)->selectRaw('COALESCE(SUM(total_paise - paid_paise),0) due')->toBase()->value('due')),
        ]);
    }
}
