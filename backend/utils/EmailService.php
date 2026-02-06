<?php
/**
 * DigitalEdgeSolutions - Email Service
 * Send emails using SMTP with template support
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class EmailService {
    
    /**
     * Send email using SMTP
     */
    public static function send(string $to, string $subject, string $bodyHtml, string $bodyText = null, array $attachments = []): bool {
        // Use PHPMailer or similar library in production
        // This is a simplified implementation
        
        $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        
        if ($bodyHtml) {
            $boundary = md5(time());
            $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
            
            $message = "--$boundary\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= ($bodyText ?: strip_tags($bodyHtml)) . "\r\n\r\n";
            
            $message .= "--$boundary\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $bodyHtml . "\r\n\r\n";
            
            $message .= "--$boundary--";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message = $bodyText;
        }
        
        // Log email for debugging
        self::logEmail($to, $subject, 'sent');
        
        // In production, use proper SMTP library
        // For now, log the email
        if (APP_DEBUG) {
            error_log("Email to: $to, Subject: $subject");
        }
        
        return true;
    }
    
    /**
     * Send email using template
     */
    public static function sendTemplate(string $templateKey, string $to, array $variables = [], array $attachments = []): bool {
        $template = self::getTemplate($templateKey);
        
        if (!$template) {
            error_log("Email template not found: $templateKey");
            return false;
        }
        
        $subject = self::replaceVariables($template['subject'], $variables);
        $bodyHtml = self::replaceVariables($template['body_html'], $variables);
        $bodyText = self::replaceVariables($template['body_text'], $variables);
        
        return self::send($to, $subject, $bodyHtml, $bodyText, $attachments);
    }
    
    /**
     * Get email template from database
     */
    private static function getTemplate(string $templateKey): ?array {
        $sql = "SELECT * FROM email_templates WHERE template_key = ? AND is_active = 1";
        return Database::fetchOne($sql, [$templateKey]);
    }
    
    /**
     * Replace template variables
     */
    private static function replaceVariables(string $content, array $variables): string {
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
            $content = str_replace('{{ ' . $key . ' }}', $value, $content);
        }
        
        // Replace global variables
        $content = str_replace('{{site_name}}', APP_NAME, $content);
        $content = str_replace('{{site_url}}', APP_URL, $content);
        $content = str_replace('{{support_email}}', SMTP_FROM_EMAIL, $content);
        $content = str_replace('{{current_year}}', date('Y'), $content);
        
        return $content;
    }
    
    /**
     * Log email
     */
    private static function logEmail(string $to, string $subject, string $status): void {
        $sql = "INSERT INTO email_logs (recipient, subject, status, sent_at) VALUES (?, ?, ?, NOW())";
        try {
            Database::query($sql, [$to, $subject, $status]);
        } catch (Exception $e) {
            error_log("Failed to log email: " . $e->getMessage());
        }
    }
    
    /**
     * Send bulk emails
     */
    public static function sendBulk(array $recipients, string $templateKey, array $variables = []): array {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($recipients as $recipient) {
            $to = is_array($recipient) ? $recipient['email'] : $recipient;
            $vars = is_array($recipient) ? array_merge($variables, $recipient) : $variables;
            
            if (self::sendTemplate($templateKey, $to, $vars)) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to send to: $to";
            }
            
            // Rate limiting - sleep briefly between emails
            usleep(100000); // 100ms
        }
        
        return $results;
    }
    
    /**
     * Send welcome email
     */
    public static function sendWelcome(string $to, string $firstName): bool {
        return self::sendTemplate('welcome_email', $to, [
            'first_name' => $firstName,
            'login_url' => APP_URL . '/login'
        ]);
    }
    
    /**
     * Send password reset email
     */
    public static function sendPasswordReset(string $to, string $firstName, string $resetToken): bool {
        return self::sendTemplate('password_reset', $to, [
            'first_name' => $firstName,
            'reset_url' => APP_URL . '/reset-password?token=' . $resetToken
        ]);
    }
    
    /**
     * Send course enrollment confirmation
     */
    public static function sendCourseEnrollment(string $to, string $firstName, string $courseTitle, string $courseUrl): bool {
        return self::sendTemplate('course_enrollment', $to, [
            'first_name' => $firstName,
            'course_title' => $courseTitle,
            'course_url' => $courseUrl
        ]);
    }
    
    /**
     * Send certificate email
     */
    public static function sendCertificate(string $to, string $firstName, string $courseTitle, string $certificateUrl): bool {
        return self::sendTemplate('certificate_issued', $to, [
            'first_name' => $firstName,
            'course_title' => $courseTitle,
            'certificate_url' => $certificateUrl
        ]);
    }
    
    /**
     * Send interview invitation
     */
    public static function sendInterviewInvitation(string $to, string $firstName, string $positionTitle, string $interviewDate, string $meetingLink): bool {
        return self::sendTemplate('interview_scheduled', $to, [
            'first_name' => $firstName,
            'position_title' => $positionTitle,
            'interview_date' => $interviewDate,
            'meeting_link' => $meetingLink
        ]);
    }
    
    /**
     * Send offer letter
     */
    public static function sendOfferLetter(string $to, string $firstName, string $positionTitle, string $offerUrl): bool {
        return self::sendTemplate('offer_letter', $to, [
            'first_name' => $firstName,
            'position_title' => $positionTitle,
            'offer_url' => $offerUrl
        ]);
    }
    
    /**
     * Send payslip
     */
    public static function sendPayslip(string $to, string $firstName, string $monthYear, string $currency, string $netSalary, string $payslipUrl): bool {
        return self::sendTemplate('payslip_generated', $to, [
            'first_name' => $firstName,
            'month_year' => $monthYear,
            'currency' => $currency,
            'net_salary' => $netSalary,
            'payslip_url' => $payslipUrl
        ]);
    }
}
