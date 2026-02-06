<?php
/**
 * DigitalEdgeSolutions - Response Handler
 * Standardized API response formatting
 */

class Response {
    
    /**
     * Send success response
     */
    public static function success(array $data = [], string $message = 'Success', int $code = 200): void {
        self::send([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c'),
            'request_id' => self::generateRequestId()
        ], $code);
    }
    
    /**
     * Send error response
     */
    public static function error(string $message, int $code = 400, array $errors = []): void {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('c'),
            'request_id' => self::generateRequestId()
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        self::send($response, $code);
    }
    
    /**
     * Send paginated response
     */
    public static function paginated(array $items, int $page, int $perPage, int $total, array $meta = []): void {
        $totalPages = (int)ceil($total / $perPage);
        
        self::send([
            'success' => true,
            'data' => $items,
            'pagination' => array_merge([
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ], $meta),
            'timestamp' => date('c'),
            'request_id' => self::generateRequestId()
        ]);
    }
    
    /**
     * Send raw JSON response
     */
    public static function send(array $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        if ($json === false) {
            $json = json_encode([
                'success' => false,
                'message' => 'JSON encoding error',
                'timestamp' => date('c')
            ]);
        }
        
        echo $json;
        exit;
    }
    
    /**
     * Send file response
     */
    public static function file(string $filePath, string $filename = null, string $mimeType = null): void {
        if (!file_exists($filePath)) {
            self::error('File not found', 404);
        }
        
        $filename = $filename ?? basename($filePath);
        $mimeType = $mimeType ?? mime_content_type($filePath) ?? 'application/octet-stream';
        $fileSize = filesize($filePath);
        
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: no-cache, must-revalidate');
        
        readfile($filePath);
        exit;
    }
    
    /**
     * Stream video response
     */
    public static function streamVideo(string $filePath): void {
        if (!file_exists($filePath)) {
            self::error('Video not found', 404);
        }
        
        $mimeType = mime_content_type($filePath) ?? 'video/mp4';
        $fileSize = filesize($filePath);
        
        header('Content-Type: ' . $mimeType);
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: public, max-age=31536000');
        
        // Handle range requests for video seeking
        if (isset($_SERVER['HTTP_RANGE'])) {
            $range = $_SERVER['HTTP_RANGE'];
            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                $start = intval($matches[1]);
                $end = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;
                
                header('HTTP/1.1 206 Partial Content');
                header("Content-Range: bytes $start-$end/$fileSize");
                header('Content-Length: ' . ($end - $start + 1));
                
                $fp = fopen($filePath, 'rb');
                fseek($fp, $start);
                
                $bufferSize = 8192;
                $bytesToSend = $end - $start + 1;
                
                while ($bytesToSend > 0 && !feof($fp)) {
                    $bytes = min($bufferSize, $bytesToSend);
                    echo fread($fp, $bytes);
                    $bytesToSend -= $bytes;
                    flush();
                }
                
                fclose($fp);
                exit;
            }
        }
        
        readfile($filePath);
        exit;
    }
    
    /**
     * Send redirect response
     */
    public static function redirect(string $url, int $code = 302): void {
        header("Location: $url", true, $code);
        exit;
    }
    
    /**
     * Generate unique request ID
     */
    private static function generateRequestId(): string {
        return uniqid('req_', true);
    }
    
    /**
     * Validation error response
     */
    public static function validationError(array $errors): void {
        self::error('Validation failed', 422, $errors);
    }
    
    /**
     * Not found response
     */
    public static function notFound(string $resource = 'Resource'): void {
        self::error($resource . ' not found', 404);
    }
    
    /**
     * Unauthorized response
     */
    public static function unauthorized(string $message = 'Unauthorized'): void {
        self::error($message, 401);
    }
    
    /**
     * Forbidden response
     */
    public static function forbidden(string $message = 'Forbidden'): void {
        self::error($message, 403);
    }
    
    /**
     * Server error response
     */
    public static function serverError(string $message = 'Internal server error'): void {
        self::error($message, 500);
    }
}
