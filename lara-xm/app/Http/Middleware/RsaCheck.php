<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Input;

class RsaCheck
{
    private $pubKey = "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCZy3jIWYJ9u8HdCMRwERduBMnX
/2epZnnqHz8d5L3MRyMpICWmVODmvsSn47396vlacxXiYElwNpv65yyxRAva4wKR
6hAaf7OmZMjxHLW6+2sTqJMcZPVlaUalA9B/Q058CPu5RgHpytJyH+Qk1n55LLZe
jCBEEdi1y64lNzrbQwIDAQAB";
    private $priKey = "MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAMVs6p/8vs9LB6Ac
WcLWWd8H6E5n63pRdjrVsG4wOeZDpQgC8iW3o/8/TBQgYoc48pLq60KA8Zt9sLrj
SB9WhxIYeqwyCh0oNY4HOg/VWGMKlboP0DliQ+y5WBN7k0gKF1TMaUWsurGR4hdq
cLeYjv4W7DxlEewZitv6Zy/kYJA1AgMBAAECgYAbMv88HXEYVAjv6RgAvNFS5d7+
dli92F1Gi8wr0h8X9zfUW7uKsLs6XjkYCMIqSRE6Zn0VA3jF6FIh3VBBaQVgnXLJ
2O0c02f/lw0XApCdDY7oqFMqQwXjBXPaUzrfhzTTi8pOE66aHCg+RQ6QZHehPZLq
x+5AUy985ThbPEDjvQJBAP2uWMFW6RqKkX8XSOOmmvLjkEXhYoQPHUAMmZEeSrxh
em8tM+HV6cUE0ttA4xGgOg0RzE69cGThiFCHTN7eH5MCQQDHOuxFfbVFpFLWUHsG
O5aj0Wb313WyVwDFK9cqsw+skdoBqI1bDMOjoQFh4iFHKnxT6DUWud3aCT41ZJg8
tZ4XAkAVuhH19Sif0lBlzyu5+7H3rY/UvFoAr3601p9sc2i5O6wNy5RO+lA8RI5+
os8P2mY+alDSSZ1PtpVDOGNYDzQrAkBytouqa3o/ciE8QzTC3vaatoyqMcYT/KJ1
5QtMC7P/si8re0iA33WaNq9cE98DYgQaL/65aiXCUEYgah55/jzbAkEAjI0KIVc7
M7A/iOaP4nFv8Rt8Vtd8MixdUfaKIgZzTdPm8XZRbIfbKRrvT5d9yd88yD3syp/R
ji8XrcjIbc7ffQ==";

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $sign = $request->header('sign');
        $data = $request->header('jsonstr');
        $sslKey = "-----BEGIN PUBLIC KEY-----" . PHP_EOL . $this->pubKey . PHP_EOL . "-----END PUBLIC KEY-----";
        if (openssl_verify($data, base64_decode($sign), $sslKey, 'SHA256') != 1) {
            return response(error('133001', '密钥验证不通过'));
        }
//        echo 1355555;die;
        return $next($request);
    }

    public function checkSign(string $data = '', string $sign = '', string $type = 'SHA256')
    {

    }
}
