<?php

namespace App\Services\Messaging;

use App\Models\Template;
use Illuminate\Support\Facades\Cache;

class MessageWording
{
    public function textFor(string $event, string $default): string
    {
        /** @var string $override */
        $override = Cache::remember('message-template.'.$event, 3600, function () use ($event): string {
            $template = Template::withoutTenancy()->whereNull('organization_id')->where('kind', 'message')->where('template_key', $event)->first();

            return (string) ($template?->getAttribute('body')['text'] ?? '');
        });

        return $override !== '' ? $override : $default;
    }
}
