<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * A link a patient can open without signing in. The signature is the permission, so it cannot
 * be altered to reach another record, and it stops working when it expires.
 */
final class DocumentLink
{
    public static function prescription(int $id): string
    {
        return URL::temporarySignedRoute('files.prescription', self::expires(), ['prescription' => $id]);
    }

    public static function invoice(int $id): string
    {
        return URL::temporarySignedRoute('files.invoice', self::expires(), ['invoice' => $id]);
    }

    private static function expires(): \DateTimeInterface
    {
        return now()->addHours((int) config('cureplus.document_link_hours'));
    }
}
