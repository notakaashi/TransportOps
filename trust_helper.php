<?php
/**
 * Trust Score Helper Functions
 * Handles trust score calculations and badge assignments
 */

require_once 'db.php';

/**
 * Calculate and update user's trust score based on report verification
 * @param int $userId - User ID whose score needs updating
 * @param string $reason - Reason for score change
 * @param int $adjustedBy - Admin user ID (null for automatic adjustments)
 * @return bool - Success status
 */
function updateUserTrustScore($userId, $reason, $adjustedBy = null) {
    try {
        $pdo = getDBConnection();
        
        // Get current trust score
        $stmt = $pdo->prepare("SELECT trust_score FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        $oldScore = (float)$user['trust_score'];
        $newScore = calculateTrustScore($userId);
        
        // Ensure score stays within bounds
        $newScore = max(0, min(100, $newScore));
        
        // Update user's trust score
        $stmt = $pdo->prepare("UPDATE users SET trust_score = ? WHERE id = ?");
        $stmt->execute([$newScore, $userId]);
        
        // Log the change
        $stmt = $pdo->prepare("INSERT INTO trust_score_logs (user_id, old_score, new_score, reason, adjusted_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $oldScore, $newScore, $reason, $adjustedBy]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Trust score update error: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate trust score based on user's report history
 * @param int $userId - User ID
 * @return float - Calculated trust score
 */
function calculateTrustScore($userId) {
    try {
        $pdo = getDBConnection();
        
        // Start with base score of 50
        $score = 50;
        
        // Get user's reports statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_reports,
                SUM(CASE WHEN verification_count >= 3 THEN 1 ELSE 0 END) as verified_reports,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_reports,
                SUM(CASE WHEN verification_count = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as expired_reports
            FROM (
                SELECT 
                    r.*,
                    (SELECT COUNT(*) FROM report_verifications rv WHERE rv.report_id = r.id AND rv.is_verified = 1) as verification_count
                FROM reports r 
                WHERE r.user_id = ?
            ) as user_reports
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();
        
        // Apply scoring logic
        $score += ($stats['verified_reports'] * 5);   // +5 for each verified report
        $score -= ($stats['rejected_reports'] * 10);  // -10 for each rejected report
        $score -= ($stats['expired_reports'] * 2);    // -2 for each expired report
        
        // Bonus for accurate verifications of others' reports
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as accurate_verifications
            FROM report_verifications rv
            JOIN reports r ON rv.report_id = r.id
            WHERE rv.user_id = ? 
            AND rv.is_verified = 1
            AND r.verification_count >= 3
            AND r.user_id != ?
        ");
        $stmt->execute([$userId, $userId]);
        $verificationStats = $stmt->fetch();
        
        $score += ($verificationStats['accurate_verifications'] * 1); // +1 for each accurate verification
        
        return $score;
    } catch (PDOException $e) {
        error_log("Trust score calculation error: " . $e->getMessage());
        return 50; // Return default score on error
    }
}

/**
 * Get trust badge information based on score
 * @param float $score - Trust score
 * @return array - Badge information
 */
function getTrustBadge($score) {
    $score = (float)$score;
    
    if ($score >= 81) {
        return [
            'label' => 'Verified Contributor',
            'color' => 'blue',
            'bg_color' => 'bg-blue-100',
            'text_color' => 'text-blue-800',
            'border_color' => 'border-blue-300'
        ];
    } elseif ($score >= 61) {
        return [
            'label' => 'Trusted Reporter',
            'color' => 'green',
            'bg_color' => 'bg-green-100',
            'text_color' => 'text-green-800',
            'border_color' => 'border-green-300'
        ];
    } elseif ($score >= 41) {
        return [
            'label' => 'Regular Reporter',
            'color' => 'yellow',
            'bg_color' => 'bg-yellow-100',
            'text_color' => 'text-yellow-800',
            'border_color' => 'border-yellow-300'
        ];
    } elseif ($score >= 21) {
        return [
            'label' => 'Low Credibility',
            'color' => 'orange',
            'bg_color' => 'bg-orange-100',
            'text_color' => 'text-orange-800',
            'border_color' => 'border-orange-300'
        ];
    } else {
        return [
            'label' => 'Unreliable Reporter',
            'color' => 'red',
            'bg_color' => 'bg-red-100',
            'text_color' => 'text-red-800',
            'border_color' => 'border-red-300'
        ];
    }
}

/**
 * Get user's public profile information
 * @param int $userId - User ID
 * @return array|false - User profile data or false if not found
 */
function getUserPublicProfile($userId) {
    try {
        $pdo = getDBConnection();
        
        // Get user basic info
        $stmt = $pdo->prepare("
            SELECT id, name, profile_image, trust_score, created_at
            FROM users 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Get user's report statistics - handle missing tables gracefully
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_reports,
                    SUM(CASE WHEN verification_count >= 3 THEN 1 ELSE 0 END) as verified_reports,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_reports
                FROM (
                    SELECT 
                        r.*,
                        (SELECT COUNT(*) FROM report_verifications rv WHERE rv.report_id = r.id AND rv.is_verified = 1) as verification_count
                    FROM reports r 
                    WHERE r.user_id = ?
                ) as user_reports
            ");
            $stmt->execute([$userId]);
            $stats = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Statistics query failed, using defaults: " . $e->getMessage());
            // Fallback to simple count if verification table doesn't exist
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total_reports FROM reports WHERE user_id = ?");
                $stmt->execute([$userId]);
                $stats = $stmt->fetch();
                $stats['verified_reports'] = 0;
                $stats['rejected_reports'] = 0;
            } catch (PDOException $e2) {
                error_log("Simple reports query also failed: " . $e2->getMessage());
                return false;
            }
        }
        
        // Get user's recent verified reports - handle missing tables gracefully
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    r.id,
                    r.crowd_level,
                    r.created_at,
                    rd.route_name,
                    (SELECT COUNT(*) FROM report_verifications rv WHERE rv.report_id = r.id AND rv.is_verified = 1) as verification_count
                FROM reports r
                LEFT JOIN route_definitions rd ON r.route_id = rd.id
                WHERE r.user_id = ? 
                AND (SELECT COUNT(*) FROM report_verifications rv WHERE rv.report_id = r.id AND rv.is_verified = 1) >= 3
                ORDER BY r.created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$userId]);
            $recentReports = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Recent reports query failed, using empty array: " . $e->getMessage());
            $recentReports = [];
        }
        
        return [
            'user' => $user,
            'stats' => $stats,
            'recent_reports' => $recentReports,
            'badge' => getTrustBadge($user['trust_score'])
        ];
    } catch (PDOException $e) {
        error_log("Get user profile error: " . $e->getMessage());
        return false;
    }
}

/**
 * Manually adjust user's trust score (admin function)
 * @param int $userId - User ID to adjust
 * @param float $newScore - New trust score
 * @param string $reason - Reason for adjustment
 * @param int $adminId - Admin user ID making the adjustment
 * @return bool - Success status
 */
function manuallyAdjustTrustScore($userId, $newScore, $reason, $adminId) {
    try {
        $pdo = getDBConnection();
        
        // Get current score
        $stmt = $pdo->prepare("SELECT trust_score FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        $oldScore = (float)$user['trust_score'];
        $newScore = max(0, min(100, (float)$newScore));
        
        // Update user's trust score
        $stmt = $pdo->prepare("UPDATE users SET trust_score = ? WHERE id = ?");
        $stmt->execute([$newScore, $userId]);
        
        // Log the change
        $stmt = $pdo->prepare("INSERT INTO trust_score_logs (user_id, old_score, new_score, reason, adjusted_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $oldScore, $newScore, $reason, $adminId]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Manual trust score adjustment error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all users sorted by trust score (for admin panel)
 * @return array - List of users with trust scores
 */
function getAllUsersByTrustScore() {
    try {
        $pdo = getDBConnection();
        
        // Try to get users with trust_score, fall back if column doesn't exist
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    name,
                    email,
                    trust_score,
                    role,
                    is_active,
                    created_at
                FROM users 
                ORDER BY trust_score ASC
            ");
            $stmt->execute();
            $users = $stmt->fetchAll();
        } catch (PDOException $e) {
            // If trust_score column doesn't exist, query without it
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    name,
                    email,
                    role,
                    is_active,
                    created_at
                FROM users 
                ORDER BY created_at ASC
            ");
            $stmt->execute();
            $users = $stmt->fetchAll();
            
            // Add default trust_score to each user
            foreach ($users as &$user) {
                $user['trust_score'] = 50.0;
            }
        }
        
        return $users;
    } catch (PDOException $e) {
        error_log("Get users by trust score error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get trust score logs for a user
 * @param int $userId - User ID
 * @return array - Trust score logs
 */
function getTrustScoreLogs($userId) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT 
                tsl.*,
                CASE 
                    WHEN tsl.adjusted_by IS NULL THEN 'System'
                    ELSE (SELECT name FROM users WHERE id = tsl.adjusted_by)
                END as adjusted_by_name
            FROM trust_score_logs tsl
            WHERE tsl.user_id = ?
            ORDER BY tsl.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get trust score logs error: " . $e->getMessage());
        return [];
    }
}
?>
