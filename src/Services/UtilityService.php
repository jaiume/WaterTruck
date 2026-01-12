<?php

declare(strict_types=1);

namespace WaterTruck\Services;

class UtilityService
{
    private static ?\PDO $pdo = null;

    /**
     * Get PDO database connection (singleton)
     */
    public static function getDbConnection(): \PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                ConfigService::get('database.host', 'localhost'),
                ConfigService::get('database.port', '3306'),
                ConfigService::get('database.name'),
                ConfigService::get('database.charset', 'utf8mb4')
            );
            
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            self::$pdo = new \PDO(
                $dsn, 
                ConfigService::get('database.user'),
                ConfigService::get('database.pass'),
                $options
            );
        }
        
        return self::$pdo;
    }

    /**
     * Generate a UUID v4
     */
    public static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Get base URL of the application
     */
    public function getBaseUrl(): string
    {
        return ConfigService::get('app.url', 'https://water-dev.stuckbendix.com');
    }

    /**
     * Generate a share URL for a job
     */
    public function getJobShareUrl(int $jobId): string
    {
        return $this->getBaseUrl() . '/job/' . $jobId;
    }

    /**
     * Generate an invite URL
     */
    public function getInviteUrl(string $token): string
    {
        return $this->getBaseUrl() . '/invite/' . $token;
    }

    /**
     * Format phone number for display
     */
    public function formatPhone(string $phone): string
    {
        // Remove all non-digit characters
        $digits = preg_replace('/[^0-9]/', '', $phone);
        
        // Format based on length
        $len = strlen($digits);
        
        if ($len === 10) {
            // Format: (XXX) XXX-XXXX
            return sprintf('(%s) %s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6, 4)
            );
        } elseif ($len === 7) {
            // Format: XXX-XXXX
            return sprintf('%s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 4)
            );
        }
        
        // Return as-is if unknown format
        return $phone;
    }

    /**
     * Format currency for display
     */
    public function formatCurrency(float $amount, string $symbol = '$'): string
    {
        return $symbol . number_format($amount, 2);
    }

    /**
     * Format capacity with units
     */
    public function formatCapacity(int $gallons): string
    {
        return number_format($gallons) . ' gallons';
    }

    /**
     * Calculate ETA text from queue length and average job time
     */
    public function formatEtaText(int $queueLength, int $avgMinutes): string
    {
        if ($queueLength === 0) {
            return 'Available now';
        }
        
        $minMinutes = $queueLength * $avgMinutes;
        $maxMinutes = ($queueLength + 1) * $avgMinutes;
        
        if ($minMinutes < 60) {
            return sprintf('%d-%d minutes', $minMinutes, $maxMinutes);
        }
        
        $minHours = floor($minMinutes / 60);
        $maxHours = ceil($maxMinutes / 60);
        
        if ($minHours === $maxHours) {
            return sprintf('~%d hour%s', $minHours, $minHours > 1 ? 's' : '');
        }
        
        return sprintf('%d-%d hours', (int) $minHours, (int) $maxHours);
    }

    /**
     * Generate a random token
     */
    public function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Sanitize string for output
     */
    public function sanitize(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate email format
     */
    public function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone number (basic check)
     */
    public function isValidPhone(string $phone): bool
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        return strlen($digits) >= 7 && strlen($digits) <= 15;
    }

    /**
     * Get current timestamp in database format
     */
    public function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Format date for display
     */
    public function formatDate(string $dateString, string $format = 'M j, Y g:i A'): string
    {
        $date = new \DateTime($dateString);
        return $date->format($format);
    }

    /**
     * Get time ago string (e.g., "5 minutes ago")
     */
    public function timeAgo(string $dateString): string
    {
        $date = new \DateTime($dateString);
        $now = new \DateTime();
        $diff = $now->diff($date);
        
        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        }
        if ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        }
        if ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        }
        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        }
        if ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        }
        
        return 'Just now';
    }
}
