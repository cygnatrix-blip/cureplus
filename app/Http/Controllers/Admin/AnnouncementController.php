<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\Messaging\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.announcements.index', ['announcements' => Announcement::query()->latest('id')->paginate(25)]);
    }

    public function store(Request $request, Notifier $notifier): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'organization_slugs' => ['nullable', 'string', 'max:2000'],
            'ends_at' => ['nullable', 'date', 'after:now'],
            'send_push' => ['sometimes', 'boolean'],
        ]);

        $slugs = array_filter(array_map('trim', explode(',', (string) ($data['organization_slugs'] ?? ''))));
        $organizationIds = $slugs === [] ? null : Organization::query()->whereIn('slug', $slugs)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($slugs !== [] && $organizationIds === []) {
            return back()->withErrors(['organization_slugs' => 'No organization matches those link names.'])->withInput();
        }

        Announcement::query()->create([
            'title' => $data['title'],
            'body' => $data['body'],
            'organization_ids' => $organizationIds,
            'starts_at' => now(),
            'ends_at' => $data['ends_at'] ?? null,
            'published_by' => $request->user('platform')->id,
        ]);

        if ($request->boolean('send_push')) {
            $this->pushToMembers($notifier, $organizationIds, $data['title'], $data['body']);
        }

        return back()->with('success', 'Announcement published.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', 'Announcement removed.');
    }

    /**
     * @param  list<int>|null  $organizationIds
     */
    private function pushToMembers(Notifier $notifier, ?array $organizationIds, string $title, string $body): void
    {
        $userIds = Membership::withoutTenancy()
            ->where('status', 'active')
            ->when($organizationIds !== null, fn ($query) => $query->whereIn('organization_id', (array) $organizationIds))
            ->distinct()
            ->pluck('user_id');

        User::query()->whereIn('id', $userIds)->select(['id', 'name'])->chunk(200, function ($users) use ($notifier, $title, $body): void {
            foreach ($users as $user) {
                $notifier->notifyUser($user, null, 'announcement', ['title' => $title, 'body' => $body], route('app.dashboard'));
            }
        });
    }
}
