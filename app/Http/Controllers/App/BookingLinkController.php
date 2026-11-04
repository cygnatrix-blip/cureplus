<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Services\QrCodeService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class BookingLinkController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(QrCodeService $qr): View
    {
        $links = $this->links();

        return view('app.booking-links.index', [
            'links' => collect($links)->map(fn (array $link) => $link + ['qr' => $qr->svg($link['url'], 160)]),
        ]);
    }

    public function download(Request $request, QrCodeService $qr): Response
    {
        $link = collect($this->links())->firstWhere('key', (string) $request->query('link')) ?? abort(404);

        return response($qr->svg($link['url'], 800), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qr-'.$link['key'].'.svg"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function poster(Request $request, QrCodeService $qr): View
    {
        $link = collect($this->links())->firstWhere('key', (string) $request->query('link')) ?? abort(404);

        return view('app.booking-links.poster', ['link' => $link, 'qr' => $qr->svg($link['url'], 420), 'organization' => $this->tenant->organization()]);
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    private function links(): array
    {
        $organization = $this->tenant->organization();
        $links = [['key' => 'org', 'label' => $organization->name, 'url' => route('booking.show', $organization->slug)]];

        // A solo practice's single link already is the doctor's link.
        if ($organization->isSolo()) {
            return $links;
        }

        $doctors = Membership::query()->active()->whereNotNull('doctor_slug')->with('user:id,name')->get();

        foreach ($doctors as $doctor) {
            $links[] = [
                'key' => 'doctor-'.$doctor->id,
                'label' => (string) $doctor->user?->name,
                'url' => route('booking.doctor', [$organization->slug, $doctor->doctor_slug]),
            ];
        }

        return $links;
    }
}
