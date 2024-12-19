<?php

namespace App\Helpers;

class AssetHelper
{
    public static function asset($path)
    {
        if (config('app.env') === 'production') {
            return secure_asset($path);
        }
        return asset($path);
    }

    public static function url($path)
    {
        if (config('app.env') === 'production') {
            return secure_url($path);
        }
        return url($path);
    }
}
