<?php

namespace App\Services;

use App\Models\Organization;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BrandingService
{
    /**
     * Re-encoding through GD strips metadata and anything hiding inside the upload,
     * and gives the installed app the exact icon sizes a manifest needs.
     */
    public function saveLogo(Organization $organization, UploadedFile $file): void
    {
        $source = $this->decode($file, 'logo');
        $disk = Storage::disk(config('filesystems.default'));
        $paths = [];

        foreach ([192, 512] as $size) {
            $canvas = imagecreatetruecolor($size, $size);
            imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));

            $scale = min($size / imagesx($source), $size / imagesy($source));
            $width = (int) (imagesx($source) * $scale);
            $height = (int) (imagesy($source) * $scale);
            imagecopyresampled($canvas, $source, (int) (($size - $width) / 2), (int) (($size - $height) / 2), 0, 0, $width, $height, imagesx($source), imagesy($source));

            ob_start();
            imagepng($canvas);
            $path = 'branding/'.$organization->id.'/'.Str::uuid().'.png';
            $disk->put($path, (string) ob_get_clean());
            $paths[$size] = $path;
        }

        $this->deleteIcons($organization);

        $organization->forceFill(['logo_path' => $paths[512], 'icon_192_path' => $paths[192], 'icon_512_path' => $paths[512]])->save();
    }

    public function removeLogo(Organization $organization): void
    {
        $this->deleteIcons($organization);
        $organization->forceFill(['logo_path' => null, 'icon_192_path' => null, 'icon_512_path' => null])->save();
    }

    /**
     * Re-encoded like the logo, and scaled down because patients are often on weak networks.
     */
    public function saveCover(Organization $organization, UploadedFile $file): void
    {
        $source = $this->decode($file, 'cover');
        $width = min(1600, imagesx($source));
        $height = (int) round(imagesy($source) * $width / imagesx($source));
        $canvas = imagecreatetruecolor($width, $height);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        // PHP builds without JPEG support still get a working cover, only a larger file.
        $canWriteJpeg = function_exists('imagejpeg');

        ob_start();
        $canWriteJpeg ? imagejpeg($canvas, null, 80) : imagepng($canvas);
        $path = 'branding/'.$organization->id.'/'.Str::uuid().($canWriteJpeg ? '.jpg' : '.png');
        Storage::disk(config('filesystems.default'))->put($path, (string) ob_get_clean());

        $this->removeCover($organization);
        $organization->forceFill(['cover_image_path' => $path])->save();
    }

    public function removeCover(Organization $organization): void
    {
        if ($organization->cover_image_path) {
            Storage::disk(config('filesystems.default'))->delete($organization->cover_image_path);
        }

        $organization->forceFill(['cover_image_path' => null])->save();
    }

    /**
     * A file that passes validation can still be unreadable: damaged, or a JPEG on a
     * PHP build without JPEG support. The person gets a message, not an error page.
     *
     * @throws ValidationException
     */
    private function decode(UploadedFile $file, string $field): GdImage
    {
        try {
            $image = imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        } catch (Throwable) {
            $image = false;
        }

        if ($image === false) {
            throw ValidationException::withMessages([$field => 'This image could not be read. Please try a different picture.']);
        }

        return $image;
    }

    private function deleteIcons(Organization $organization): void
    {
        $disk = Storage::disk(config('filesystems.default'));

        foreach (array_filter([$organization->icon_192_path, $organization->icon_512_path]) as $old) {
            $disk->delete($old);
        }
    }
}
