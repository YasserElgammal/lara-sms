# Lara SMS Gateway Package

A powerful and flexible Laravel SMS gateway package with support for multiple providers  
(**MobilySms**, **Msegat**, **Dreams**, **Taqnyat**, **Jawaly**, **Infobip**, **Twilio**, **Vonage**, **Unifonic**, **SMS Misr**)
intelligent fallback mechanisms, and a fluent builder pattern for clean message construction.

---

## [2.0.0]

Version 2.0.0 requires PHP 8.2+ and Laravel / Illuminate 12.x.

- Send messages asynchronously with `SmsSendBuilder::queue()`.
- Use `SmsResult::retryable` to distinguish retryable, permanent, and unknown
  failures.
- `FAIL_FAST` now moves to the next gateway only after an explicitly retryable
  failure, while `TRY_ALL` continues after any failure.
- HTTP `429` and `5xx` responses use the configured retry policy.
- Invalid configuration and unknown gateway names now throw
  `InvalidConfigurationException`.
- Structured logs provide correlation IDs, durations, and attempt counts while
  masking recipients and excluding sensitive message and provider data.

Custom gateways should set `SmsResult::retryable` or throw
`RetryableException` / `NonRetryableException`. See
[CHANGELOG.md](CHANGELOG.md) for the complete release notes and upgrade details.

---

## Features

- ✅ **Multiple Gateway Support** - Dreams, Infobip, Jawaly, MobilySms, Msegat, SMS Misr, Taqnyat, Twilio, Unifonic, and Vonage
- ✅ **Fluent Builder Pattern** - Build and send messages through a clean, expressive API
- ✅ **Queued Sending** - Dispatch SMS messages through Laravel queues
- ✅ **Fallback Strategies** - Try All, Fail Fast, and custom gateway ordering
- ✅ **Intelligent HTTP Retries** - Retry HTTP 429 and 5xx responses automatically
- ✅ **Explicit Error Classification** - Retryable, permanent, and unknown failures
- ✅ **Configuration Validation** - Detect invalid settings and unknown gateways early
- ✅ **Structured Logging** - Correlated events, timings, and attempt counts with sensitive data masked
- ✅ **Clean DTOs** - Structured message and result objects
- ✅ **Laravel Integration** - Service provider, facade, configuration, and queue support
- ✅ **Metadata Support** - Attach custom data to messages

---

## Installation

### Step 1: Install via Composer

```bash
composer require yasser-elgammal/lara-sms
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=sms-config

```

### Step 3: Add Environment Variables

Add needed sms gateways to your `.env` file:

```env

# --------------------------------------------------------------------------
# SMS Default Gateway
# --------------------------------------------------------------------------
#
# You can set the default SMS gateway in your .env file using:
#
#   SMS_DEFAULT_GATEWAY=mobilysms
#
# Supported gateway names:
#   - infobip
#   - vonage
#   - jawaly
#   - smsmisr
#   - msegat
#   - mobilysms
#   - dreams
#   - taqnyat
#   - twilio
#
# Example:
#   SMS_DEFAULT_GATEWAY=msegat
#


# ===============================
# 🌐 GENERAL SMS SETTINGS
# ===============================
SMS_DEFAULT_GATEWAY=jawaly
SMS_FALLBACK_STRATEGY=fail_fast
SMS_TIMEOUT=30
SMS_RETRY_ATTEMPTS=3
SMS_RETRY_DELAY=1000


# ===============================
# 🇸🇦 SAUDI GATEWAYS
# ===============================

# 🇸🇦 MobilySms GATEWAY
MOBILY_USERNAME=your_username
MOBILY_PASSWORD=your_password
MOBILY_SENDER=YourApp
MOBILY_BASE_URL=https://www.mobilysms.net

# 🇸🇦 MSEGAT GATEWAY
MSEGAT_USERNAME=your_msegat_username
MSEGAT_API_KEY=your_msegat_api_key
MSEGAT_USER_SENDER=auth-mseg
MSEGAT_MSG_ENCODING=UTF8

# 🇸🇦 DREAMS GATEWAY
DREAMS_USERNAME=your_dreams_username
DREAMS_SECRET_KEY=your_dreams_secret_key
DREAMS_SENDER=YourApp

# 🇸🇦 TAQNYAT GATEWAY
TAQNYAT_TOKEN=your_taqnyat_token
TAQNYAT_SENDER_ID=YourApp

# 🇸🇦 JAWALY GATEWAY
JAWALY_API_KEY=your_jawaly_api_key
JAWALY_API_SECRET=your_jawaly_api_secret
JAWALY_SENDER=YourApp

# ===============================
# 🌍 INTERNATIONAL GATEWAYS
# ===============================

# 🌍 INFOBIP
INFOBIP_API_KEY=
INFOBIP_SENDER=
INFOBIP_BASE_URL=

# 🇺🇸 TWILIO GATEWAY
TWILIO_SID=your_twilio_sid
TWILIO_TOKEN=your_twilio_token
TWILIO_FROM=+1234567890

# 🌍 VONAGE GATEWAY
VONAGE_API_KEY=your_vonage_api_key
VONAGE_API_SECRET=your_vonage_api_secret
VONAGE_SENDER=YourApp

# 🌍 UNIFONIC
UNIFONIC_APP_SID=
UNIFONIC_TOKEN=
UNIFONIC_SENDER=
UNIFONIC_BASE_URL=

# ===============================
# 🇪🇬 EGYPT GATEWAYS
# ===============================

# 🇪🇬 SMS MISR GATEWAY
SMSMISR_USERNAME=your_smsmisr_username
SMSMISR_PASSWORD=your_smsmisr_password
SMSMISR_SENDER=YourApp

```


