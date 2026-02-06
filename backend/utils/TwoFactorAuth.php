<?php
/**
 * DigitalEdgeSolutions - Two Factor Authentication
 * TOTP-based 2FA using Google Authenticator
 */

class TwoFactorAuth {
    
    private static int $codeLength = 6;
    private static int $timeStep = 30; // 30 seconds
    
    /**
     * Generate random secret key
     */
    public static function generateSecret(int $length = 32): string {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 characters
        $secret = '';
        
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        
        return $secret;
    }
    
    /**
     * Generate TOTP code
     */
    public static function generateCode(string $secret, int $time = null): string {
        $time = $time ?? time();
        $timeStep = floor($time / self::$timeStep);
        
        $secret = self::base32Decode($secret);
        $timeStep = pack('N*', 0) . pack('N*', $timeStep);
        
        $hash = hash_hmac('sha1', $timeStep, $secret, true);
        $offset = ord($hash[19]) & 0x0F;
        
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::$codeLength);
        
        return str_pad((string)$code, self::$codeLength, '0', STR_PAD_LEFT);
    }
    
    /**
     * Verify TOTP code
     */
    public static function verify(string $secret, string $code, int $window = 1): bool {
        $time = time();
        
        // Check current and adjacent time windows
        for ($i = -$window; $i <= $window; $i++) {
            $expectedCode = self::generateCode($secret, $time + ($i * self::$timeStep));
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get QR Code URL for Google Authenticator
     */
    public static function getQRCodeUrl(string $email, string $secret, string $issuer = ''): string {
        $issuer = $issuer ?: APP_NAME;
        $label = urlencode($issuer . ':' . $email);
        $issuer = urlencode($issuer);
        
        $otpauth = "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}";
        
        // Return Google Chart API URL
        return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . urlencode($otpauth);
    }
    
    /**
     * Get manual entry key (formatted secret)
     */
    public static function getManualEntryKey(string $secret): string {
        return implode(' ', str_split($secret, 4));
    }
    
    /**
     * Base32 decode
     */
    private static function base32Decode(string $input): string {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(str_replace('=', '', $input));
        $output = '';
        $buffer = 0;
        $bufferSize = 0;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];
            $val = strpos($map, $char);
            
            if ($val === false) {
                continue;
            }
            
            $buffer = ($buffer << 5) | $val;
            $bufferSize += 5;
            
            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $output .= chr(($buffer >> $bufferSize) & 0xFF);
            }
        }
        
        return $output;
    }
    
    /**
     * Base32 encode
     */
    private static function base32Encode(string $input): string {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $buffer = 0;
        $bufferSize = 0;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $buffer = ($buffer << 8) | ord($input[$i]);
            $bufferSize += 8;
            
            while ($bufferSize >= 5) {
                $output .= $map[($buffer >> ($bufferSize - 5)) & 0x1F];
                $bufferSize -= 5;
            }
        }
        
        if ($bufferSize > 0) {
            $output .= $map[($buffer << (5 - $bufferSize)) & 0x1F];
        }
        
        return $output;
    }
    
    /**
     * Generate backup codes
     */
    public static function generateBackupCodes(int $count = 10): array {
        $codes = [];
        
        for ($i = 0; $i < $count; $i++) {
            $codes[] = implode('-', str_split(bin2hex(random_bytes(4)), 4));
        }
        
        return $codes;
    }
    
    /**
     * Hash backup code for storage
     */
    public static function hashBackupCode(string $code): string {
        return hash('sha256', strtoupper(str_replace('-', '', $code)));
    }
    
    /**
     * Verify backup code
     */
    public static function verifyBackupCode(string $code, array $hashedCodes): bool {
        $hash = self::hashBackupCode($code);
        return in_array($hash, $hashedCodes);
    }
}
