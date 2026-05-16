<?php
namespace WSR\Premium\License;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * License Guard — Simple license verification for premium features.
 *
 * For the free/development version, always returns true (no license required).
 */
class License_Guard {

    /**
     * Verify if license is valid.
     *
     * @return bool True if license is valid or no license check required.
     */
    public static function verify(): bool {
        // For development/free version: always allow (no license required)
        return true;
    }

    /**
     * Get license status.
     *
     * @return array License status info.
     */
    public static function get_status(): array {
        return [
            'valid'       => true,
            'type'        => 'free',
            'message'     => 'Free/Development version - no license required',
        ];
    }
}
