# Changelog

All notable changes to `lalalili/text-to-speech` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-07-27

### Changed

- 首個穩定版。此後遵循
  [SEMVER.md](https://github.com/lalalili/.github/blob/main/SEMVER.md)
  定義的 public API 契約,宿主可安全使用 `^1.0` 約束。
- 對其他 lalalili 套件的約束一律收斂為 `^1.0`,取代先前 `^0.x`
  與多段 OR 的寫法。
- `repositories` 改用 GitHub VCS,不再依賴宿主 `packages/` 底下的
  兄弟目錄;測試資源改從 `vendor/lalalili/*` 讀取。
- 移除 `minimum-stability` / `prefer-stable` 宣告,授權統一為 MIT。

### 為什麼是 1.0.0

Composer 對 `^0.1.1` 的解讀是 `>=0.1.1 <0.2.0`,0.x 期間每發一個 minor
都需要所有宿主手動改 `composer.json`,否則 `composer update` 永遠拿不到
新版。本套件生態曾因此讓宿主停在數十個 commit 之前而無人察覺。

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
