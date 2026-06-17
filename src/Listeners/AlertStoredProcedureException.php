<?php

namespace Aotr\DynamicLevelHelper\Listeners;

use Aotr\DynamicLevelHelper\Events\StoredProcedureExceptionEvent;
use Illuminate\Support\Facades\Log;

/**
 * Sends alerts when a stored procedure throws an exception.
 *
 * Alert channels (configurable via dynamic-levels-helper.enhanced_db_service.alerts):
 * - log      : always logs to the stp channel (default)
 * - email    : sends email via Laravel Mail
 * - slack    : posts to a Slack webhook
 * - webhook  : POSTs full payload to a custom URL
 *
 * Each channel can be independently enabled/disabled and filtered by
 * error severity (retryable vs fatal, connection vs query error).
 */
class AlertStoredProcedureException
{
    /**
     * Handle the event.
     */
    public function handle(StoredProcedureExceptionEvent $event): void
    {
        $alertConfig = $this->getAlertConfig();

        if (!($alertConfig['enabled'] ?? true)) {
            return;
        }

        // ── 1. Always log the exception ──
        $this->logException($event);

        // ── 2. Email alert ──
        if (!empty($alertConfig['channels']['email']['enabled'])) {
            $this->sendEmailAlert($event, $alertConfig['channels']['email']);
        }

        // ── 3. Slack alert ──
        if (!empty($alertConfig['channels']['slack']['enabled'])) {
            $this->sendSlackAlert($event, $alertConfig['channels']['slack']);
        }

        // ── 4. Custom webhook alert ──
        if (!empty($alertConfig['channels']['webhook']['enabled'])) {
            $this->sendWebhookAlert($event, $alertConfig['channels']['webhook']);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    //  Log
    // ──────────────────────────────────────────────────────────────────

    private function logException(StoredProcedureExceptionEvent $event): void
    {
        $channel = config('dynamic-levels-helper.enhanced_db_service.logging.channel', 'stp');

        Log::channel($channel)->critical(
            "🚨 EXCEPTION in stored procedure [{$event->storedProcedureName}] "
            . "(attempt {$event->attempt}/{$event->maxAttempts})",
            [
                'stored_procedure'  => $event->storedProcedureName,
                'parameters'        => $event->parameters,
                'connection'        => $event->connection,
                'sql'               => $event->sql,
                'exception_class'   => $event->getExceptionClass(),
                'exception_code'    => $event->getExceptionCode(),
                'exception_message' => $event->getExceptionMessage(),
                'exception_file'    => $event->exception->getFile(),
                'exception_line'    => $event->exception->getLine(),
                'retryable'         => $event->retryable,
                'connection_error'  => $event->connectionError,
                'retries_exhausted' => $event->retriesExhausted,
                'execution_time'    => round($event->executionTime, 4),
                'execution_history' => $event->executionHistory,
                'timestamp'         => now()->toISOString(),
            ]
        );
    }

    // ──────────────────────────────────────────────────────────────────
    //  Email
    // ──────────────────────────────────────────────────────────────────

    private function sendEmailAlert(StoredProcedureExceptionEvent $event, array $config): void
    {
        // Filter by severity
        if (! $this->shouldAlert($event, $config)) {
            return;
        }

        $recipients = $config['recipients'] ?? [];
        if (empty($recipients)) {
            return;
        }

        try {
            // Build a rich HTML email body
            $subject = "🚨 SP Exception: {$event->storedProcedureName} — {$event->getExceptionMessage()}";
            $body    = $this->buildEmailBody($event);

            // Use Laravel Mailable if available, else fall back to raw mail
            if (class_exists(\Illuminate\Support\Facades\Mail::class)) {
                foreach ($recipients as $recipient) {
                    \Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($recipient, $subject) {
                        $message->to($recipient)
                                ->subject($subject)
                                ->html($body);
                    });
                }
            } elseif (function_exists('mail')) {
                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: " . ($config['from'] ?? 'noreply@example.com') . "\r\n";

                foreach ($recipients as $recipient) {
                    mail($recipient, $subject, $body, $headers);
                }
            }

            Log::channel('stp')->info("📧 Email alert sent for [{$event->storedProcedureName}]", [
                'recipients' => $recipients,
            ]);
        } catch (\Exception $e) {
            Log::channel('stp')->error("Failed to send email alert: {$e->getMessage()}", [
                'stored_procedure' => $event->storedProcedureName,
                'error'            => $e->getMessage(),
            ]);
        }
    }

    private function buildEmailBody(StoredProcedureExceptionEvent $event): string
    {
        $severity = $event->retriesExhausted ? '🔴 FATAL' : '🟡 RETRYABLE';
        $severityColor = $event->retriesExhausted ? '#dc3545' : '#ffc107';

        $html = <<<HTML
        <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 700px; margin: 0 auto;">
            <div style="background: {$severityColor}; color: #fff; padding: 16px 20px; border-radius: 8px 8px 0 0;">
                <h2 style="margin:0;">{$severity} — Stored Procedure Exception</h2>
            </div>
            <div style="border: 1px solid #dee2e6; border-top: none; padding: 20px; border-radius: 0 0 8px 8px;">
                <table style="width:100%; border-collapse: collapse;">
                    <tr><td style="font-weight:bold; padding:6px 12px; background:#f8f9fa; width:180px;">Stored Procedure</td><td style="padding:6px 12px;">{$event->storedProcedureName}</td></tr>
                    <tr><td style="font-weight:bold; padding:6px 12px; background:#f8f9fa;">Connection</td><td style="padding:6px 12px;">{$event->connection}</td></tr>
                    <tr><td style="font-weight:bold; padding:6px 12px; background:#f8f9fa;">Attempt</td><td style="padding:6px 12px;">{$event->attempt} / {$event->maxAttempts}</td></tr>
                    <tr><td style="font-weight:bold; padding:6px 12px; background:#f8f9fa;">Execution Time</td><td style="padding:6px 12px;">{$event->executionTime}s</td></tr>
                    <tr><td style="font-weight:bold; padding:6px 12px; background:#f8f9fa;">Retryable</td><td style="padding:6px 12px;">{$event->retryable}</td></tr>
                    <tr><td style="font-weight:bold; padding:6px 12px; background:#f8f9fa;">Connection Error</td><td style="padding:6px 12px;">{$event->connectionError}</td></tr>
                    <tr><td style="font-weight:bold; padding:6px 12px; background:#f8f9fa;">Exception Class</td><td style="padding:6px 12px;">{$event->getExceptionClass()}</td></tr>
                    <tr><td style="font-weight:bold; padding:6px 12px; background:#f8f9fa;">Exception Code</td><td style="padding:6px 12px;">{$event->getExceptionCode()}</td></tr>
                </table>
                <h3 style="margin-top:16px; color:#dc3545;">Exception Message</h3>
                <pre style="background:#f8f9fa; padding:12px; border-radius:4px; overflow-x:auto; font-size:13px;">{$event->getExceptionMessage()}</pre>
                <h3 style="margin-top:16px;">SQL</h3>
                <pre style="background:#f8f9fa; padding:12px; border-radius:4px; overflow-x:auto; font-size:13px;">{$event->sql}</pre>
                <h3 style="margin-top:16px;">Parameters</h3>
                <pre style="background:#f8f9fa; padding:12px; border-radius:4px; overflow-x:auto; font-size:13px;">" . json_encode($event->parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>
HTML;

        if (!empty($event->executionHistory)) {
            $html .= '<h3 style="margin-top:16px;">Execution History</h3>';
            $html .= '<table style="width:100%; border-collapse:collapse; border:1px solid #dee2e6;">';
            $html .= '<tr style="background:#f8f9fa;"><th style="padding:6px; border:1px solid #dee2e6;">Attempt</th><th style="padding:6px; border:1px solid #dee2e6;">Error</th><th style="padding:6px; border:1px solid #dee2e6;">Retryable</th></tr>';
            foreach ($event->executionHistory as $h) {
                $err = htmlspecialchars($h['error'] ?? '');
                $retry = ($h['retryable'] ?? false) ? 'Yes' : 'No';
                $html .= "<tr><td style='padding:6px; border:1px solid #dee2e6;'>{$h['attempt']}</td><td style='padding:6px; border:1px solid #dee2e6;'>{$err}</td><td style='padding:6px; border:1px solid #dee2e6;'>{$retry}</td></tr>";
            }
            $html .= '</table>';
        }

        $html .= '<p style="margin-top:20px; color:#6c757d; font-size:12px;">Timestamp: ' . now()->toDateTimeString() . ' | Server: ' . gethostname() . '</p>';
        $html .= '</div></div>';

        return $html;
    }

    // ──────────────────────────────────────────────────────────────────
    //  Slack
    // ──────────────────────────────────────────────────────────────────

    private function sendSlackAlert(StoredProcedureExceptionEvent $event, array $config): void
    {
        if (! $this->shouldAlert($event, $config)) {
            return;
        }

        $webhookUrl = $config['webhook_url'] ?? '';
        if (empty($webhookUrl)) {
            return;
        }

        try {
            $severity = $event->retriesExhausted ? '🔴 FATAL' : '🟡 RETRYABLE';

            $payload = [
                'text' => "{$severity} Stored Procedure Exception",
                'blocks' => [
                    [
                        'type' => 'header',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => "{$severity} SP Exception: {$event->storedProcedureName}",
                        ],
                    ],
                    [
                        'type' => 'section',
                        'fields' => [
                            ['type' => 'mrkdwn', 'text' => "*Procedure:*\n{$event->storedProcedureName}"],
                            ['type' => 'mrkdwn', 'text' => "*Connection:*\n{$event->connection}"],
                            ['type' => 'mrkdwn', 'text' => "*Attempt:*\n{$event->attempt}/{$event->maxAttempts}"],
                            ['type' => 'mrkdwn', 'text' => "*Time:*\n{$event->executionTime}s"],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "*Error:*\n```{$event->getExceptionMessage()}```",
                        ],
                    ],
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "*SQL:*\n```{$event->sql}```",
                        ],
                    ],
                ],
            ];

