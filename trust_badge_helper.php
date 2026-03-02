<?php
/**
 * Trust Badge Rendering Helper
 * Functions to display trust badges and user information with trust scores
 */

require_once 'trust_helper.php';

/**
 * Render trust badge HTML
 * @param float $score - Trust score
 * @param bool $showScore - Whether to show the numeric score
 * @return string - HTML for trust badge
 */
function renderTrustBadge($score, $showScore = true) {
    $badge = getTrustBadge($score);
    
    $html = '<span class="' . $badge['bg_color'] . ' ' . $badge['text_color'] . ' ' . $badge['border_color'] . ' px-2 py-1 rounded-full text-xs font-medium border">';
    $html .= $badge['label'];
    if ($showScore) {
        $html .= ' (' . number_format($score, 1) . ')';
    }
    $html .= '</span>';
    
    return $html;
}

/**
 * Render user info with trust badge
 * @param array $user - User data with trust_score
 * @param bool $linkToProfile - Whether to link to public profile
 * @return string - HTML for user info with trust badge
 */
function renderUserWithTrustBadge($user, $linkToProfile = true) {
    $badge = getTrustBadge($user['trust_score']);
    
    $html = '<div class="flex items-center space-x-2">';
    
    // User name with optional profile link
    if ($linkToProfile && isset($user['id'])) {
        $html .= '<a href="public_profile.php?id=' . $user['id'] . '" class="font-medium text-gray-800 hover:text-blue-600 transition-colors">';
        $html .= htmlspecialchars($user['name']);
        $html .= '</a>';
    } else {
        $html .= '<span class="font-medium text-gray-800">' . htmlspecialchars($user['name']) . '</span>';
    }
    
    // Trust badge
    $html .= ' ' . renderTrustBadge($user['trust_score']);
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render report card with trust information
 * @param array $report - Report data including user info
 * @param bool $dimLowCredibility - Whether to dim low credibility reports
 * @return string - HTML for report card with trust info
 */
function renderReportWithTrust($report, $dimLowCredibility = true) {
    $userTrustScore = $report['trust_score'] ?? 50;
    $badge = getTrustBadge($userTrustScore);
    
    // Determine if report should be dimmed
    $isDimmed = $dimLowCredibility && $userTrustScore < 20;
    
    $html = '<div class="border border-gray-200 rounded-lg p-4 ' . ($isDimmed ? 'opacity-60' : '') . ' hover:bg-gray-50 transition-colors">';
    
    // Report header with user info
    $html .= '<div class="flex justify-between items-start mb-3">';
    $html .= '<div class="flex-1">';
    
    // User name and trust badge
    if (isset($report['user_id'])) {
        $html .= renderUserWithTrustBadge($report);
    } else {
        $html .= '<span class="font-medium text-gray-800">' . htmlspecialchars($report['user_name'] ?? 'Anonymous') . '</span>';
        $html .= ' ' . renderTrustBadge($userTrustScore);
    }
    
    // Route name
    if (isset($report['route_name'])) {
        $html .= '<p class="text-sm text-gray-600 mt-1">' . htmlspecialchars($report['route_name']) . '</p>';
    }
    
    $html .= '</div>';
    
    // Timestamp
    $html .= '<div class="text-right">';
    $html .= '<p class="text-xs text-gray-500">' . date('M j, g:i A', strtotime($report['created_at'])) . '</p>';
    if (isset($report['verification_count']) && $report['verification_count'] > 0) {
        $html .= '<p class="text-xs text-green-600 mt-1">' . $report['verification_count'] . ' verifications</p>';
    }
    $html .= '</div>';
    
    $html .= '</div>';
    
    // Report content
    $html .= '<div class="space-y-2">';
    
    // Crowd level
    if (isset($report['crowd_level'])) {
        $html .= '<div class="flex items-center space-x-2">';
        $html .= '<span class="text-sm font-medium text-gray-700">Crowd Level:</span>';
        $html .= '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-sm">' . htmlspecialchars($report['crowd_level']) . '</span>';
        $html .= '</div>';
    }
    
    // Delay reason
    if (!empty($report['delay_reason'])) {
        $html .= '<div class="flex items-center space-x-2">';
        $html .= '<span class="text-sm font-medium text-gray-700">Delay Reason:</span>';
        $html .= '<span class="text-sm text-gray-600">' . htmlspecialchars($report['delay_reason']) . '</span>';
        $html .= '</div>';
    }
    
    // Comments
    if (!empty($report['comments'])) {
        $html .= '<div class="text-sm text-gray-600">' . htmlspecialchars($report['comments']) . '</div>';
    }
    
    $html .= '</div>';
    
    // Low credibility warning
    if ($isDimmed) {
        $html .= '<div class="mt-3 p-2 bg-red-50 border border-red-200 rounded">';
        $html .= '<p class="text-xs text-red-700 font-medium">⚠️ Low Credibility Report</p>';
        $html .= '<p class="text-xs text-red-600">This report is from a user with low trust score. Verify with caution.</p>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Get user trust score for report (helper for queries)
 * @param PDO $pdo - Database connection
 * @param int $userId - User ID
 * @return float - Trust score
 */
function getUserTrustScoreForReport($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT trust_score FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        return $user ? (float)$user['trust_score'] : 50.0;
    } catch (PDOException $e) {
        return 50.0; // Default score on error
    }
}

/**
 * Enhance reports array with user trust information
 * @param array $reports - Reports array
 * @param PDO $pdo - Database connection
 * @return array - Enhanced reports with trust info
 */
function enhanceReportsWithTrustInfo($reports, $pdo) {
    foreach ($reports as &$report) {
        if (isset($report['user_id'])) {
            $report['trust_score'] = getUserTrustScoreForReport($pdo, $report['user_id']);
        } else {
            $report['trust_score'] = 50.0; // Default for anonymous reports
        }
    }
    return $reports;
}
?>
