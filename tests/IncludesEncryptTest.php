<?php

// Test-specific setup (bootstrap.php handles common setup)
// Load any specific includes needed for this test suite

class IncludesEncryptTest extends \PHPUnit\Framework\TestCase {

    /**
     * @dataProvider providerSafeBase64
     * @group base64
     */
    public function testSafeBase64Encode($string, $result) {
        $this->assertSame($result, safe_base64_encode($string));
    }

    /**
     * @depends testSafeBase64Encode
     * @dataProvider providerSafeBase64
     * @group base64
     */
    public function testSafeBase64Decode($result, $string) {
        $this->assertSame($result, safe_base64_decode($string));
    }

    /**
     * @depends testSafeBase64Encode
     * @dataProvider providerSafeBase64Random
     * @group base64
     */
    public function testSafeBase64Random($string) {
        $encode = safe_base64_encode($string);
        $decode = safe_base64_decode($encode);

        $this->assertSame($decode, $string);
    }

    public static function providerSafeBase64() {
        return [
            [ 'Zlwv(,/E%>ieDr25Mr,-?ZOiL',                  'Wmx3digsL0UlPmllRHIyNU1yLC0_Wk9pTA' ],
            [ 'w&8=K@.3}ULxnw"8+j`I\'yRQyL%RDijctN."',      'dyY4PUtALjN9VUx4bnciOCtqYEkneVJReUwlUkRpamN0Ti4i' ],
            [ 'T_\\u[WGG6c{o;i*J1/}\'5"\'nJJ.RY',           'VF9cdVtXR0c2Y3tvO2kqSjEvfSc1IiduSkouUlk' ],
            [ '(?fY".Q/g7>=cjtK@p[m$v,',                    'KD9mWSIuUS9nNz49Y2p0S0BwW20kdiw' ],
            [ 'kaoaDKPg;ek"rVi`4{mA,=KQZ%yOz<J;2~E',        'a2FvYURLUGc7ZWsiclZpYDR7bUEsPUtRWiV5T3o8SjsyfkU' ],
            [ 'Bow[#R+\'A*\':gIpRsL{3q-*2s',                'Qm93WyNSKydBKic6Z0lwUnNMezNxLSoycw' ],
            [ 'NG6JqTVjnZ>j}NP&#u%|e=i`n2@*QQ^T#o":xo/',    'Tkc2SnFUVmpuWj5qfU5QJiN1JXxlPWlgbjJAKlFRXlQjbyI6eG8v' ],
            [ 'e\',n,5S/UJoVZOTCHZx6Tn9Hsk7Cn2p',           'ZScsbiw1Uy9VSm9WWk9UQ0haeDZUbjlIc2s3Q24ycA' ],
            [ '7+Wz}\'GgFUl=;=A8M]~b1GfS3P`mJCV#',          'NytXen0nR2dGVWw9Oz1BOE1dfmIxR2ZTM1BgbUpDViM' ],
            [ '}.X8sPK0D)./=mQmVw,!A|VG',                   'fS5YOHNQSzBEKS4vPW1RbVZ3LCFBfFZH' ],
            [ 'cDlpvOGgnIlojBkDmU?:vHLVo9{oYaj7u0^jx',      'Y0RscHZPR2duSWxvakJrRG1VPzp2SExWbzl7b1lhajd1MF5qeA' ],
            [ '*loZQI@L[P?nq4f-px?J<~TDxK%BmLE,xdLs(C!]',   'KmxvWlFJQExbUD9ucTRmLXB4P0o8flREeEslQm1MRSx4ZExzKEMhXQ' ],
            [ '{Nx6#5tgz">e"gLh2\\wkqYOH/ZvX&U*97NBL',      'e054NiM1dGd6Ij5lImdMaDJcd2txWU9IL1p2WCZVKjk3TkJM' ],
            [ 'ZGYP`R\\!{4`pZ^s1~4gSrbr^>mk',               'WkdZUGBSXCF7NGBwWl5zMX40Z1NyYnJePm1r' ],
            [ '"J4l*A8%6D<#Q;0F~m3~m[|D938',                'Iko0bCpBOCU2RDwjUTswRn5tM35tW3xEOTM4' ],
            [ 'JzY:LY$(^0<Rv*TjAwAx[q/+mRGhA+I;,[2(y',      'SnpZOkxZJCheMDxSdipUakF3QXhbcS8rbVJHaEErSTssWzIoeQ' ],
            [ 'GQ&>l5tMX!CA<?5Wo-dMuw',                     'R1EmPmw1dE1YIUNBPD81V28tZE11dw' ],
            [ '!V=K\\?NkP^4ruh_*?<.UA&L6\\',                'IVY9S1w_TmtQXjRydWhfKj88LlVBJkw2XA' ],
            [ '8,G(?\'A>_7p`>qbr!;9``1ssc$WZpc\'>KxD*?Py3', 'OCxHKD8nQT5fN3BgPnFiciE7OWBgMXNzYyRXWnBjJz5LeEQqP1B5Mw' ],
            [ '6K\')zm&][xm0m/}G}<I)u)',                    'NksnKXptJl1beG0wbS99R308SSl1KQ' ],
        ];
    }

