# Changelog

All notable changes to `lalalili/text-to-speech` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-06-26

First tagged release (previously consumed from `dev-main`).

### Added

- `TextToSpeechManager` + `TextToSpeechService` with pluggable drivers
  (Google Cloud, Gemini, Azure) behind `TextToSpeechDriverInterface`.
- `TextToSpeech` facade and `config/text-to-speech.php`.
- Queued synthesis via `GenerateTextToSpeechAudioJob` with audio caching/hashing.
- Character counting with SSML stripping (`CharacterCounterInterface`).
- Request + daily/monthly metric models and aggregation/cleanup/retry/stats
  console commands.
- CI (PHP 8.3/8.4) and tag-triggered release workflows; pest + phpstan (level in
  `phpstan.neon.dist`) + pint.
