<?php

namespace Lalalili\TextToSpeech\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Lalalili\TextToSpeech\Contracts\TextToSpeechDriverInterface;
use Lalalili\TextToSpeech\Support\TextToSpeechOptions;
use RuntimeException;
use Throwable;

class GeminiTextToSpeechDriver implements TextToSpeechDriverInterface
{
    public function synthesize(string $input, TextToSpeechOptions $options): string
    {
        if ($options->inputType === 'ssml') {
            throw new InvalidArgumentException('Gemini TTS does not support SSML input.');
        }

        $endpoint = $this->resolveEndpoint();
        $apiKey = $this->resolveApiKey();
        $voice = $this->resolveVoice($options);

        $payload = [
            'contents' => [
                ['parts' => [['text' => $input]]],
            ],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => $voice,
                        ],
                    ],
                ],
            ],
        ];

        $request = Http::withHeader('x-goog-api-key', $apiKey)
            ->acceptJson()
            ->contentType('application/json');

        $response = $this->applyHttpOptions($request)->post($endpoint, $payload);

        $this->logResponseSummary($response, $endpoint);

        if (! $response->successful()) {
            $this->throwRequestException($response);
        }

        $json = $response->json();
        $inlineData = $json['candidates'][0]['content']['parts'][0]['inlineData'] ?? null;

        if (! is_array($inlineData) || ! isset($inlineData['data'])) {
            throw new RuntimeException('Gemini TTS response did not contain audio data.');
        }

        $pcmBytes = base64_decode($inlineData['data'], true);

        if ($pcmBytes === false || $pcmBytes === '') {
            throw new RuntimeException('Gemini TTS returned invalid base64 audio data.');
        }

        $mimeType = (string) ($inlineData['mimeType'] ?? '');
        $sampleRate = $this->parseSampleRate($mimeType);

        return match ($options->audioFormat) {
            'mp3' => $this->pcmToMp3($pcmBytes, $sampleRate),
            default => $this->wrapPcmAsWav($pcmBytes, $sampleRate),
        };
    }

    private function resolveEndpoint(): string
    {
        $baseUrl = rtrim((string) config('text-to-speech.drivers.gemini.base_url', 'https://generativelanguage.googleapis.com'), '/');
        $model = config('text-to-speech.drivers.gemini.model');

        if (! is_string($model) || $model === '') {
            throw new RuntimeException('Gemini TTS model is not configured. Set GEMINI_TTS_MODEL in .env.');
        }

        return sprintf('%s/v1beta/models/%s:generateContent', $baseUrl, $model);
    }

    private function resolveApiKey(): string
    {
        $key = config('text-to-speech.drivers.gemini.api_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Gemini TTS API key is not configured. Set GEMINI_TTS_API_KEY in .env.');
        }

        return $key;
    }

    private function resolveVoice(TextToSpeechOptions $options): string
    {
        if ($options->voice !== '') {
            return $options->voice;
        }

        throw new RuntimeException('Gemini TTS voice is not configured.');
    }

    private function applyHttpOptions(PendingRequest $request): PendingRequest
    {
        $timeout = config('text-to-speech.drivers.gemini.timeout_seconds');

        if (is_numeric($timeout)) {
            $request = $request->timeout((float) $timeout);
        }

        $connectTimeout = config('text-to-speech.drivers.gemini.connect_timeout_seconds');

        if (is_numeric($connectTimeout)) {
            $request = $request->connectTimeout((float) $connectTimeout);
        }

        $retryTimes = (int) config('text-to-speech.drivers.gemini.retry_times', 0);

        if ($retryTimes > 0) {
            $sleepMilliseconds = (int) config('text-to-speech.drivers.gemini.retry_sleep_ms', 0);
            $retryStatuses = $this->resolveRetryStatuses();

            $request = $request->retry(
                $retryTimes,
                $sleepMilliseconds,
                function (Throwable $exception, PendingRequest $request) use ($retryStatuses): bool {
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
        $statuses = config('text-to-speech.drivers.gemini.retry_on_statuses', []);

        if (! is_array($statuses)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $statuses))));
    }

    private function parseSampleRate(string $mimeType): int
    {
        if (preg_match('/rate=(\d+)/i', $mimeType, $matches)) {
            $rate = (int) $matches[1];

            if ($rate > 0) {
                return $rate;
            }
        }

        return 24000;
    }

    private function wrapPcmAsWav(string $pcmBytes, int $sampleRate): string
    {
        $pcmSize = strlen($pcmBytes);
        $byteRate = $sampleRate * 2; // mono, 16-bit

        $header = 'RIFF';
        $header .= pack('V', 36 + $pcmSize);
        $header .= 'WAVE';
        $header .= 'fmt ';
        $header .= pack('V', 16);
        $header .= pack('v', 1);
        $header .= pack('v', 1);
        $header .= pack('V', $sampleRate);
        $header .= pack('V', $byteRate);
        $header .= pack('v', 2);
        $header .= pack('v', 16);
        $header .= 'data';
        $header .= pack('V', $pcmSize);

        return $header.$pcmBytes;
    }

    private function pcmToMp3(string $pcmBytes, int $sampleRate): string
    {
        $ffmpeg = (string) config('text-to-speech.drivers.gemini.ffmpeg_path', 'ffmpeg');

        $command = sprintf(
            '%s -loglevel error -f s16le -ar %d -ac 1 -i pipe:0 -f mp3 pipe:1',
            escapeshellcmd($ffmpeg),
            $sampleRate,
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start ffmpeg process for PCM→MP3 conversion.');
        }

        fwrite($pipes[0], $pcmBytes);
        fclose($pipes[0]);

        $mp3 = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0 || $mp3 === false || $mp3 === '') {
            throw new RuntimeException(sprintf(
                'ffmpeg PCM→MP3 conversion failed (exit %d): %s',
                $exitCode,
                trim((string) $stderr),
            ));
        }

        return $mp3;
    }

    private function throwRequestException(Response $response): void
    {
        $message = sprintf('Gemini TTS request failed with status %s.', $response->status());
        $body = trim((string) $response->body());

        if ($body !== '') {
            $message .= ' '.$body;
        }

        throw new RuntimeException($message);
    }

    private function logResponseSummary(Response $response, string $endpoint): void
    {
        Log::info('Gemini TTS response', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'content_type' => $response->header('Content-Type'),
            'x_request_id' => $response->header('x-request-id'),
        ]);
    }
}
