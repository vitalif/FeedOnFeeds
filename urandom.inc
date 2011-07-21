<?php

function urandom($nbytes = 16)
{
    $pr_bits = NULL;
    // Unix/Linux platform?
    $fp = @fopen('/dev/urandom', 'rb');
    if ($fp !== FALSE)
    {
        $pr_bits = @fread($fp, $nbytes);
        @fclose($fp);
    }
    // MS-Windows platform?
    elseif (@class_exists('COM'))
    {
        // http://msdn.microsoft.com/en-us/library/aa388176(VS.85).aspx
        try
        {
            $CAPI_Util = new COM('CAPICOM.Utilities.1');
            $pr_bits = $CAPI_Util->GetRandom($nbytes,0);
            // if we ask for binary data PHP munges it, so we
            // request base64 return value.
            $pr_bits = base64_decode($pr_bits);
        }
        catch (Exception $ex)
        {
        }
    }
    return $pr_bits;
}