### Step 4: Clear Cache

```bash
php artisan config:cache
```
---

## Quick Start

### Simplest Usage - Quick Send

```php
use YasserElgammal\LaraSms\Facades\LaraSms;
use Illuminate\Support\Facades\Log;

// Send a quick SMS
$result = LaraSms::quickSend('201234567890', 'Hello World!');

if ($result->success) {
    Log::debug("SMS sent! Message ID: {$result->messageId}");
} else {
    Log::debug("Failed: {$result->error}");
}
```

## Usage Guide

### Basic Usage

#### 1. Quick Send (One-liner)

```php
use YasserElgammal\LaraSms\Facades\LaraSms;

LaraSms::quickSend('201234567890', 'Your OTP is 123456');
```

#### 2. Builder with Basic Fields

```php
LaraSms::builder()
    ->to('201234567890')
    ->text('Hello World!')
    ->send();
```

#### 3. Builder with All Fields

```php
LaraSms::builder()
    ->to('201234567890')
    ->from('MyApp')
    ->text('Your verification code is: 123456')
    ->send();
```

---

### Builder Pattern - Complete Reference

The builder provides a fluent interface for constructing SMS messages with full control over sending options.

#### Setting Recipients and Messages

```php
LaraSms::builder()
    ->to('201234567890')           // Set recipient (required)
    ->from('MyApp')                 // Set sender (optional)
    ->text('Hello!')                // Set message text (required)
    ->send();
```

#### Adding Metadata

Attach custom data to your SMS:

```php
LaraSms::builder()
    ->to('201234567890')
    ->text('Hello World !')
    ->metadata([
        'language' => '2', // 1=Eng, 2=Arabic, 3=Unicode
        'environment' => '1', //1=Live, 2=Test
        'delay_until' => '2025-12-31 23:59:00' // example if message scheduling
    ])->gateway('smsmisr')->send();
```

#### Controlling Fallback Strategies

```php
// Strategy 1: Try All Gateways
// Attempts all gateways until one succeeds
LaraSms::builder()
    ->to('201234567890')
    ->text('Hello!')
    ->useFallback(FallbackStrategy::TRY_ALL)
    ->send();

// Or use helper
LaraSms::builder()
    ->to('201234567890')
    ->text('Hello!')
    ->tryAll()
    ->send();
```

```php
// Strategy 2: Fail Fast (Default)
// Stops on first non-retryable error
LaraSms::builder()
    ->to('201234567890')
    ->text('Hello!')
    ->useFallback(FallbackStrategy::FAIL_FAST)
    ->send();

// Or use helper
LaraSms::builder()
    ->to('201234567890')
    ->text('Hello!')
    ->failFast()
    ->send();
```

#### Gateway Selection and Ordering

```php
// Use specific gateway order
LaraSms::builder()
    ->to('201234567890')
    ->text('Hello!')
    ->gateways(['jawaly', 'twilio', 'vonage'])  // Custom order
    ->send();

// Use only one gateway (no fallback)
LaraSms::builder()
    ->to('201234567890')
    ->text('Hello!')
    ->gateway('jawaly')  // Only Jawaly, fail if it fails
    ->send();

// Restrict to specific gateways
LaraSms::builder()
    ->to('201234567890')
    ->text('Hello!')
    ->gateways(['jawaly', 'twilio'])  // Skip others
    ->send();
```
**Behavior:**
- Stops immediately on non-retryable error (auth, invalid phone, etc.)
- Continues on retryable errors (timeout, server 5xx)
- More cost-effective
- Faster failure response

#### Custom Gateway Order

