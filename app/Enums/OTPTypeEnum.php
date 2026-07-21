<?php

namespace App\Enums;

enum OTPTypeEnum
{
    case VERIFY_EMAIL_OTP;
    case RESET_PASSWORD_OTP;
    case TWO_FACTOR_OTP;
}
