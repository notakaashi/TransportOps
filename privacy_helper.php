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
 * @param bool $showFirst Whether to show first name fully (default: true)
 * @return string The censored name
 */
function censorUserName($fullName, $showFirst = true) {
    if (empty($fullName)) {
        return "Anonymous";
    }
    
    // Trim and clean the name
    $fullName = trim($fullName);
    $nameParts = explode(' ', $fullName);
    
    // If only one name, censor it elegantly
    if (count($nameParts) === 1) {
        $name = $nameParts[0];
        if (strlen($name) <= 2) {
            return str_repeat('*', strlen($name));
        }
        if (strlen($name) === 3) {
            return substr($name, 0, 1) . '*' . substr($name, 2, 1);
        }
        // Show first and last letter with asterisks in between
        return substr($name, 0, 1) . str_repeat('*', strlen($name) - 2) . substr($name, -1);
    }
    
    // Multiple names: apply elegant censoring to each part
    $censoredParts = [];
    
    foreach ($nameParts as $index => $part) {
        if (strlen($part) <= 2) {
            $censoredParts[] = str_repeat('*', strlen($part));
        } elseif (strlen($part) === 3) {
            $censoredParts[] = substr($part, 0, 1) . '*' . substr($part, 2, 1);
        } else {
            // Show first and last letter with asterisks in between
            $censoredParts[] = substr($part, 0, 1) . str_repeat('*', strlen($part) - 2) . substr($part, -1);
        }
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
