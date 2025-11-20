<?php

namespace App\Http\Helpers;

use App\Models\Settings;

class SocialLogin
{
    public static function checkSocial($social) {
        $socials = Settings::getAll()->where('type', 'social')->pluck('value','name')->toArray();
        if(array_key_exists($social, $socials) &&  ! (int) $socials[$social] == 0) {
            return true;
        }
        return false;
    }
}
