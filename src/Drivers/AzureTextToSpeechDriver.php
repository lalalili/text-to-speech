<?php

namespace Lalalili\TextToSpeech\Drivers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Lalalili\TextToSpeech\Contracts\TextToSpeechDriverInterface;
use Lalalili\TextToSpeech\Support\TextToSpeechOptions;
use RuntimeException;
use Throwable;

class AzureTextToSpeechDriver implements TextToSpeechDriverInterface
{
    public function synthesize(string $input, TextToSpeechOptions $options): string
    {
        $endpoint = $this->resolveEndpoint();
        $key = $this->resolveKey();
        $voice = $this->resolveVoice($options);
        $languageCode = $this->resolveLanguageCode($options);

        $ssml = $options->inputType === 'ssml'
            ? $input
            : $this->buildSsml($input, $voice, $languageCode, $options);

        $request = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $key,
            'X-Microsoft-OutputFormat' => $this->resolveOutputFormat($options),
            'User-Agent' => $this->resolveUserAgent(),
        ])->withBody($ssml, 'application/ssml+xml');

        $response = $this->applyHttpOptions($request)->post($endpoint);

        $this->logResponseSummary($response, $endpoint);

        if (! $response->successful()) {
            $this->throwRequestException($response);
        }

        return (string) $response->body();
    }

    private function resolveEndpoint(): string
    {
        $endpoint = config('text-to-speech.drivers.azure.endpoint');

        if (is_string($endpoint) && $endpoint !== '') {
            return $endpoint;
        }

        $region = config('text-to-speech.drivers.azure.region');

        if (! is_string($region) || $region === '') {
            throw new RuntimeException('Azure Text-to-Speech region is not configured.');
        }

        return sprintf('https://%s.tts.speech.microsoft.com/cognitiveservices/v1', $region);
    }

    private function resolveKey(): string
    {
        $key = config('text-to-speech.drivers.azure.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Azure Text-to-Speech key is not configured.');
        }

        return $key;
    }

    private function resolveVoice(TextToSpeechOptions $options): string
    {
        if ($options->voice !== '') {
            return $options->voice;
        }

        throw new RuntimeException('Azure Text-to-Speech voice is not configured.');
    }

    private function resolveLanguageCode(TextToSpeechOptions $options): string
    {
        if ($options->languageCode !== '') {
            return $options->languageCode;
        }

        throw new RuntimeException('Azure Text-to-Speech language code is not configured.');
    }

    private function resolveUserAgent(): string
    {
        $userAgent = config('text-to-speech.drivers.azure.user_agent');

        if (is_string($userAgent) && $userAgent !== '') {
            return $userAgent;
        }

        return 'text-to-speech';
    }

    private function applyHttpOptions(PendingRequest $request): PendingRequest
    {
        $timeout = config('text-to-speech.drivers.azure.timeout_seconds');

        if (is_numeric($timeout)) {
            $request->timeout((float) $timeout);
        }

        $connectTimeout = config('text-to-speech.drivers.azure.connect_timeout_seconds');

        if (is_numeric($connectTimeout)) {
            $request->connectTimeout((float) $connectTimeout);
        }

        $retryTimes = (int) config('text-to-speech.drivers.azure.retry_times', 0);

        if ($retryTimes > 0) {
            $sleepMilliseconds = (int) config('text-to-speech.drivers.azure.retry_sleep_ms', 0);
            $retryStatuses = $this->resolveRetryStatuses();

            $request->retry(
                $retryTimes,
                $sleepMilliseconds,
                function (Throwable $exception, PendingRequest $request, ?string $method) use ($retryStatuses): bool {
                    if ($exception instanceof RequestException) {
                        return in_array($exception->response->status(), $retryStatuses, true);
                    }

                    return $exception instanceof ConnectionException;
                },
                false,
            );
        }

        return $request;
    }

    /**
     * @return array<int, int>
     */
    private function resolveRetryStatuses(): array
    {
        $statuses = config('text-to-speech.drivers.azure.retry_on_statuses', []);

        if (! is_array($statuses)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $statuses))));
    }

    private function resolveOutputFormat(TextToSpeechOptions $options): string
    {
        $outputFormat = config('text-to-speech.drivers.azure.output_format');

        if (is_string($outputFormat) && $outputFormat !== '') {
            return $outputFormat;
        }

        return match ($options->audioFormat) {
            'ogg_opus' => 'ogg-16khz-16bit-mono-opus',
            'linear16' => 'riff-16khz-16bit-mono-pcm',
            default => 'audio-16khz-128kbitrate-mono-mp3',
        };
    }

    private function buildSsml(string $input, string $voice, string $languageCode, TextToSpeechOptions $options): string
    {
        $text = htmlspecialchars($input, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $prosodyAttributes = $this->buildProsodyAttributes($options);

        $content = $text;

        if ($prosodyAttributes !== []) {
            $attributes = $this->formatAttributes($prosodyAttributes);
            $content = sprintf('<prosody %s>%s</prosody>', $attributes, $content);
        }

        return sprintf(
            '<speak version="1.0" xml:lang="%s" xmlns="http://www.w3.org/2001/10/synthesis"><voice name="%s">%s</voice></speak>',
            htmlspecialchars($languageCode, ENT_XML1 | ENT_COMPAT, 'UTF-8'),
            htmlspecialchars($voice, ENT_XML1 | ENT_COMPAT, 'UTF-8'),
            $content,
        );
    }

    /**
     * @return array<string, string>
     */
    private function buildProsodyAttributes(TextToSpeechOptions $options): array
    {
        $attributes = [];

        if ($options->speakingRate !== 1.0) {
            $attributes['rate'] = $this->formatRate($options->speakingRate);
        }

        if ($options->pitch !== 0.0) {
            $attributes['pitch'] = $this->formatPitch($options->pitch);
        }

        return $attributes;
    }

    private function formatRate(float $speakingRate): string
    {
        $percent = (int) round(($speakingRate - 1.0) * 100);

        return sprintf('%+d%%', $percent);
    }

    private function formatPitch(float $pitch): string
    {
        $value = round($pitch, 1);
        $formatted = rtrim(rtrim(sprintf('%+0.1f', $value), '0'), '.');

        return $formatted.'st';
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function formatAttributes(array $attributes): string
    {
        $pairs = [];

        foreach ($attributes as $key => $value) {
            $pairs[] = sprintf('%s="%s"', $key, htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
        }

        return implode(' ', $pairs);
    }

    private function throwRequestException(Response $response): void
    {
        $message = sprintf('Azure Text-to-Speech request failed with status %s.', $response->status());
        $body = trim((string) $response->body());

        if ($body !== '') {
            $message .= ' '.$body;
        }

        throw new RuntimeException($message);
    }

    private function logResponseSummary(Response $response, string $endpoint): void
    {
        $contentLength = $response->header('Content-Length');
        $length = strlen((string) $response->body());

        Log::info('Azure TTS response', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'content_length' => $contentLength,
            'body_length' => $length,
            'content_type' => $response->header('Content-Type'),
            'x_request_id' => $response->header('x-requestid'),
        ]);
    }
}
