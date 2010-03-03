<?php

# Функция для установки прокси из переменных окружения для cURL'а < 7.3 (да и > тоже),
# который, тупая тварь, не умеет это делать самостоятельно. Используется у нас в FeedOnFeeds и MediaWiki.
# vfilippov@custis.ru, 2010-03-03

function curl_set_env_proxy($curl, $url)
{
    if ($proxy = getenv("http_proxy"))
    {
        $useproxy = true;
        if ($url && ($noproxy = preg_split("#\s*,\s*#is", getenv("no_proxy"))))
        {
            foreach ($noproxy as $n)
            {
                if (preg_match('#(\d+)\.(\d+)\.(\d+)\.(\d+)/(\d+)#s', $n, $m) &&
                    preg_match('#^[a-z0-9_]+://(?:[^/]*:[^/]*@)?([^/@]+)(?:/|$|\?)#is', $url, $ip))
                {
                    $mask = array(
                        max(0x100 - (1 << max( 8-$m[5], 0)), 0),
                        max(0x100 - (1 << max(16-$m[5], 0)), 0),
                        max(0x100 - (1 << max(24-$m[5], 0)), 0),
                        max(0x100 - (1 << max(32-$m[5], 0)), 0),
                    );
                    $ip = @gethostbyname($ip[1]);
                    if (preg_match('#(\d+)\.(\d+)\.(\d+)\.(\d+)#s', $ip, $ipm) &&
                        (intval($ipm[1]) & $mask[0]) == intval($m[1]) &&
                        (intval($ipm[2]) & $mask[1]) == intval($m[2]) &&
                        (intval($ipm[3]) & $mask[2]) == intval($m[3]) &&
                        (intval($ipm[4]) & $mask[3]) == intval($m[4]))
                    {
                        $useproxy = false;
                        break;
                    }
                }
                else
                {
                    $n = preg_replace('/#.*$/is', '', $n);
                    $n = preg_quote($n);
                    $n = str_replace('\\*', '.*', $n);
                    if (preg_match('#'.$n.'#is', $url))
                    {
                        $useproxy = false;
                        break;
                    }
                }
            }
        }
        if ($useproxy)
        {
            $proxy = preg_replace('#^http://#is', '', $proxy);
            $proxy = preg_replace('#/*$#is', '', $proxy);
        }
        else
            $proxy = '';
        curl_setopt($curl, CURLOPT_PROXY, $proxy);
    }
    return $proxy;
}
