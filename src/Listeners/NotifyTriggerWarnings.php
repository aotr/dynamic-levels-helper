<?php

namespace Aotr\DynamicLevelHelper\Listeners;

use Aotr\DynamicLevelHelper\Events\StoredProcedureTriggerWarningEvent;
use Illuminate\Support\Facades\Log;

/**
 * Sends notifications when DB triggers raise warnings during SP execution.
 *
 * Trigger warnings are non-fatal — the SP still succeeds — but they signal
 * things like:
 * - Data truncation
 * - Deprecated column usage
 * - Constraint warnings
 * - Custom SIGNAL / RAISERROR (severity < 11) from triggers
 *
 * This listener can be configured to:
 * - Log all trigger warnings
 * - Send email digests for important warnings
 * - Post to Slack for real-time awareness
 * - POST to a custom webhook
 */
class NotifyTriggerWarnings
{
    /**
     * Handle the event.
     */
    public function handle(StoredProcedureTriggerWarningEvent $event): void
    {
        $alertConfig = $this->getAlertConfig();

        if (!($alertConfig['enabled'] ?? true)) {
            return;
        }

        // ── 1. Always log trigger warnings ──
        $this->logWarning($event);

        // ── 2. Email notification (if configured) ──
        if (!empty($alertConfig['channels']['email']['enabled'])) {
            $this->sendEmailNotification($event, $alertConfig['channels']['email']);
        }

        // ── 3. Slack notification (if configured) ──
        if (!empty($alertConfig['channels']['slack']['enabled'])) {
            $this->sendSlackNotification($event, $alertConfig['channels']['slack']);
        }

        // ── 4. Custom webhook (if configured) ──
        if (!empty($alertConfig['channels']['webhook']['enabled'])) {
            $this->sendWebhookNotification($event, $alertConfig['channels']['webhook']);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    //  Log
    // ──────────────────────────────────────────────────────────────────

    private function logWarning(StoredProcedureTriggerWarningEvent $event): void
    {
        $channel = config('dynamic-levels-helper.enhanced_db_service.logging.channel', 'stp');

        $warningCount = count($event->warnings);
        $summary      = $this->summarizeWarnings($event->warnings);

        Log::channel($channel)->warning(
            "⚠️  Trigger warning(s) in [{$event->storedProcedureName}] — {$warningCount} warning(s): {$summary}",
            [
                'stored_procedure' => $event->storedProcedureName,
                'parameters'       => $event->parameters,
                'connection'       => $event->connection,
                'warnings'         => $event->warnings,
                'sql'              => $event->sql,
                'execution_time'   => round($event->executionTime, 4),
                'result_meta'      => $event->resultMeta,
                'hostname'         => gethostname(),
                'timestamp'        => now()->toISOString(),
            ]
        );
    }

    // ──────────────────────────────────────────────────────────────────
    //  Email
    // ──────────────────────────────────────────────────────────────────

    private function sendEmailNotification(StoredProcedureTriggerWarningEvent $event, array $config): void
    {
        $recipients = $config['recipients'] ?? [];
        if (empty($recipients)) {
            return;
        }

        try {
            $subject = "⚠️ Trigger Warning(s): {$event->storedProcedureName}";
            $body    = $this->buildEmailBody($event);

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

            Log::channel('stp')->info("📧 Trigger warning email sent for [{$event->storedProcedureName}]", [
                'recipients' => $recipients,
            ]);
        } catch (\Exception $e) {
            Log::channel('stp')->error("Failed to send trigger warning email: {$e->getMessage()}", [
                'stored_procedure' => $event->storedProcedureName,
                'error'            => $e->getMessage(),
            ]);
        }
    }

    private function buildEmailBody(StoredProcedureTriggerWarningEvent $event): string
    {
        $warningCount = count($event->warnings);
        $warningsHtml = '';

        foreach ($event->warnings as $i => $warning) {
            $msg  = htmlspecialchars($warning['message'] ?? $warning['Message'] ?? ($warning[0] ?? 'Unknown'));
            $code = htmlspecialchars((string)($warning['code'] ?? $warning['Code'] ?? ($warning[1] ?? '')));
            $sev  = htmlspecialchars((string)($warning['severity'] ?? $warning['Severity'] ?? ($warning[2] ?? '')));
            $warningsHtml .= <<<HTML
            <tr>
                <td style="padding:8px; border:1px solid #dee2e6; font-weight:bold;">#{$i}</td>
                <td style="padding:8px; border:1px solid #dee2e6;">{$code}</td>
                <td style="padding:8px; border:1px solid #dee2e6;">{$sev}</td>
                <td style="padding:8px; border:1px solid #dee2e6;">{$msg}</td>
            </tr>
HTML;
        }

        $html = <<<HTML
        <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 700px; margin: 0 auto;">
            <div style="background: #ffc107; color: #000; padding: 16px 20px; border-radius: 8px 8px 0 0;">
                <h2 style="margin:0;">⚠️ Trigger Warnings Detected</h2>
            </div>
            <div style="border: 1px solid #dee2e6; border-top: none; padding: 20px; border-radius: 0 0 8px 8px;">
                <p style="margin-top:0;">The stored procedure <strong>{$event->storedProcedureName}</strong> completed successfully but raised <strong>{$warningCount} trigger warning(s)</strong>.</p>

                <table style="width:100%; border-collapse: collapse; margin-top:16px;">
                    <tr><td style="font-weight:bold; padding:6px 12px; background:#f8f9fa; width:180px;">Stored Procedure</td><td style="padding:6px 12px;">{$event->storedProcedureName}</td></tr>
                    <tr><td style="font-weight:bold; padding:6px 12px; background:#f8f9fa;">Connection</td><td style="padding:6px 12px;">{$event->connection}</td></tr>
                    <tr><td style="font-weight:bold; padding:6px 12px; background:#f8f9fa;">Execution Time</td><td style="padding:6px 12px;">{$event->executionTime}s</td></tr>
                    <tr><td style="font-weight:bold; padding:6px 12px; background:#f8f9fa;">Warning Count</td><td style="padding:6px 12px;">{$warningCount}</td></tr>
                </table>

                <h3 style="margin-top:16px;">Warnings</h3>
                <table style="width:100%; border-collapse:collapse; border:1px solid #dee2e6;">
                    <thead>
                        <tr style="background:#f8f9fa;">
                            <th style="padding:8px; border:1px solid #dee2e6; text-align:left;">#</th>
                            <th style="padding:8px; border:1px solid #dee2e6; text-align:left;">Code</th>
                            <th style="padding:8px; border:1px solid #dee2e6; text-align:left;">Severity</th>
                            <th style="padding:8px; border:1px solid #dee2e6; text-align:left;">Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$warningsHtml}
                    </tbody>
                </table>

                <h3 style="margin-top:16px;">SQL</h3>
                <pre style="background:#f8f9fa; padding:12px; border-radius:4px; overflow-x:auto; font-size:13px;">{$event->sql}</pre>

                <h3 style="margin-top:16px;">Parameters</h3>
                <pre style="background:#f8f9fa; padding:12px; border-radius:4px; overflow-x:auto; font-size:13px;">" . json_encode($event->parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>

                <p style="margin-top:20px; color:#6c757d; font-size:12px;">Timestamp: " . now()->toDateTimeString() . " | Server: " . gethostname() . "</p>
            </div>
        </div>
HTML;

        return $html;
    }

    // ──────────────────────────────────────────────────────────────────
    //  Slack
    // ──────────────────────────────────────────────────────────────────

    private function sendSlackNotification(StoredProcedureTriggerWarningEvent $event, array $config): void
    {
        $webhookUrl = $config['webhook_url'] ?? '';
        if (empty($webhookUrl)) {
            return;
        }

        try {
            $warningCount = count($event->warnings);
            $summary      = $this->summarizeWarnings($event->warnings);

            $payload = [
                'text' => "⚠️ Trigger Warnings: {$event->storedProcedureName}",
                'blocks' => [
                    [
                        'type' => 'header',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => "⚠️ Trigger Warnings: {$event->storedProcedureName}",
                        ],
                    ],
                    [
                        'type' => 'section',
                        'fields' => [
                            ['type' => 'mrkdwn', 'text' => "*Procedure:*\n{$event->storedProcedureName}"],
                            ['type' => 'mrkdwn', 'text' => "*Connection:*\n{$event->connection}"],
                            ['type' => 'mrkdwn', 'text' => "*Warnings:*\n{$warningCount}"],
                            ['type' => 'mrkdwn', 'text' => "*Time:*\n{$event->executionTime}s"],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "*Summary:*\n{$summary}",
                        ],
                    ],
                ],
            ];

            $this->postJson($webhookUrl, $payload);

            Log::channel('stp')->info("💬 Slack notification sent for trigger warnings [{$event->storedProcedureName}]");
        } catch (\Exception $e) {
            Log::channel('stp')->error("Failed to send Slack trigger warning notification: {$e->getMessage()}");
        }
    }

