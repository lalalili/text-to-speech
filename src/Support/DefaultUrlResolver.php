<?php

namespace Lalalili\TextToSpeech\Support;

use Illuminate\Support\Facades\Storage;

class DefaultUrlResolver
{
    public static function resolve(string $disk, string $path, ?int $temporaryUrlTtlMinutes = null): string
    {
        $filesystem = Storage::disk($disk);
        $visibility = config('text-to-speech.storage.visibility');

        if ($visibility === null) {
            $visibility = config("filesystems.disks.{$disk}.visibility");
        }

        if ($visibility === 'public') {
            return $filesystem->url($path);
        }

        $ttl = $temporaryUrlTtlMinutes ?? (int) config('text-to-speech.storage.temporary_url_ttl_minutes', 15);

        if (method_exists($filesystem, 'temporaryUrl')) {
            return $filesystem->temporaryUrl($path, now()->addMinutes($ttl));
        }

        return $filesystem->url($path);
    }
}
