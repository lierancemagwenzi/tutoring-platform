<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case AccountVerification = 'account_verification';
    case PasswordReset = 'password_reset';
    case AdminInvite = 'admin_invite';
}
