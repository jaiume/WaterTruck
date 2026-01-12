<?php

declare(strict_types=1);

namespace WaterTruck\Services;

class ConfigService
{
    private static ?array $config = null;

    /**
     * Get a configuration value
     * 
     * @param string $key Key in format "section.name" or just "name"
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$config === null) {
            self::load();
        }

        if (str_contains($key, '.')) {
            [$section, $name] = explode('.', $key, 2);
            
            if (!isset(self::$config[$section][$name])) {
                return $default;
            }
            
            return self::parseValue(self::$config[$section][$name]);
        }

        // Search all sections if no section specified
        foreach (self::$config as $section) {
            if (isset($section[$key])) {
                return self::parseValue($section[$key]);
            }
        }

        return $default;
    }

    /**
     * Get entire section as array
     */
    public static function getSection(string $section): array
    {
        if (self::$config === null) {
            self::load();
        }

        return self::$config[$section] ?? [];
    }

    /**
     * Load configuration from ini file
     */
    private static function load(): void
    {
        $configPath = dirname(__DIR__, 2) . '/config/config.ini';
        
        if (!file_exists($configPath)) {
            throw new \RuntimeException("Configuration file not found: {$configPath}");
        }
        
        $config = parse_ini_file($configPath, true);
        
        if ($config === false) {
            throw new \RuntimeException("Failed to parse configuration file");
        }
        
        self::$config = $config;
    }

    /**
     * Parse value to correct type
     */
    private static function parseValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        // Convert string "true" or "false" to boolean
        if (strtolower($value) === 'true' || $value === '1') {
            return true;
        }
        
        if (strtolower($value) === 'false' || $value === '0' || $value === '') {
            return false;
        }
        
        // Convert numeric strings to numbers
        if (is_numeric($value)) {
            if (strpos($value, '.') !== false) {
                return (float) $value;
            }
            return (int) $value;
        }
        
        return $value;
    }

    /**
     * Reload configuration (useful for testing)
     */
    public static function reload(): void
    {
        self::$config = null;
    }
}
