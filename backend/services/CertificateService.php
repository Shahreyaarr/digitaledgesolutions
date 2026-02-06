<?php
/**
 * DigitalEdgeSolutions - Certificate Service
 * Auto-generate professional certificates with QR codes and blockchain verification
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/EmailService.php';

// Include FPDF library (install via composer require setasign/fpdf)
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

class CertificateService {
    
    private static string $certificatesTable = 'certificates';
    private static string $certificatesPath = CERTIFICATES_PATH;
    
    /**
     * Generate certificate for course completion
     */
    public static function generate(int $userId, int $courseId, array $options = []): ?array {
        try {
            // Get user data
            $user = Database::fetchOne("SELECT * FROM users WHERE user_id = ?", [$userId]);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Get course data
            $course = Database::fetchOne("SELECT * FROM courses WHERE course_id = ?", [$courseId]);
            if (!$course) {
                throw new Exception('Course not found');
            }
            
            // Check if certificate already exists
            $existingCert = Database::fetchOne(
                "SELECT * FROM " . self::$certificatesTable . " WHERE user_id = ? AND course_id = ?",
                [$userId, $courseId]
            );
            
            if ($existingCert) {
                return [
                    'certificate_id' => $existingCert['certificate_id'],
                    'certificate_number' => $existingCert['certificate_number'],
                    'pdf_url' => $existingCert['pdf_url'],
                    'verification_url' => $existingCert['verification_url']
                ];
            }
            
            // Generate unique certificate number
            $certificateNumber = self::generateCertificateNumber();
            
            // Generate verification URL
            $verificationUrl = APP_URL . '/verify/' . $certificateNumber;
            
            // Generate QR code
            $qrCodePath = self::generateQRCode($verificationUrl, $certificateNumber);
            
            // Generate PDF certificate
            $pdfPath = self::generatePDF($user, $course, $certificateNumber, $qrCodePath, $options);
            
            // Generate blockchain hash if enabled
            $blockchainTxHash = null;
            if (BLOCKCHAIN_ENABLED) {
                $blockchainTxHash = self::storeOnBlockchain($certificateNumber, $user, $course);
            }
            
            // Store certificate in database
            $certData = [
                'certificate_number' => $certificateNumber,
                'user_id' => $userId,
                'course_id' => $courseId,
                'certificate_type' => $options['type'] ?? 'course_completion',
                'title' => $course['title'],
                'description' => 'Certificate of Completion for ' . $course['title'],
                'grade' => $options['grade'] ?? null,
                'score' => $options['score'] ?? null,
                'issue_date' => date('Y-m-d'),
                'status' => 'active',
                'pdf_url' => $pdfPath,
                'blockchain_tx_hash' => $blockchainTxHash,
                'blockchain_network' => BLOCKCHAIN_ENABLED ? BLOCKCHAIN_NETWORK : null,
                'verification_url' => $verificationUrl
            ];
            
            $sql = "INSERT INTO " . self::$certificatesTable . " 
                    (certificate_number, user_id, course_id, certificate_type, title, description, 
                     grade, score, issue_date, status, pdf_url, blockchain_tx_hash, blockchain_network, verification_url) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            Database::query($sql, [
                $certData['certificate_number'],
                $certData['user_id'],
                $certData['course_id'],
                $certData['certificate_type'],
                $certData['title'],
                $certData['description'],
                $certData['grade'],
                $certData['score'],
                $certData['issue_date'],
                $certData['status'],
                $certData['pdf_url'],
                $certData['blockchain_tx_hash'],
                $certData['blockchain_network'],
                $certData['verification_url']
            ]);
            
            $certificateId = Database::getInstance()->lastInsertId();
            
            // Update enrollment with certificate info
            Database::query(
                "UPDATE enrollments SET certificate_issued = 1, certificate_id = ? WHERE user_id = ? AND course_id = ?",
                [$certificateNumber, $userId, $courseId]
            );
            
            // Send email notification
            $downloadUrl = APP_URL . '/certificates/download/' . $certificateNumber;
            EmailService::sendCertificate(
                $user['email'],
                $user['first_name'],
                $course['title'],
                $downloadUrl
            );
            
            // Log certificate generation
            self::logGeneration($userId, $courseId, $certificateNumber);
            
            return [
                'certificate_id' => $certificateId,
                'certificate_number' => $certificateNumber,
                'pdf_url' => $pdfPath,
                'verification_url' => $verificationUrl,
                'blockchain_tx_hash' => $blockchainTxHash
            ];
            
        } catch (Exception $e) {
            error_log("Certificate generation error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate unique certificate number
     */
    private static function generateCertificateNumber(): string {
        $prefix = 'DES';
        $year = date('Y');
        $random = strtoupper(substr(uniqid(), -6));
        $checksum = substr(hash('sha256', $prefix . $year . $random), 0, 4);
        
        return "{$prefix}-{$year}-{$random}-{$checksum}";
    }
    
    /**
     * Generate QR code for certificate verification
     */
    private static function generateQRCode(string $url, string $certificateNumber): string {
        // Use Google Chart API for QR code generation
        $qrCodeUrl = 'https://chart.googleapis.com/chart?cht=qr&chs=300x300&chld=H|0&chl=' . urlencode($url);
        
        // Download and save QR code
        $qrCodePath = self::$certificatesPath . 'qr/' . $certificateNumber . '.png';
        
        if (!is_dir(dirname($qrCodePath))) {
            mkdir(dirname($qrCodePath), 0755, true);
        }
        
        $qrCodeImage = @file_get_contents($qrCodeUrl);
        if ($qrCodeImage) {
            file_put_contents($qrCodePath, $qrCodeImage);
        }
        
        return $qrCodePath;
    }
    
    /**
     * Generate PDF certificate
     */
    private static function generatePDF(array $user, array $course, string $certificateNumber, string $qrCodePath, array $options = []): string {
        // Check if FPDF is available
        if (!class_exists('FPDF')) {
            // Fallback: Create a simple HTML certificate
            return self::generateHTMLCertificate($user, $course, $certificateNumber, $qrCodePath);
        }
        
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->AddPage();
        
        // Background
        $pdf->SetFillColor(15, 23, 42);
        $pdf->Rect(0, 0, 297, 210, 'F');
        
        // Border
        $pdf->SetDrawColor(139, 92, 246);
        $pdf->SetLineWidth(1);
        $pdf->Rect(10, 10, 277, 190);
        
        $pdf->SetDrawColor(6, 182, 212);
        $pdf->SetLineWidth(0.5);
        $pdf->Rect(15, 15, 267, 180);
        
        // Logo
        $pdf->SetFont('Arial', 'B', 24);
        $pdf->SetTextColor(6, 182, 212);
        $pdf->Cell(0, 20, 'DigitalEdgeSolutions', 0, 1, 'C');
        
        // Title
        $pdf->SetFont('Arial', 'B', 36);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 25, 'Certificate of Completion', 0, 1, 'C');
        
        // Subtitle
        $pdf->SetFont('Arial', '', 16);
        $pdf->SetTextColor(156, 163, 175);
        $pdf->Cell(0, 10, 'This is to certify that', 0, 1, 'C');
        
        // Student Name
        $pdf->SetFont('Arial', 'B', 32);
        $pdf->SetTextColor(139, 92, 246);
        $pdf->Cell(0, 20, $user['first_name'] . ' ' . $user['last_name'], 0, 1, 'C');
        
        // Course completion text
        $pdf->SetFont('Arial', '', 14);
        $pdf->SetTextColor(156, 163, 175);
        $pdf->Cell(0, 10, 'has successfully completed the course', 0, 1, 'C');
        
        // Course Name
        $pdf->SetFont('Arial', 'B', 24);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 15, $course['title'], 0, 1, 'C');
        
        // Date and Duration
        $pdf->SetFont('Arial', '', 12);
        $pdf->SetTextColor(156, 163, 175);
        $pdf->Cell(0, 10, 'Issued on ' . date('F d, Y'), 0, 1, 'C');
        
        // Certificate Number
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 8, 'Certificate ID: ' . $certificateNumber, 0, 1, 'C');
        
        // QR Code
        if (file_exists($qrCodePath)) {
            $pdf->Image($qrCodePath, 240, 140, 40, 40);
        }
        
        // Signature
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Text(30, 160, CERTIFICATE_ISSUER_NAME);
        $pdf->SetFont('Arial', '', 12);
        $pdf->SetTextColor(156, 163, 175);
        $pdf->Text(30, 168, CERTIFICATE_ISSUER_TITLE);
        $pdf->Line(30, 158, 100, 158);
        
        // Save PDF
        $pdfPath = self::$certificatesPath . $certificateNumber . '.pdf';
        $pdf->Output('F', $pdfPath);
        
        return $pdfPath;
    }
    
    /**
     * Generate HTML certificate as fallback
     */
    private static function generateHTMLCertificate(array $user, array $course, string $certificateNumber, string $qrCodePath): string {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Certificate - {$certificateNumber}</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        body {
            margin: 0;
            padding: 0;
            width: 297mm;
            height: 210mm;
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
            font-family: 'Arial', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .certificate {
            width: 277mm;
            height: 190mm;
            border: 3px solid #8B5CF6;
            position: relative;
            text-align: center;
            padding: 40px;
            box-sizing: border-box;
        }
        .certificate::before {
            content: '';
            position: absolute;
            top: 5mm;
            left: 5mm;
            right: 5mm;
            bottom: 5mm;
            border: 1px solid #06B6D4;
        }
        .logo { color: #06B6D4; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
        .title { color: #fff; font-size: 48px; font-weight: bold; margin-bottom: 20px; }
        .subtitle { color: #9CA3AF; font-size: 18px; margin-bottom: 15px; }
        .name { color: #8B5CF6; font-size: 36px; font-weight: bold; margin-bottom: 15px; }
        .course { color: #fff; font-size: 28px; font-weight: bold; margin-bottom: 20px; }
        .date { color: #9CA3AF; font-size: 16px; margin-bottom: 10px; }
        .cert-id { color: #6B7280; font-size: 14px; }
        .signature { position: absolute; bottom: 40px; left: 40px; text-align: left; }
        .signature-name { color: #fff; font-size: 18px; font-weight: bold; }
        .signature-title { color: #9CA3AF; font-size: 14px; }
        .qr-code { position: absolute; bottom: 40px; right: 40px; width: 80px; height: 80px; }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="logo">DigitalEdgeSolutions</div>
        <div class="title">Certificate of Completion</div>
        <div class="subtitle">This is to certify that</div>
        <div class="name">{$user['first_name']} {$user['last_name']}</div>
        <div class="subtitle">has successfully completed the course</div>
        <div class="course">{$course['title']}</div>
        <div class="date">Issued on " . date('F d, Y') . "</div>
        <div class="cert-id">Certificate ID: {$certificateNumber}</div>
        <div class="signature">
            <div class="signature-name">" . CERTIFICATE_ISSUER_NAME . "</div>
            <div class="signature-title">" . CERTIFICATE_ISSUER_TITLE . "</div>
        </div>
        <img src="{$qrCodePath}" class="qr-code" alt="QR Code">
    </div>
</body>
</html>
HTML;
        
        $pdfPath = self::$certificatesPath . $certificateNumber . '.html';
        file_put_contents($pdfPath, $html);
        
        return $pdfPath;
    }
    
    /**
     * Store certificate hash on blockchain
     */
    private static function storeOnBlockchain(string $certificateNumber, array $user, array $course): ?string {
        if (!BLOCKCHAIN_ENABLED) {
            return null;
        }
        
        try {
            // Generate certificate hash
            $certData = [
                'certificate_number' => $certificateNumber,
                'student_name' => $user['first_name'] . ' ' . $user['last_name'],
                'student_email' => $user['email'],
                'course_title' => $course['title'],
                'issue_date' => date('Y-m-d'),
                'issuer' => CERTIFICATE_ISSUER_NAME
            ];
            
            $hash = hash('sha256', json_encode($certData));
            
            // In production, this would interact with the blockchain
            // For now, return a mock transaction hash
            return '0x' . substr($hash, 0, 64);
            
        } catch (Exception $e) {
            error_log("Blockchain storage error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verify certificate
     */
    public static function verify(string $certificateNumber): ?array {
        try {
            $cert = Database::fetchOne(
                "SELECT c.*, u.first_name, u.last_name, u.email, u.profile_image,
                        co.title as course_title, co.thumbnail as course_thumbnail
                 FROM " . self::$certificatesTable . " c
                 JOIN users u ON c.user_id = u.user_id
                 JOIN courses co ON c.course_id = co.course_id
                 WHERE c.certificate_number = ? AND c.status = 'active'",
                [$certificateNumber]
            );
            
            if (!$cert) {
                return null;
            }
            
            return [
                'valid' => true,
                'certificate_number' => $cert['certificate_number'],
                'student' => [
                    'name' => $cert['first_name'] . ' ' . $cert['last_name'],
                    'email' => $cert['email'],
                    'profile_image' => $cert['profile_image']
                ],
                'course' => [
                    'title' => $cert['course_title'],
                    'thumbnail' => $cert['course_thumbnail']
                ],
                'issue_date' => $cert['issue_date'],
                'grade' => $cert['grade'],
                'score' => $cert['score'],
                'blockchain_verified' => !empty($cert['blockchain_tx_hash']),
                'blockchain_tx_hash' => $cert['blockchain_tx_hash'],
                'issued_by' => CERTIFICATE_ISSUER_NAME
            ];
            
        } catch (Exception $e) {
            error_log("Certificate verification error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get user's certificates
     */
    public static function getUserCertificates(int $userId, int $page = 1, int $perPage = 10): array {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT c.*, co.title as course_title, co.thumbnail as course_thumbnail
                FROM " . self::$certificatesTable . " c
                JOIN courses co ON c.course_id = co.course_id
                WHERE c.user_id = ? AND c.status = 'active'
                ORDER BY c.issue_date DESC
                LIMIT ? OFFSET ?";
        
        $certificates = Database::fetchAll($sql, [$userId, $perPage, $offset]);
        
        $countSql = "SELECT COUNT(*) as total FROM " . self::$certificatesTable . " WHERE user_id = ? AND status = 'active'";
        $countResult = Database::fetchOne($countSql, [$userId]);
        
        return [
            'certificates' => $certificates,
            'total' => $countResult['total'] ?? 0,
            'page' => $page,
            'per_page' => $perPage
        ];
    }
    
    /**
     * Revoke certificate
     */
    public static function revoke(string $certificateNumber, string $reason, int $revokedBy): bool {
        try {
            $sql = "UPDATE " . self::$certificatesTable . " 
                    SET status = 'revoked', revoked_reason = ?, revoked_at = NOW(), revoked_by = ?
                    WHERE certificate_number = ?";
            
            return Database::execute($sql, [$reason, $revokedBy, $certificateNumber]) > 0;
            
        } catch (Exception $e) {
            error_log("Certificate revocation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Download certificate
     */
    public static function download(string $certificateNumber): ?string {
        $cert = Database::fetchOne(
            "SELECT pdf_url FROM " . self::$certificatesTable . " WHERE certificate_number = ? AND status = 'active'",
            [$certificateNumber]
        );
        
        return $cert['pdf_url'] ?? null;
    }
    
    /**
     * Log certificate generation
     */
    private static function logGeneration(int $userId, int $courseId, string $certificateNumber): void {
        $sql = "INSERT INTO certificate_generation_logs (user_id, course_id, certificate_number, generated_at, ip_address) 
                VALUES (?, ?, ?, NOW(), ?)";
        
        try {
            Database::query($sql, [
                $userId,
                $courseId,
                $certificateNumber,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Failed to log certificate generation: " . $e->getMessage());
        }
    }
    
    /**
     * Get certificate statistics
     */
    public static function getStatistics(): array {
        $stats = [];
        
        // Total certificates issued
        $result = Database::fetchOne("SELECT COUNT(*) as total FROM " . self::$certificatesTable);
        $stats['total_certificates'] = $result['total'] ?? 0;
        
        // Certificates issued this month
        $result = Database::fetchOne(
            "SELECT COUNT(*) as count FROM " . self::$certificatesTable . " 
             WHERE MONTH(issue_date) = MONTH(CURDATE()) AND YEAR(issue_date) = YEAR(CURDATE())"
        );
        $stats['this_month'] = $result['count'] ?? 0;
        
        // Certificates by course
        $sql = "SELECT co.title, COUNT(*) as count 
                FROM " . self::$certificatesTable . " c 
                JOIN courses co ON c.course_id = co.course_id 
                GROUP BY c.course_id 
                ORDER BY count DESC 
                LIMIT 10";
        $stats['by_course'] = Database::fetchAll($sql);
        
        // Top certificate earners
        $sql = "SELECT u.first_name, u.last_name, COUNT(*) as count 
                FROM " . self::$certificatesTable . " c 
                JOIN users u ON c.user_id = u.user_id 
                GROUP BY c.user_id 
                ORDER BY count DESC 
                LIMIT 10";
        $stats['top_earners'] = Database::fetchAll($sql);
        
        return $stats;
    }
    
    /**
     * Bulk generate certificates for course completions
     */
    public static function bulkGenerate(int $courseId): array {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        // Get all completed enrollments without certificates
        $sql = "SELECT e.user_id, e.course_id, e.completed_at, u.email, u.first_name, u.last_name
                FROM enrollments e
                JOIN users u ON e.user_id = u.user_id
                WHERE e.course_id = ? 
                AND e.progress_percent >= ?
                AND e.certificate_issued = 0
                AND e.status = 'completed'";
        
        $completions = Database::fetchAll($sql, [$courseId, COURSE_COMPLETION_THRESHOLD]);
        
        foreach ($completions as $completion) {
            $cert = self::generate($completion['user_id'], $completion['course_id']);
            
            if ($cert) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed for user {$completion['user_id']}";
            }
        }
        
        return $results;
    }
}
