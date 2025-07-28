<?php

declare(strict_types=1);

namespace App\Services;

class DataSecurityService
{
    protected string $key;
    protected string $iv;

    public function __construct()
    {
        $this->key = env('DATA_SECURITY_KEY', '');
        $this->iv = env('DATA_SECURITY_IV', '');
    }

    public function encrypt($data)
    {
        $key = $this->key;
        $iv = $this->iv;

        return $this->encryptData($data, $key, $iv);
    }

    public function encryptData($data, $key, $iv)
    {
        // Convert key and IV to binary format
        $key = substr(hash('sha256', $key, true), 0, 32);
        $iv = substr(hash('md5', $iv, true), 0, 16);

        $encrypted = openssl_encrypt(
            json_encode($data),
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,  // Use raw data to avoid encoding mismatch
            $iv
        );

        return base64_encode($encrypted); // Base64 encode before sending
    }

    public function decrypt($data)
    {
        $key = env('DATA_SECURITY_KEY');
        $iv = env('DATA_SECURITY_IV');
        $data = 'dAK5posYPDrXDRYBZwxL7/oqIXS7uAhwhnSKfK0RkVA=';
        $decryptedData = $this->decryptData($data, $key, $iv);

        return response()->json(['decrypted' => $decryptedData]);
    }

    public function decryptData($encryptedData, $key, $iv)
    {
        // Convert key and IV to binary format (same as Angular)
        $key = substr(hash('sha256', $key, true), 0, 32);
        $iv = substr(hash('md5', $iv, true), 0, 16);

        // Decode Base64 data
        $encryptedData = base64_decode($encryptedData);

        // Decrypt
        $decrypted = openssl_decrypt(
            $encryptedData,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return json_decode($decrypted, true);
    }
}
