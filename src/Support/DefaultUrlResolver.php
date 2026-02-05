<?php

namespace Lalalili\TextToSpeech\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DefaultUrlResolver
{
    public static function resolve(string $disk, string $path, ?int $temporaryUrlTtlMinutes = null): string
    {
        $filesystem = Storage::disk($disk);
        /** @var \Illuminate\Filesystem\FilesystemAdapter $filesystem */
        $visibility = config('text-to-speech.storage.visibility');

        if ($visibility === null) {
            $visibility = config("filesystems.disks.{$disk}.visibility");
        }

        if ($visibility === 'public') {
            return $filesystem->url($path);
        }

        $ttl = $temporaryUrlTtlMinutes ?? (int) config('text-to-speech.storage.temporary_url_ttl_minutes', 15);

        try {
            return $filesystem->temporaryUrl($path, now()->addMinutes($ttl));
        } catch (RuntimeException) {
            return $filesystem->url($path);
        }
    }
}
