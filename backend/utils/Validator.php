<?php
/**
 * DigitalEdgeSolutions - Input Validator
 * Comprehensive input validation and sanitization
 */

class Validator {
    private array $data;
    private array $errors = [];
    
    public function __construct(array $data) {
        $this->data = $data;
    }
    
    /**
     * Check if required fields exist
     */
    public function required(array $fields): self {
        foreach ($fields as $field) {
            if (!isset($this->data[$field]) || empty(trim($this->data[$field]))) {
                $this->errors[$field][] = "$field is required";
            }
        }
        return $this;
    }
    
    /**
     * Validate email format
     */
    public function email(string $field): self {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "Invalid email format";
        }
        return $this;
    }
    
    /**
     * Validate URL format
     */
    public function url(string $field): self {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
            $this->errors[$field][] = "Invalid URL format";
        }
        return $this;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength(string $field, int $length): self {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[$field][] = "Must be at least $length characters";
        }
        return $this;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength(string $field, int $length): self {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $length) {
            $this->errors[$field][] = "Must not exceed $length characters";
        }
        return $this;
    }
    
    /**
     * Validate numeric value
     */
    public function numeric(string $field): self {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field][] = "Must be a number";
        }
        return $this;
    }
    
    /**
     * Validate integer
     */
    public function integer(string $field): self {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
            $this->errors[$field][] = "Must be an integer";
        }
        return $this;
    }
    
    /**
     * Validate minimum value
     */
    public function min(string $field, $min): self {
        if (isset($this->data[$field]) && $this->data[$field] < $min) {
            $this->errors[$field][] = "Must be at least $min";
        }
        return $this;
    }
    
    /**
     * Validate maximum value
     */
    public function max(string $field, $max): self {
        if (isset($this->data[$field]) && $this->data[$field] > $max) {
            $this->errors[$field][] = "Must not exceed $max";
        }
        return $this;
    }
    
    /**
     * Validate value in array
     */
    public function in(string $field, array $values): self {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $values)) {
            $this->errors[$field][] = "Invalid value. Allowed: " . implode(', ', $values);
        }
        return $this;
    }
    
    /**
     * Validate date format
     */
    public function date(string $field, string $format = 'Y-m-d'): self {
        if (isset($this->data[$field])) {
            $date = DateTime::createFromFormat($format, $this->data[$field]);
            if (!$date || $date->format($format) !== $this->data[$field]) {
                $this->errors[$field][] = "Invalid date format. Expected: $format";
            }
        }
        return $this;
    }
    
    /**
     * Validate phone number
     */
    public function phone(string $field): self {
        if (isset($this->data[$field])) {
            $phone = preg_replace('/[^0-9+]/', '', $this->data[$field]);
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $this->errors[$field][] = "Invalid phone number";
            }
        }
        return $this;
    }
    
    /**
     * Validate password strength
     */
    public function strongPassword(string $field): self {
        if (isset($this->data[$field])) {
            $password = $this->data[$field];
            $errors = [];
            
            if (strlen($password) < 8) {
                $errors[] = "At least 8 characters";
            }
            if (!preg_match('/[A-Z]/', $password)) {
                $errors[] = "At least one uppercase letter";
            }
            if (!preg_match('/[a-z]/', $password)) {
                $errors[] = "At least one lowercase letter";
            }
            if (!preg_match('/[0-9]/', $password)) {
                $errors[] = "At least one number";
            }
            if (!preg_match('/[^A-Za-z0-9]/', $password)) {
                $errors[] = "At least one special character";
            }
            
            if (!empty($errors)) {
                $this->errors[$field][] = "Password must contain: " . implode(', ', $errors);
            }
        }
        return $this;
    }
    
    /**
     * Validate match with another field
     */
    public function matches(string $field, string $matchField): self {
        if (isset($this->data[$field]) && isset($this->data[$matchField])) {
            if ($this->data[$field] !== $this->data[$matchField]) {
                $this->errors[$field][] = "Must match $matchField";
            }
        }
        return $this;
    }
    
    /**
     * Validate JSON format
     */
    public function json(string $field): self {
        if (isset($this->data[$field])) {
            $value = is_string($this->data[$field]) ? $this->data[$field] : json_encode($this->data[$field]);
            json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->errors[$field][] = "Invalid JSON format";
            }
        }
        return $this;
    }
    
    /**
     * Validate array
     */
    public function array(string $field): self {
        if (isset($this->data[$field]) && !is_array($this->data[$field])) {
            $this->errors[$field][] = "Must be an array";
        }
        return $this;
    }
    
    /**
     * Validate file upload
     */
    public function file(string $field, array $allowedTypes = [], int $maxSize = 0): self {
        if (isset($_FILES[$field])) {
            $file = $_FILES[$field];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->errors[$field][] = "File upload failed";
                return $this;
            }
            
            if (!empty($allowedTypes)) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedTypes)) {
                    $this->errors[$field][] = "Invalid file type. Allowed: " . implode(', ', $allowedTypes);
                }
            }
            
            if ($maxSize > 0 && $file['size'] > $maxSize) {
                $this->errors[$field][] = "File too large. Max size: " . $this->formatBytes($maxSize);
            }
        }
        return $this;
    }
    
    /**
     * Custom validation rule
     */
    public function custom(string $field, callable $callback, string $message): self {
        if (isset($this->data[$field])) {
            if (!$callback($this->data[$field])) {
                $this->errors[$field][] = $message;
            }
        }
        return $this;
    }
    
    /**
     * Check if validation passed
     */
    public function isValid(): bool {
        return empty($this->errors);
    }
    
    /**
     * Get validation errors
     */
    public function getErrors(): array {
        return $this->errors;
    }
    
    /**
     * Get first error message
     */
    public function getFirstError(): ?string {
        if (empty($this->errors)) return null;
        
        $firstField = array_key_first($this->errors);
        return $this->errors[$firstField][0] ?? null;
    }
    
    /**
     * Sanitize string input
     */
    public static function sanitizeString(string $input): string {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Sanitize email
     */
    public static function sanitizeEmail(string $email): string {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
    
    /**
     * Sanitize URL
     */
    public static function sanitizeUrl(string $url): string {
        return filter_var(trim($url), FILTER_SANITIZE_URL);
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