    public static function providerSafeBase64Random() {
        $charlist = ' 0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()_+-=[]{}\|/?,.<>;:"'."'";

        $result = [];
        for ($i = 0; $i < 20; $i++) {
            $string = random_string(mt_rand(20, 40), $charlist);
            $result[] = [ $string ];
        }
        return $result;
    }

    /**
     * @dataProvider providerEncrypt
     * @group encrypt
     */
    public function testEncrypt($string, $key, $result) {
        // Fot tests use static nonce
        $nonce = 'test';

        $this->assertSame($result, encrypt($string, $key, $nonce));
    }

    /**
     * @dataProvider providerEncrypt
     * @group decrypt
     */
    public function testDecrypt($result, $key, $string) {
        // Fot tests use static nonce
        $nonce = 'test';

        $this->assertSame($result, decrypt($string, $key, $nonce));
    }

    /**
     * @dataProvider providerEncryptIncorrect
     * @group decrypt
     */
    public function testDecryptIncorrect($result, $key, $string) {
        $this->assertFalse(decrypt($string, $key));
    }

    /**
     * @dataProvider providerEncryptRandom
     * @group random
     */
    public function testEncryptRandom($string, $key) {
        $encrypt = encrypt($string, $key);
        $decrypt = decrypt($encrypt, $key);

        $this->assertSame($decrypt, $string);
    }

    /**
     * @requires extension sodium
     * @dataProvider providerEncryptSodiumRandom
     * @group random
     */
    public function testEncryptSodiumRandom($string, $key) {
        $encrypt = encrypt_sodium($string, $key);
        $decrypt = decrypt_sodium($encrypt, $key);

        $this->assertSame($decrypt, $string);
    }

    public static function providerEncrypt() {

        return [
            [ '1)AEo@^Cq&n[i&K5Rbk)YmYto|iK6&:j,3w.9',  '1e78V2',   'po1Yr3rjOhi04wDOsyt2W-DEbObyBNLtssRuIxENOe3worH1MiuqNr5ZGmbAElwoU76DFho' ],
            [ ',>3(K!$eu0QXr6SBW[$',                    'jPpz9',    '8u73mdXrtw4ToQw9CBvMLfrugtd7gS_JtoU695dyInfpnm0' ],
            [ 'Xm+0JOu1pZ#mLu4k !h<J~nRC',              'q1I5LcMX', 'MgXHgCN6WtNNsjUW1i5p6sp9j79gT_BGDXhSYUFnRftEi8rZMn3bsgA' ],
            [ 'nU1I|X61$s WT \'{Ia)25|\'f.F',           'tTfKUX6',  'Ut5IyaVVRaCYW2aJPAXc5qiiLsdJ1jd7sUAfr8LDqORrpk9NQcSd4aj-' ],
            [ '3UrLXOOI/*/VW3\\@l8#DkLFpm(8U@$%bsKTmC', '803aRMNF', 'CRqvvvSTUaUCK3pQr2ScK4P1gewotrT76bIcCfeTX2Kz-USHTS1KjO2TAEykRtK3ITdiFIE' ],
            [ 'g&W$w[K(rt(jwWC{fYDw\'M;I/1gNo',         'MpRE',     'PVenPWwrPzd15RhKLLjIm9ma8iFAcNZnH34Ts2Br4pI5U5Hsw3fDjzpLwtMs' ],
            [ '*R$oh3nu-pe2#}ovVT!Wr/Rk?hj<',           'EGqi',     '2BlR5cS-6WGPz-xXHdh8mv79wncsR4Ca4HHDKiqh2pgtCeppn8-9RCFpK2A' ],
            [ 'CUo<&\\Te-s2O[zsQg&m%_',                 'Jbyjh',    'Apxo7cRz4oQYzviARjDmYkZpNiCmS29i0quCu--sJJj58v6fmw' ],
            [ 'y+U/0i&:Z$]+\\G`<rPc|\\{-7e^',           'LuMhrEPs', 'wwtra16iR0qbMMhGWNmRlTFbjVDmZpanKoQOJ_-d4DFMi4cJ4S9Tu7Hs' ],
            [ '=V>00QV807A7*seug3 fh^n;7\'w&CX0x!3P~',  'zbOj0',    'w5AKoKG_iGY41NzzeP4T3dZAqFnUUwo-Wky4WjbRB_fPNOtGuXxFpVuO3awmeV_ZiAwJDA' ],
            [ 'rl3yLUk<{bApLXJ@a@\\Y{M\\,z:4',          'QCGdqu',   '4F6MQxnzl71QpP3vJ3yutgpLqFRYkCnez1gMVFIkhIZ4qN7RIl3x1Y6k_Q' ],
            [ '*rF@W)b9GOu<[`RR1/,#FnQCE3PgI',          'QQOd',     'c6baoXI9uni5BAWaRYCYQF8v2WgHcGpeqeHkWP3Dp2QJ7KHKUxF2zNrarwpD' ],
            [ 'j$kY#JNym311~0hVo%HX@7Fsks(g',           'PxFdn97J', 'TwtFAgU85cU7ypq8EEx75BPk8RZaTS0bj7nEak3_4Rx6KTWFdB4RkM_Pwy8' ],
            [ ']&rQB>~nOf!A4h7}X~G$\\!uD$zGc*a',        '9wlzwu78', 'YwsuhTT5r9LGTCJoOzWLtrNJQqqCEHPTcqPzMTtY3LHQodU_QIm3z-TQccH62A' ],
            [ 'Y7RZTJV>U\\"mx3(C!5hKgBw-',              '7RyavjK',  'osFXadNstXIwlACmVYICQXV-djwzcY1dYx1So7UcGYEAU-SJ_GwuBQ' ],
            [ '!t#.;(bK}k@kVrf;#}Q-jp|;?hE|+.O',        'i1txZiB',  'e33OXAxT2XWXLODhYPgLNsed3EVvHfbTXShFS8IJNWkmKEyl3xKKTySwYk4MP-I' ],
            [ '>1`k@Lr4|3ot4WrgA!g||8}vSZhBT=c13|,/_{', 'OGHuN',    'rO5gPJnZM8zHs3M-MzJHU-86zxvJBNwe_1cXGaJoR_nmcNNXaLJss0kaTp1zmS2zec_XmJb5' ],
            [ '\\0TcEC6wGL# >JXv6 `eJ',                 '7zaLTGW',  'Fl2wMTFWLFP0pb02RlrGGut-HNm1DvAB7vC0xt0CXNJ1h4_hxg' ],
            [ ':r")v$eXry,13E!{7?K.U%-@SDD',            'hvHW',     'kQwfSPwLmHBgz_n8wbQPhipQL_pD7ZJkdbc9IuUTE7MGpucR2ZeeibP5iw' ],
            [ 'q%S/wOQhM%f3G06C1#uJgjIMWf\\`',          '4Zlb',     'A4uDbyfqHaXVSmRB2Hs6C4gIJKgKij7w_H0pgWJcTFskDlhR32h0jZdb2LI' ],
        ];
    }

