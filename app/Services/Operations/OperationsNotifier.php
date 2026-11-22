<?php

declare(strict_types=1);

namespace App\Services\Operations;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class OperationsNotifier
{
    /**
     * Delivery is opt-in. Never send raw logs, user data, announce URLs or secrets.
     * A test/log/array mailer is not considered external notification delivery.
     */
    public function sendCritical(string $fingerprint): void
    {
        if (config('operations.notifications_enabled') !== true) {
            return;
        }

        $email = (string) config('operations.notification_email', '');
        $mailer = (string) config('mail.default', 'log');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || in_array($mailer, ['log', 'array'], true)) {
            return;
        }

        try {
            $row = DB::table('ops_alerts')->where('fingerprint', $fingerprint)->first();

            if ($row === null || $row->severity !== 'critical' || $row->status !== 'active') {
                return;
            }

            if ($row->notified_at !== null && \Illuminate\Support\Carbon::parse((string) $row->notified_at)->gt(now()->subHour())) {
                return;
            }

            $body = "NEXTGN has recorded a critical operational incident.\n\n"
                .'Component: '.(string) $row->component."\n"
                .'Incident: '.(string) $row->title."\n"
                .'First observed: '.(string) $row->first_seen_at."\n\n"
                .'Sign in to the Sysop Control Center to investigate. No credentials or request details are included.';

            Mail::raw($body, static function (Message $message) use ($email): void {
                $message->to($email)->subject('NEXTGN · critical platform incident');
            });

            DB::table('ops_alerts')->where('id', $row->id)->update(['notified_at' => now()]);
        } catch (Throwable) {
            // Notification outages must not crash scheduled monitoring or expose secrets.
        }
    }
}
