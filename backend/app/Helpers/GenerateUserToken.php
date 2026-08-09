<?php

namespace App\Helpers;

use App\User;
use App\UserUniqueKey;
use Illuminate\Support\Facades\Hash;
use Str;

class GenerateUserToken
{
    public static function regenerate(User $user)
    {
        $token_created_time = $user->user_unique_key?->updated_at;
        if (!empty($token_created_time)) {
            $time_difference = $token_created_time->diffInMinutes(now());

            if ($time_difference >= 5) {
                $tokenize_user = UserUniqueKey::where('user_id',$user->id)->first();
                self::generate($tokenize_user->user);
            }
        } else {
            self::generate($user);
        }
    }

    public static function generate(User $tokenize_user)
    {

        $token = self::token();
        try {
            $tokenize_user->update([
                'unique_key' => $token
            ]);

            UserUniqueKey::updateOrCreate(
                [
                    'user_id' => $tokenize_user->id
                ],
                [
                    'user_id' => $tokenize_user->id,
                    'unique_key' => $token,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
        } catch (\Exception $exception) {
        }
    }

    public static function token()
    {
        return Hash::make(Str::random(32));
    }
}