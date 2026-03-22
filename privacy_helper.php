<?php
/**
 * Privacy Helper Functions
 * Handles name censoring and privacy protection for user data
 */

/**
 * Censor a user's name for privacy protection
 * Shows first and last letters with asterisks in between
 * 
 * @param string $fullName The full name to censor
 * @param bool $showFirst Whether to show first name fully (default: false)
 * @return string The censored name
 */
function censorUserName($fullName, $showFirst = false) {
    if (empty($fullName)) {
        return "Anonymous";
    }
    
    // Trim and clean the name
    $fullName = trim($fullName);
    $nameParts = preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY);
    
    // If only one name, censor it elegantly
    if (count($nameParts) === 1) {
        return maskNamePart($nameParts[0]);
    }
    
    // Multiple names: apply elegant censoring to each part
    $censoredParts = [];
    
    foreach ($nameParts as $part) {
        $censoredParts[] = maskNamePart($part);
    }
    
    if ($showFirst && count($censoredParts) > 0) {
        // Show first name fully, censor the rest elegantly
        $firstName = $nameParts[0];
        unset($censoredParts[0]);
        return $firstName . ' ' . implode(' ', $censoredParts);
    } else {
        // Censor all parts elegantly
        return implode(' ', $censoredParts);
    }
}

function maskNamePart($namePart) {
    $length = function_exists('mb_strlen') ? mb_strlen($namePart) : strlen($namePart);

    if ($length <= 2) {
        return str_repeat('*', $length);
    }

    if ($length === 3) {
        return getNameChar($namePart, 0) . '*' . getNameChar($namePart, 2);
    }

    return getNameChar($namePart, 0)
        . str_repeat('*', $length - 2)
        . getNameChar($namePart, $length - 1);
}

function getNameChar($value, $index) {
    if (function_exists('mb_substr')) {
        return mb_substr($value, $index, 1);
    }

    return substr($value, $index, 1);
}

/**
 * Get user initials from censored name
 * 
 * @param string $fullName The full name
 * @return string The initials (max 2 chars)
 */
function getCensoredInitials($fullName) {
    if (empty($fullName)) {
        return 'A';
    }
    
    $fullName = trim($fullName);
    $nameParts = explode(' ', $fullName);
    
    $initials = '';
    foreach ($nameParts as $part) {
        if (!empty($part) && strlen($part) > 0) {
            $initials .= strtoupper(substr($part, 0, 1));
            if (strlen($initials) >= 2) break;
        }
    }
    
    return $initials ?: 'A';
}
?>
