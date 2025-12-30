<?php

class Encryption
{
    private string $cipher;
    private string $key;

    // Konstruktor untuk inisialisasi algoritma dan kunci
    public function __construct(string $key, string $cipher = 'AES-256-CBC')
    {
        $this->cipher = $cipher;
        $this->key = $key;
    }

    // Fungsi untuk mengenkripsi data
    public function encrypt(string $data): string
    {
        // Generate IV
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher));
        if ($iv === false) {
            throw new Exception("Gagal membuat IV untuk enkripsi.");
        }

        // Enkripsi data
        $encrypted = openssl_encrypt($data, $this->cipher, $this->key, 0, $iv);
        if ($encrypted === false) {
            throw new Exception("Gagal mengenkripsi data.");
        }

        // Gabungkan IV dan data terenkripsi, lalu encode ke base64
        return base64_encode($iv . $encrypted);
    }

    // Fungsi untuk mendekripsi data
    public function decrypt(string $encryptedData): string
    {
        // Decode base64
        $decoded = base64_decode($encryptedData);
        if ($decoded === false) {
            throw new Exception("Gagal mendekode data terenkripsi.");
        }

        // Pisahkan IV dan data terenkripsi
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = substr($decoded, 0, $ivLength);
        $encrypted = substr($decoded, $ivLength);

        if ($iv === false || $encrypted === false) {
            throw new Exception("Data terenkripsi tidak valid.");
        }

        // Dekripsi data
        $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->key, 0, $iv);
        if ($decrypted === false) {
            throw new Exception("Gagal mendekripsi data.");
        }

        return $decrypted;
    }
}

?>