            $this->postJson($webhookUrl, $payload);

            Log::channel('stp')->info("💬 Slack alert sent for [{$event->storedProcedureName}]");
        } catch (\Exception $e) {
            Log::channel('stp')->error("Failed to send Slack alert: {$e->getMessage()}");
        }
    }

    // ──────────────────────────────────────────────────────────────────
    //  Webhook
    // ──────────────────────────────────────────────────────────────────

    private function sendWebhookAlert(StoredProcedureExceptionEvent $event, array $config): void
    {
        if (! $this->shouldAlert($event, $config)) {
            return;
        }

        $url = $config['url'] ?? '';
        if (empty($url)) {
            return;
        }

        try {
            $payload = $event->toArray();
            $payload['alert_type'] = 'exception';
            $payload['severity']   = $event->retriesExhausted ? 'fatal' : 'retryable';

            $headers = $config['headers'] ?? [];
            $headers['Content-Type'] = 'application/json';

            $this->postJson($url, $payload, $headers);

            Log::channel('stp')->info("🔗 Webhook alert sent for [{$event->storedProcedureName}]");
        } catch (\Exception $e) {
            Log::channel('stp')->error("Failed to send webhook alert: {$e->getMessage()}");
        }
    }

    // ──────────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────────

    private function shouldAlert(StoredProcedureExceptionEvent $event, array $config): bool
    {
        $filter = $config['filter'] ?? 'all'; // all, fatal, retryable

        return match ($filter) {
            'fatal'     => $event->retriesExhausted,
            'retryable' => $event->retryable && !$event->retriesExhausted,
            default     => true,
        };
    }

    private function getAlertConfig(): array
    {
        $defaults = [
            'enabled'  => true,
            'channels' => [
                'email' => [
                    'enabled'   => false,
                    'recipients'=> [],
                    'from'      => 'noreply@example.com',
                    'filter'    => 'all',
                ],
                'slack' => [
                    'enabled'     => false,
                    'webhook_url' => '',
                    'filter'      => 'all',
                ],
                'webhook' => [
                    'enabled' => false,
                    'url'     => '',
                    'headers' => [],
                    'filter'  => 'all',
                ],
            ],
        ];

        try {
            $config = config('dynamic-levels-helper.enhanced_db_service.alerts', []);
            return array_replace_recursive($defaults, $config);
        } catch (\Exception $e) {
            return $defaults;
        }
    }

    private function postJson(string $url, array $payload, array $extraHeaders = []): void
    {
        if (class_exists(\Illuminate\Support\Facades\Http::class)) {
            $response = \Illuminate\Support\Facades\Http::withHeaders($extraHeaders)
                ->timeout(10)
                ->post($url, $payload);

            if ($response->failed()) {
                throw new \RuntimeException("HTTP {$response->status()}: {$response->body()}");
            }
            return;
        }

        // Fallback to Guzzle
        $client = new \GuzzleHttp\Client(['timeout' => 10]);
        $response = $client->post($url, [
            'headers' => array_merge(['Accept' => 'application/json'], $extraHeaders),
            'json'    => $payload,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException("HTTP {$response->getStatusCode()}: {$response->getBody()}");
        }
    }
}