```php
// Prefer cheaper gateway (local) first
SMS::builder()
    ->to('201234567890')
    ->text('Marketing message')
    ->gateways(['smsmisr'])  // Local only
    ->send();

// Regional preference
SMS::builder()
    ->to('201234567890')
    ->text('Important message')
    ->gateways(['jawaly', 'smsmisr', 'twilio'])  // Local first, then international
    ->send();

// Quality vs Cost trade-off
SMS::builder()
    ->to('201234567890')
    ->text('Verification code')
    ->gateways(['twilio', 'vonage', 'jawaly'])  // Premium first, fallback to budget
    ->send();
```

---

### Error Handling

#### Understanding SmsResult

```php
$result = SMS::builder()
    ->to('201234567890')
    ->text('Hello!')
    ->send();

// Result properties:
$result->success;      // bool - Did it succeed?
$result->messageId;    // ?string - ID from gateway
$result->gateway;      // ?string - Which gateway succeeded
$result->error;        // ?string - Error message if failed
$result->attempts;     // array - All attempts made

// Example usage
use Illuminate\Support\Facades\Log;

if ($result->success) {
    Log::debug("✅ Message sent via {$result->gateway}");
    Log::debug("📧 Message ID: {$result->messageId}");
} else {
    Log::debug("❌ All gateways failed");
    Log::debug("📋 Error: {$result->error}");
    
    // Check attempts
    foreach ($result->attempts as $attempt) {
        Log::debug(
            $attempt['gateway'] . ': ' .
            ($attempt['success'] ? '✓' : '✗ ' . $attempt['error'])
        );
    }
}
```

---

## 🤝 Contributing

Pull requests are welcome!
For major changes, please open an issue first to discuss what you’d like to modify.

---

## ⚖️ License

MIT © 2025 **Yasser Elgammal**


## Reliability and queued sending

Requires PHP 8.2+ and Laravel / Illuminate 12.x (matching the Testbench 10 test suite).

`SmsResult::retryable` is `true` for typed retryable failures, `false` for typed permanent failures, and `null` for unknown failures. Custom gateways should set this field or throw `RetryableException` / `NonRetryableException`. Error text is for diagnostics only. `FAIL_FAST` continues only after an explicitly retryable failure; `TRY_ALL` continues after any failed result. This changes fallback behavior for custom gateways returning only error strings.

HTTP 429 and 5xx responses use the configured retry count and delay. `retry_attempts` counts total HTTP attempts, including the first request. Other HTTP errors are permanent. Connection failures remain unknown and are not retried by the HTTP layer: a timeout may occur after the provider accepted the message. Provider-specific errors returned with HTTP 200 remain unknown unless the gateway explicitly classifies them.

```php
LaraSms::builder()
    ->to('+201234567890')
    ->text('Your verification code is 123456')
    ->gateway('twilio')
    ->queue(connection: 'redis', queue: 'sms');
```

Configure a non-sync queue connection and run a worker for the `sms` queue. Queue dispatch returns the dispatcher result, not an SMS delivery result. The job has one attempt and throws on a failed send so Laravel records the failure. HTTP retries and selected fallback still happen inside that attempt. There is no automatic replay of the entire job.

This reduces duplicate sends but does not guarantee exactly-once delivery. HTTP 5xx retries, TRY_ALL after timeouts, worker crashes, and manually retrying failed jobs can still duplicate messages. Check provider delivery state before manual replay. Set the worker timeout above the complete HTTP retry/fallback budget and the queue retry_after (or SQS visibility timeout) above the worker timeout. See [Laravel queue timeouts](https://laravel.com/docs/12.x/queues#job-expirations-and-timeouts).

Run the fake-HTTP regression suite with `composer test`. Tests never send real SMS.


### Structured logging

Managed sends emit `send.started`, `gateway.completed`, and `send.completed` events with a random `correlation_id` shared across fallback gateways and HTTP retries. Each new send gets a new ID. Gateway events include `gateway_attempt`, `duration_ms`, `success`, and `error_classification` (`retryable`, `permanent`, or `unknown`; null on success). Completion events include total duration and gateway attempt count.

The HTTP layer emits `http.completed` at debug level for each attempt, including `http_attempt`, `max_attempts`, HTTP `status` (null for a connection failure), and request duration excluding retry sleep. Enable debug logging to collect these details. Gateway and total durations include retry delays. Provider acceptance is not proof of final handset delivery.

Package logs show only the recipient's last four characters; short recipients are fully masked. Raw URLs, headers, payloads, provider responses, message IDs, and exception text are omitted. Result objects still contain provider diagnostics: avoid logging them wholesale. Custom gateway logging and external HTTP/queue instrumentation must apply their own privacy controls. These structured events can feed your existing log monitoring; no dashboard or alerting service is installed.
