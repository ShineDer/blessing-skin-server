<?php

namespace App\Rules;

use Composer\CaBundle\CaBundle;
use Gregwar\Captcha\CaptchaBuilder;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Http;

class Captcha implements Rule
{
    public function passes($attribute, $value)
    {
        $turnstileSecret = option('turnstile_secretkey');
        if ($turnstileSecret) {
            $response = Http::asForm()
                ->withOptions(['verify' => CaBundle::getSystemCaRootBundlePath()])
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $turnstileSecret,
                    'response' => $value,
                    'remoteip' => request()?->ip(),
                ])
                ->json();

            return (bool) ($response['success'] ?? false);
        }

        $secretkey = option('recaptcha_secretkey');
        if ($secretkey) {
            return Http::asForm()
                ->withOptions(['verify' => CaBundle::getSystemCaRootBundlePath()])
                ->post('https://www.recaptcha.net/recaptcha/api/siteverify', [
                    'secret' => $secretkey,
                    'response' => $value,
                ])
                ->json()['success'];
        }

        $builder = new CaptchaBuilder(session()->pull('captcha'));

        return $builder->testPhrase($value);
    }

    public function message()
    {
        if (option('turnstile_secretkey')) {
            return trans('validation.turnstile');
        }

        return option('recaptcha_secretkey')
            ? trans('validation.recaptcha')
            : trans('validation.captcha');
    }
}
