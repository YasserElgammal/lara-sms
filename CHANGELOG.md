# Changelog

Changes are grouped by release. Only versions with an existing Git tag are
listed as released; pending changes remain under Unreleased.

## [2.0.0] - 2026-09-16

Major release introducing queued SMS sending, explicit error classification, improved fallback handling, and privacy-conscious structured logging.

### Added

- Queued sending through `SmsSendBuilder::queue(connection: ..., queue: ...)`
  and the `SendSms` job, preserving the message, fallback strategy, and gateway
  order. The job allows one queue attempt and throws on a failed send.
- Nullable `SmsResult::retryable`, also exposed by `toArray()` and individual
  attempt records: `true` for retryable failures, `false` for permanent failures,
  and `null` for unknown failures.
- `InvalidConfigurationException`, extending `NonRetryableException`, plus
  validation of configuration structure, gateway classes and order, and HTTP
  timeout, attempt count, and retry delay.
- Structured `send.started`, `gateway.completed`, `send.completed`, and debug
  `http.completed` logs with correlation IDs, durations, attempt counts, HTTP
  status, and error classification.
- PHPUnit configuration and 16 fake-HTTP regression tests covering sending,
  retries, fallback, configuration, queues, and logging privacy.
- A Composer lockfile for reproducible development dependencies and an ignore
  rule for the PHPUnit cache.
- Reliability, queue operation, and logging documentation in the README, plus
  technical review reports in Markdown and HTML.

### Changed

- **Breaking:** `FAIL_FAST` now continues only when `retryable === true`.
  Permanent and unknown failures stop fallback; error-message text no longer
  determines the decision. `TRY_ALL` continues after any failed result.
- **Breaking:** Explicit runtime requirements are PHP `^8.2` and Illuminate
  contracts, HTTP, support, bus, and queue components `^12.0`.
- **Breaking:** Unknown gateway names and an empty gateway order now raise
  `InvalidConfigurationException` instead of being skipped or returning a
  generic failed result. Invalid configuration is rejected early.
- Built-in gateways preserve typed exception classification in failed results;
  existing credential and sender checks use permanent configuration errors.
- Package logging masks recipients and omits raw URLs, headers, payloads,
  provider responses, message IDs, and exception text. Result objects still
  contain provider diagnostics.

### Fixed

- HTTP 429 responses now use the same configured retry handling as server
  errors. `retry_attempts` includes the initial request.
- HTTP request-exception classification uses the response status instead of
  the exception code.

### Upgrade notes

- Custom gateways should set `SmsResult::retryable` or throw
  `RetryableException` / `NonRetryableException`. Returning only an error string
  leaves the failure unknown and stops `FAIL_FAST`.
- Connection failures are unknown and are not retried by the HTTP layer.
  Provider errors inside successful HTTP responses remain unknown unless
  explicitly classified by the gateway.
- For asynchronous sending, select a non-sync queue connection and run a queue
  worker. `queue()` returns the dispatcher result, not an SMS delivery result.
  HTTP retries and fallback still execute within the single queue attempt.
- Set worker timeout above the complete retry/fallback duration, and queue
  `retry_after` or visibility timeout above worker timeout. Retries and manual
  job replay can duplicate messages; exactly-once delivery is not guaranteed.
- Update log consumers to use the structured event names and fields.

## [1.0.0] - 2025-10-23

Initial tagged release (`1.0.0`).

### Added

- Ten SMS gateway integrations: Dreams, Infobip, Jawaly, MobilySms, Msegat,
  SMS Misr, Taqnyat, Twilio, Unifonic, and Vonage.
- Laravel service provider, `LaraSms` facade, and publishable SMS configuration.
- Fluent message builder, quick sending, sender selection, and message metadata.
- `SmsMessage` and `SmsResult` data objects with gateway attempt history.
- `TRY_ALL` and `FAIL_FAST` fallback strategies and custom gateway ordering.
- Shared HTTP connection with configurable timeout, retry attempts, and delay.
- `SmsException`, `RetryableException`, and `NonRetryableException` types.
- Installation, provider configuration, and usage documentation; MIT license.
