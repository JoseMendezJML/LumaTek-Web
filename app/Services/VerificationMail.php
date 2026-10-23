<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class VerificationMail
{
    public function send(User $user): string
    {
        // Shared by initial delivery and resend: failures count against abuse limits.
        $key = 'verification-mail:user:'.$user->id;
        // Use the increment's result so concurrent resends cannot all pass a pre-check.
        if (RateLimiter::hit($key, 3600) > 3) {
            return 'limited';
        }
        try {
            $user->sendEmailVerificationNotification();

            return 'submitted';
        } catch (\Throwable) {
            // Transport exceptions can contain SMTP credentials, addresses or signed URLs.
            Log::warning('email_verification_delivery_failed', ['user_id' => $user->id]);

            return 'failed';
        }
    }
}