    public static function providerEncryptIncorrect() {

        return [
            [ '1)AEo@^Cq&n[i&K5Rbk)YmYto|iK6&:j,3w.9',  '1e78V2',   'po1Yr3rjOhi04wDOsyt2W-DEbObyBNLtssRuIxENOe3wH1MiuqNr5ZGmbAElwoU76DFho' ],
            [ ',>3(K!$eu0QXr6SBW[$',                    'jPpz900',  '8u73mdXrtw4ToQw9CBvMLfrugtd7gS_JtoU695dyInfpnm0' ],
        ];
    }

    public static function providerEncryptRandom() {

        $charlist = ' 0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()_+-=[]{}\|/?,.<>;:"'."'";
        $result   = [];
        for ($i = 0; $i < 20; $i++) {
            $string = random_string(mt_rand(20, 40), $charlist);
            $key    = random_string(mt_rand(4, 8));
            $result[] = [ $string, $key ];
        }
        return $result;
    }

    public static function providerEncryptSodiumRandom() {

        $charlist = ' 0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()_+-=[]{}\|/?,.<>;:"'."'";
        $string   = random_string(mt_rand(20, 40), $charlist);

        $result = [];
        $result[] = [ $string, random_string(SODIUM_CRYPTO_SECRETBOX_KEYBYTES - 1), random_string(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES - 1) ];
        $result[] = [ $string, random_string(SODIUM_CRYPTO_SECRETBOX_KEYBYTES - 1), random_string(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) ];
        $result[] = [ $string, random_string(SODIUM_CRYPTO_SECRETBOX_KEYBYTES - 1), random_string(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 1) ];
        $result[] = [ $string, random_string(SODIUM_CRYPTO_SECRETBOX_KEYBYTES),     random_string(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES - 1) ];
        $result[] = [ $string, random_string(SODIUM_CRYPTO_SECRETBOX_KEYBYTES),     random_string(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) ];
        $result[] = [ $string, random_string(SODIUM_CRYPTO_SECRETBOX_KEYBYTES),     random_string(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 1) ];
        //$result[] = [ $string, generate_random_string(SODIUM_CRYPTO_SECRETBOX_KEYBYTES + 1), generate_random_string(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES - 1) ];
        //$result[] = [ $string, generate_random_string(SODIUM_CRYPTO_SECRETBOX_KEYBYTES + 1), generate_random_string(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) ];
        //$result[] = [ $string, generate_random_string(SODIUM_CRYPTO_SECRETBOX_KEYBYTES + 1), generate_random_string(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 1) ];

        return $result;
    }
}

// EOF