    // ──────────────────────────────────────────────────────────────────
    //  Webhook
    // ──────────────────────────────────────────────────────────────────

    private function sendWebhookNotification(StoredProcedureTriggerWarningEvent $event, array $config): void
    {
        $url = $config['url'] ?? '';
        if (empty($url)) {
            return;
        }

        try {
            $payload = $event->toArray();
            $payload['alert_type'] = 'trigger_warning';
            $payload['severity']   = 'warning';

            $headers = $config['headers'] ?? [];
            $headers['Content-Type'] = 'application/json';

            $this->postJson($url, $payload, $headers);

            Log::channel('stp')->info("🔗 Webhook sent for trigger warnings [{$event->storedProcedureName}]");
        } catch (\Exception $e) {
            Log::channel('stp')->error("Failed to send webhook for trigger warnings: {$e->getMessage()}");
        }
    }

    // ──────────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────────

    /**
     * Summarize warnings into a single line for quick scanning.
     */
    private function summarizeWarnings(array $warnings): string
    {
        $lines = [];

        foreach ($warnings as $w) {
            $msg = $w['message'] ?? $w['Message'] ?? ($w[0] ?? null);
            if ($msg) {
                $lines[] = is_string($msg) ? mb_substr($msg, 0, 120) : (string)$msg;
            }
        }

        return implode('; ', $lines) ?: 'No details available';
    }

    private function getAlertConfig(): array
    {
        $defaults = [
            'enabled'  => true,
            'channels' => [
                'email' => [
                    'enabled'    => false,
                    'recipients' => [],
                    'from'       => 'noreply@example.com',
                ],
                'slack' => [
                    'enabled'     => false,
                    'webhook_url' => '',
                ],
                'webhook' => [
                    'enabled' => false,
                    'url'     => '',
                    'headers' => [],
                ],
            ],
        ];

        try {
            $config = config('dynamic-levels-helper.enhanced_db_service.trigger_warnings', []);
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
