<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Reset Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are the default lines which match reasons
    | that are given by the password broker for a password update attempt
    | outcome such as failure due to an invalid password / reset token.
    |
    | Not currently used by this app's own password-reset flow (it's OTP-based,
    | not Laravel's default token-link broker) — kept for parity with
    | lang/en/passwords.php in case any framework-internal code path ever calls it.
    |
    */

    'reset' => 'Kata sandi Anda telah direset.',
    'sent' => 'Kami telah mengirim tautan reset kata sandi ke email Anda.',
    'throttled' => 'Mohon tunggu sebelum mencoba lagi.',
    'token' => 'Token reset kata sandi ini tidak valid.',
    'user' => 'Kami tidak menemukan pengguna dengan alamat email tersebut.',

];
