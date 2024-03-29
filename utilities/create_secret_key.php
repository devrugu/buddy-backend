$keyLength = 32; // 256 bit key için
$strong = true; // Güçlü bir key oluşturulup oluşturulmadığını kontrol et
$secretKey = bin2hex(openssl_random_pseudo_bytes($keyLength, $strong));

echo $secretKey;
