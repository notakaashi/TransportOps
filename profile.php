<?php
/**
 * User Profile Page – redesigned to match the Transport Ops glass UI
 * Allows logged-in users to view/edit their profile details and profile picture.
 */

require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$error = "";
$success = "";
$reportCount = 0;

try {
    $pdo = getDBConnection();

    // Load current user (extended to include trust_score, role, created_at)
    $stmt = $pdo->prepare(
        "SELECT id, name, email, profile_image, trust_score, role, created_at
         FROM users WHERE id = ?",
    );
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error = "User not found.";
    }

    // Report count for hero stats
    $stmtC = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE user_id = ?");
    $stmtC->execute([$user_id]);
    $reportCount = (int) $stmtC->fetchColumn();
} catch (PDOException $e) {
    error_log("Profile load error: " . $e->getMessage());
    $error = "Failed to load profile.";
    $user = [
        "id" => $user_id,
        "name" => $_SESSION["user_name"] ?? "User",
        "email" => "",
        "profile_image" => null,
        "trust_score" => 0,
        "role" => $_SESSION["role"] ?? "User",
        "created_at" => date("Y-m-d"),
    ];
}

// ── Image upload / delete ─────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if ($_POST["action"] === "upload_image") {
        if (
            !isset($_FILES["profile_image"]) ||
            $_FILES["profile_image"]["error"] !== UPLOAD_ERR_OK
        ) {
            $error = "Please select a valid file to upload.";
        } else {
            $file = $_FILES["profile_image"];
            $allowed_types = [
                "image/jpeg",
                "image/jpg",
                "image/png",
                "image/gif",
                "image/webp",
            ];
            $max_size = 5 * 1024 * 1024; // 5 MB

            if (!in_array($file["type"], $allowed_types)) {
                $error =
                    "Invalid file type. Please upload a JPG, PNG, GIF, or WebP image.";
            } elseif ($file["size"] > $max_size) {
                $error = "File too large. Maximum allowed size is 5 MB.";
            } else {
                $ext = pathinfo($file["name"], PATHINFO_EXTENSION);
                $filename = "profile_" . $user_id . "_" . time() . "." . $ext;
                $dest = "uploads/" . $filename;

                if (!is_dir("uploads")) {
                    mkdir("uploads", 0755, true);
                }

                if (move_uploaded_file($file["tmp_name"], $dest)) {
                    if (
                        $user["profile_image"] &&
                        file_exists("uploads/" . $user["profile_image"])
                    ) {
                        unlink("uploads/" . $user["profile_image"]);
                    }
                    $pdo->prepare(
                        "UPDATE users SET profile_image = ? WHERE id = ?",
                    )->execute([$filename, $user_id]);
                    $user["profile_image"] = $filename;
                    $_SESSION["profile_image"] = $filename;
                    $success = "Profile photo updated successfully!";
                } else {
                    $error = "Failed to upload image. Please try again.";
                }
            }
        }
    }

    if ($_POST["action"] === "delete_image") {
        if (
            $user["profile_image"] &&
            file_exists("uploads/" . $user["profile_image"])
        ) {
            unlink("uploads/" . $user["profile_image"]);
        }
        $pdo->prepare(
            "UPDATE users SET profile_image = NULL WHERE id = ?",
        )->execute([$user_id]);
        $user["profile_image"] = null;
        $_SESSION["profile_image"] = null;
        $success = "Profile photo removed.";
    }
}

// ── Profile info update ───────────────────────────────────────────────────────
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    empty($error) &&
    !isset($_POST["action"])
) {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($name === "" || $email === "") {
        $error = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($new_password !== "" && strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT id FROM users WHERE email = ? AND id != ?",
            );
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error = "That email address is already in use.";
            } else {
                if ($new_password !== "") {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $pdo->prepare(
                        "UPDATE users SET name=?, email=?, password=? WHERE id=?",
                    )->execute([$name, $email, $hashed, $user_id]);
                } else {
                    $pdo->prepare(
                        "UPDATE users SET name=?, email=? WHERE id=?",
                    )->execute([$name, $email, $user_id]);
                }
                $_SESSION["user_name"] = $name;
                $_SESSION["user_email"] = $email;
                $user["name"] = $name;
                $user["email"] = $email;
                $success = "Profile updated successfully.";
            }
        } catch (PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            $error = "Failed to update profile. Please try again.";
        }
    }
}

// Convenience vars
$userName = htmlspecialchars($user["name"] ?? "User");
$userInitial = strtoupper(substr($user["name"] ?? "U", 0, 1));
$userRole = htmlspecialchars($user["role"] ?? "User");
$trustScore = number_format((float) ($user["trust_score"] ?? 0), 1);
$memberSince = date("M j, Y", strtotime($user["created_at"] ?? "now"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — Transport Ops</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

:root {
    --navy:      #22335C;
    --navy-deep: #0f1c36;
    --navy-mid:  #19284a;
    --slate:     #5B7B99;
    --gold:      #FBC061;
    --gold-dark: #e8a83e;
    --cream:     #E8E1D8;
}

body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(145deg, #e8edf8 0%, #f0f4ff 40%, #edf3f0 70%, #f5f0ea 100%);
    min-height: 100vh;
    color: #1e293b;
}

/* ── Floating Nav ────────────────────────────────── */
.glass-nav {
    background: rgba(34,51,92,0.78);
    backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,0.15);
    box-shadow: 0 8px 32px rgba(15,28,54,0.35), 0 2px 8px rgba(0,0,0,0.15);
    transition: background 0.3s, box-shadow 0.3s, top 0.3s;
}
.glass-nav.scrolled {
    background: rgba(34,51,92,0.96);
    box-shadow: 0 12px 40px rgba(15,28,54,0.5), 0 4px 12px rgba(0,0,0,0.25);
}
.nav-link {
    display: inline-block; padding: 0.45rem 0.9rem; border-radius: 0.5rem;
    font-size: 0.875rem; font-weight: 500; color: #cbd5e1;
    border: 1px solid transparent; text-decoration: none; transition: all 0.2s;
}
.nav-link:hover  { background: rgba(255,255,255,0.14); border-color: rgba(255,255,255,0.22); color: #fff; }
.nav-link.active { background: rgba(255,255,255,0.22); border-color: rgba(255,255,255,0.3);  color: #fff; }
.nav-link-mobile {
    display: block; padding: 0.5rem 0.9rem; border-radius: 0.5rem;
    font-size: 0.875rem; font-weight: 500; color: #cbd5e1;
    border: 1px solid transparent; text-decoration: none; transition: all 0.2s;
}
.nav-link-mobile:hover  { background: rgba(255,255,255,0.14); border-color: rgba(255,255,255,0.22); color: #fff; }
.nav-link-mobile.active { background: rgba(255,255,255,0.22); border-color: rgba(255,255,255,0.3);  color: #fff; }
.glass-dropdown {
    background: rgba(25,40,74,0.97);
    backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,0.12);
    box-shadow: 0 8px 32px rgba(15,28,54,0.45);
}

/* ── Hero ────────────────────────────────────────── */
.hero {
    position: relative;
    background: linear-gradient(135deg, var(--navy-deep) 0%, var(--navy-mid) 55%, #1a2f5a 100%);
    overflow: hidden;
}
.hero::before {
    content: '';
    position: absolute; inset: 0;
    background:
        radial-gradient(ellipse at 15% 60%, rgba(91,123,153,0.28) 0%, transparent 55%),
        radial-gradient(ellipse at 85% 15%, rgba(251,192,97,0.13) 0%, transparent 50%);
}
.hero-grid {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 48px 48px;
}
.hero-orb { position: absolute; border-radius: 50%; pointer-events: none; }
.hero-orb-1 {
    width: 500px; height: 500px; top: -170px; right: -90px;
    background: radial-gradient(circle, rgba(91,123,153,0.18) 0%, transparent 70%);
    animation: orbPulse 18s ease-in-out infinite alternate;
}
.hero-orb-2 {
    width: 300px; height: 300px; bottom: -100px; left: 5%;
    background: radial-gradient(circle, rgba(251,192,97,0.08) 0%, transparent 70%);
    animation: orbPulse 24s ease-in-out infinite alternate-reverse;
}
@keyframes orbPulse {
    from { transform: translate(0,0) scale(1); }
    to   { transform: translate(28px,18px) scale(1.08); }
}
.role-badge {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: rgba(251,192,97,0.15); border: 1px solid rgba(251,192,97,0.35);
    color: var(--gold); border-radius: 999px;
    padding: 0.28rem 0.85rem; font-size: 0.72rem; font-weight: 700;
    letter-spacing: 0.07em; text-transform: uppercase;
}
.hero-name {
    font-family: 'Poppins', sans-serif;
    font-size: clamp(1.7rem, 4vw, 2.5rem);
    font-weight: 800; color: #fff; line-height: 1.15; letter-spacing: -0.02em;
}
.hero-name .gold { color: var(--gold); }
.hero-subtitle  { color: #94a3b8; font-size: 0.9rem; line-height: 1.6; }
.hero-avatar {
    width: 80px; height: 80px; border-radius: 50%;
    border: 3px solid rgba(251,192,97,0.55);
    object-fit: cover; flex-shrink: 0;
}
.hero-avatar-initials {
    width: 80px; height: 80px; border-radius: 50%;
    border: 3px solid rgba(251,192,97,0.55);
    background: var(--slate);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Poppins', sans-serif;
    font-size: 1.8rem; font-weight: 800; color: #fff; flex-shrink: 0;
}
.hero-stat {
    font-size: 0.82rem; color: rgba(255,255,255,0.55); font-weight: 500;
}
.hero-stat strong {
    color: #fff; font-family: 'Poppins', sans-serif;
    font-weight: 800; font-size: 0.95rem; margin-right: 0.15rem;
}
.cta-btn {
    display: inline-flex; align-items: center; gap: 0.45rem;
    background: var(--gold); color: var(--navy-deep);
    font-weight: 700; font-size: 0.875rem;
    padding: 0.62rem 1.35rem; border-radius: 0.55rem;
    text-decoration: none; border: none; cursor: pointer;
    transition: all 0.2s; box-shadow: 0 4px 18px rgba(251,192,97,0.35);
}
.cta-btn:hover { background: var(--gold-dark); transform: translateY(-2px); box-shadow: 0 8px 26px rgba(251,192,97,0.45); }
.cta-btn-ghost {
    display: inline-flex; align-items: center; gap: 0.45rem;
    background: rgba(255,255,255,0.1); color: #e2e8f0;
    font-weight: 600; font-size: 0.875rem;
    padding: 0.62rem 1.35rem; border-radius: 0.55rem;
    border: 1px solid rgba(255,255,255,0.2);
    text-decoration: none; cursor: pointer; transition: all 0.2s;
}
.cta-btn-ghost:hover { background: rgba(255,255,255,0.18); color: #fff; }

/* ── Section headings ───────────────────────────── */
.sec-eyebrow {
    font-size: 0.67rem; font-weight: 700; letter-spacing: 0.12em;
    text-transform: uppercase; color: var(--slate);
    display: flex; align-items: center; gap: 0.5rem;
}
.sec-eyebrow::before {
    content: ''; display: block; width: 1.3rem; height: 2px;
    background: var(--gold); border-radius: 999px;
}
.sec-heading {
    font-family: 'Poppins', sans-serif;
    font-size: 1.2rem; font-weight: 800; color: var(--navy);
    letter-spacing: -0.02em;
}

/* ── Glass cards ────────────────────────────────── */
.glass-card {
    background: rgba(255,255,255,0.82);
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.95);
    box-shadow: 0 4px 24px rgba(34,51,92,0.07), 0 1px 4px rgba(34,51,92,0.04);
    border-radius: 1.125rem;
}

/* ── Form elements ──────────────────────────────── */
.form-label {
    display: block; font-size: 0.72rem; font-weight: 700;
    letter-spacing: 0.07em; text-transform: uppercase;
    color: var(--slate); margin-bottom: 0.45rem;
}
.form-label .opt {
    font-weight: 500; font-size: 0.68rem; letter-spacing: 0;
    text-transform: none; color: #94a3b8;
}
.glass-input {
    width: 100%; padding: 0.7rem 1rem;
    background: rgba(255,255,255,0.85);
    border: 1px solid rgba(34,51,92,0.15);
    border-radius: 0.6rem; font-size: 0.875rem;
    color: #1e293b; font-family: 'Inter', sans-serif;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.glass-input:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(251,192,97,0.18);
    background: #fff;
}
.glass-input::placeholder { color: #94a3b8; }

/* Save button (in-form, navy fill) */
.save-btn {
    display: inline-flex; align-items: center; gap: 0.45rem;
    background: var(--navy); color: #fff;
    font-weight: 700; font-size: 0.875rem;
    padding: 0.7rem 1.6rem; border-radius: 0.6rem;
    border: none; cursor: pointer; transition: all 0.2s;
    box-shadow: 0 4px 16px rgba(34,51,92,0.2);
}
.save-btn:hover { background: var(--navy-mid); transform: translateY(-1px); box-shadow: 0 8px 22px rgba(34,51,92,0.28); }

.cancel-btn {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: rgba(34,51,92,0.07); color: var(--navy);
    font-weight: 600; font-size: 0.875rem;
    padding: 0.7rem 1.4rem; border-radius: 0.6rem;
    border: 1px solid rgba(34,51,92,0.12);
    text-decoration: none; cursor: pointer; transition: all 0.2s;
}
.cancel-btn:hover { background: rgba(34,51,92,0.12); }

/* Upload / remove buttons */
.upload-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem;
    background: var(--navy); color: #fff;
    font-weight: 600; font-size: 0.82rem;
    padding: 0.6rem 1.25rem; border-radius: 0.55rem;
    border: none; cursor: pointer; width: 100%;
    transition: all 0.2s; box-shadow: 0 3px 12px rgba(34,51,92,0.2);
}
.upload-btn:hover { background: var(--navy-mid); transform: translateY(-1px); }

.remove-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
    background: rgba(239,68,68,0.09); color: #dc2626;
    font-weight: 600; font-size: 0.8rem;
    padding: 0.55rem 1.25rem; border-radius: 0.55rem;
    border: 1px solid rgba(239,68,68,0.22); cursor: pointer; width: 100%;
    transition: all 0.2s;
}
.remove-btn:hover { background: rgba(239,68,68,0.16); transform: translateY(-1px); }

/* Avatar ring hover effect */
.avatar-wrap { position: relative; display: inline-block; }
.avatar-wrap:hover .avatar-overlay { opacity: 1; }
.avatar-overlay {
    position: absolute; inset: 0; border-radius: 50%;
    background: rgba(15,28,54,0.35);
    display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: opacity 0.2s; cursor: pointer;
}

/* Alert cards */
.alert-success {
    background: rgba(22,163,74,0.08); border: 1px solid rgba(22,163,74,0.25);
    color: #166534; border-radius: 0.75rem;
    padding: 0.85rem 1.1rem; font-size: 0.875rem; font-weight: 500;
    display: flex; align-items: center; gap: 0.6rem;
}
.alert-error {
    background: rgba(220,38,38,0.08); border: 1px solid rgba(220,38,38,0.22);
    color: #991b1b; border-radius: 0.75rem;
    padding: 0.85rem 1.1rem; font-size: 0.875rem; font-weight: 500;
    display: flex; align-items: center; gap: 0.6rem;
}

/* Trust score pill */
.trust-pill {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.22rem 0.75rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700;
    background: rgba(251,192,97,0.15); border: 1px solid rgba(251,192,97,0.35); color: #92600a;
}

/* ── Footer ─────────────────────────────────────── */
.site-footer {
    background: linear-gradient(135deg, var(--navy-deep) 0%, var(--navy-mid) 100%);
    position: relative; overflow: hidden; margin-top: 5rem;
}
.site-footer::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse at 80% 50%, rgba(91,123,153,0.12) 0%, transparent 60%);
}
.footer-logo { font-family: 'Poppins', sans-serif; font-size: 1.2rem; font-weight: 800; color: #fff; }
.footer-logo span { color: var(--gold); }
.footer-link { color: #94a3b8; text-decoration: none; font-size: 0.85rem; transition: color 0.2s; }
.footer-link:hover { color: var(--gold); }

/* ── Scroll reveal ───────────────────────────────── */
.reveal {
    opacity: 0; transform: translateY(22px);
    transition: opacity 0.6s cubic-bezier(.4,0,.2,1), transform 0.6s cubic-bezier(.4,0,.2,1);
}
.reveal.visible { opacity: 1; transform: none; }
.rd1 { transition-delay: 0.07s; }
.rd2 { transition-delay: 0.14s; }

@media (max-width: 640px) {
    .hero-name { font-size: 1.6rem; }
}
</style>
</head>
<body>

<!-- ═══════════════ FLOATING NAV ═══════════════ -->
<nav id="floatingNav" class="fixed top-4 left-1/2 -translate-x-1/2 z-40 glass-nav text-white rounded-2xl w-[calc(100%-2rem)] max-w-7xl">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-14">

            <div class="flex items-center gap-7">
                <a href="user_dashboard.php" id="brandLink"
                   style="font-family:'Poppins',sans-serif;font-size:1.2rem;font-weight:800;color:#fff;text-decoration:none;white-space:nowrap;letter-spacing:-0.01em;">
                    Transport<span style="color:var(--gold);">Ops</span>
                </a>
                <div class="hidden md:flex gap-1">
                    <a href="user_dashboard.php" class="nav-link">Home</a>
                    <a href="report.php"         class="nav-link">Submit Report</a>
                    <a href="reports_map.php"    class="nav-link">Reports Map</a>
                    <a href="routes.php"         class="nav-link">Routes</a>
                    <a href="about.php"          class="nav-link">About</a>
                </div>
                <div id="mobileMenu"
                     class="md:hidden hidden absolute top-full left-0 right-0 mt-2 flex flex-col gap-1 px-4 py-3 z-20 rounded-2xl"
                     style="background:rgba(25,40,74,0.97);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,0.12);box-shadow:0 8px 32px rgba(15,28,54,0.4);">
                    <a href="user_dashboard.php" class="nav-link-mobile">Home</a>
                    <a href="report.php"         class="nav-link-mobile">Submit Report</a>
                    <a href="reports_map.php"    class="nav-link-mobile">Reports Map</a>
                    <a href="routes.php"         class="nav-link-mobile">Routes</a>
                    <a href="about.php"          class="nav-link-mobile">About</a>
                </div>
            </div>

            <!-- Profile button -->
            <div class="relative">
                <button id="profileMenuButton"
                        class="flex items-center gap-2 px-2 py-1.5 rounded-full hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/40 transition">
                    <div class="hidden sm:flex flex-col items-end leading-tight">
                        <span class="text-sm text-white font-medium"><?= $userName ?></span>
                        <span class="text-[11px] text-blue-200"><?= $userRole ?></span>
                    </div>
                    <div class="flex items-center gap-1">
                        <?php if ($user["profile_image"]): ?>
                        <img src="uploads/<?= htmlspecialchars(
                            $user["profile_image"],
                        ) ?>"
                             alt="Profile" class="h-8 w-8 rounded-full object-cover border-2 border-white/50">
                        <?php else: ?>
                        <div class="h-8 w-8 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
                             style="background:var(--slate);"><?= $userInitial ?></div>
                        <?php endif; ?>
                        <svg class="w-4 h-4 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </button>
                <div id="profileMenu" class="hidden absolute right-0 top-12 w-52 glass-dropdown rounded-xl shadow-xl py-1 z-50">
                    <a href="profile.php"
                       class="block px-4 py-2 text-sm text-white hover:bg-white/10 mx-1 rounded-lg font-semibold" style="background:rgba(255,255,255,0.1);">View &amp; Edit Profile</a>
                    <a href="public_profile.php?id=<?= $user_id ?>"
                       class="block px-4 py-2 text-sm text-white hover:bg-white/10 mx-1 rounded-lg">Public Profile</a>
                    <div class="my-1 border-t border-white/10"></div>
                    <a href="logout.php"
                       class="block px-4 py-2 text-sm text-red-300 hover
:bg-white/10 mx-1 rounded-lg">Logout</a>
                </div>
            </div>

        </div>
    </div>
</nav>

<!-- ═══════════════ HERO ═══════════════ -->
<section class="hero pt-20">
    <div class="hero-grid"></div>
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-14 relative z-10">
        <div class="flex flex-col sm:flex-row items-center gap-6">

            <!-- Avatar -->
            <?php if ($user["profile_image"]): ?>
            <img src="uploads/<?= htmlspecialchars($user["profile_image"]) ?>"
                 alt="Avatar" class="hero-avatar">
            <?php else: ?>
            <div class="hero-avatar-initials"><?= $userInitial ?></div>
            <?php endif; ?>

            <!-- Info -->
            <div>
                <div class="role-badge mb-3">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                    <?= $userRole ?>
                </div>
                <h1 class="hero-name"><?= $userName ?><br><span class="gold">My Profile</span></h1>
                <p class="hero-subtitle mt-2">Manage your personal details, profile photo, and account security.</p>

                <!-- Quick stats -->
                <div class="flex flex-wrap items-center gap-5 mt-4">
                    <span class="hero-stat"><strong><?= $trustScore ?></strong> / 100 Trust Score</span>
                    <span class="hero-stat"><strong><?= $reportCount ?></strong> Report<?= $reportCount !== 1 ? "s" : "" ?> Submitted</span>
                    <span class="hero-stat">Since <strong><?= $memberSince ?></strong></span>
                </div>

                <!-- CTAs -->
                <div class="flex flex-wrap gap-3 mt-6">
                    <a href="public_profile.php?id=<?= $user_id ?>" class="cta-btn">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        View Public Profile
                    </a>
                    <a href="user_dashboard.php" class="cta-btn-ghost">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ MAIN ═══════════════ -->
<main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <!-- Flash messages -->
    <?php if ($success): ?>
    <div class="alert-success mb-6 reveal">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert-error mb-6 reveal">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ── Photo card (1/3) ── -->
        <div class="glass-card p-6 reveal rd1 flex flex-col items-center gap-5">
            <div class="sec-eyebrow w-full mb-1">Photo</div>
            <h2 class="sec-heading w-full mb-2">Profile Photo</h2>

            <!-- Avatar display -->
            <div class="avatar-wrap" onclick="document.getElementById('profile_image').click();" title="Click to change photo">
                <?php if ($user["profile_image"]): ?>
                <img src="uploads/<?= htmlspecialchars($user["profile_image"]) ?>"
                     alt="Profile"
                     style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid rgba(251,192,97,0.5);display:block;">
                <?php else: ?>
                <div style="width:96px;height:96px;border-radius:50%;background:var(--slate);border:3px solid rgba(251,192,97,0.5);display:flex;align-items:center;justify-content:center;font-family:'Poppins',sans-serif;font-size:2.2rem;font-weight:800;color:#fff;">
                    <?= $userInitial ?>
                </div>
                <?php endif; ?>
                <div class="avatar-overlay">
                    <svg width="22" height="22" fill="none" stroke="#fff" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
            </div>

            <p style="font-size:.75rem;color:#94a3b8;text-align:center;line-height:1.5;">JPG, PNG, GIF or WebP &bull; max 5 MB</p>

            <!-- Hidden upload form -->
            <form id="upload_form" method="POST" action="" enctype="multipart/form-data" class="w-full">
                <input type="hidden" name="action" value="upload_image">
                <input type="file" id="profile_image" name="profile_image" accept="image/*"
                       class="hidden" onchange="document.getElementById('upload_form').submit();">
                <button type="button" class="upload-btn" onclick="document.getElementById('profile_image').click();">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Upload Photo
                </button>
            </form>

            <!-- Delete form (only if image exists) -->
            <?php if ($user["profile_image"]): ?>
            <form method="POST" action="" class="w-full"
                  onsubmit="return confirm('Remove your profile photo? This cannot be undone.');">
                <input type="hidden" name="action" value="delete_image">
                <button type="submit" class="remove-btn">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Remove Photo
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- ── Edit form (2/3) ── -->
        <div class="glass-card p-6 reveal rd2 lg:col-span-2">
            <div class="sec-eyebrow mb-1">Account</div>
            <h2 class="sec-heading mb-6">Account Information</h2>

            <form method="POST" action="" class="space-y-5">
                <!-- Name + Email -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" id="name" name="name" required
                               value="<?= htmlspecialchars($user["name"] ?? "") ?>"
                               class="glass-input" placeholder="Your full name">
                    </div>
                    <div>
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" required
                               value="<?= htmlspecialchars($user["email"] ?? "") ?>"
                               class="glass-input" placeholder="you@example.com">
                    </div>
                </div>

                <!-- Password -->
                <div style="padding:1.1rem 1.25rem;background:rgba(34,51,92,0.04);border-radius:.75rem;border:1px solid rgba(34,51,92,0.08);">
                    <p style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--slate);margin-bottom:1rem;">Change Password <span style="font-weight:500;text-transform:none;letter-spacing:0;color:#94a3b8;">(leave blank to keep current)</span></p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label for="new_password" class="form-label">New Password <span class="opt">(optional)</span></label>
                            <input type="password" id="new_password" name="new_password"
                                   class="glass-input" placeholder="Min. 6 characters" autocomplete="new-password">
                        </div>
                        <div>
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   class="glass-input" placeholder="Repeat new password" autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="save-btn">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Save Changes
                    </button>
                    <a href="user_dashboard.php" class="cancel-btn">Cancel</a>
                </div>
            </form>
        </div>

    </div>
</main>

<!-- ═══════════════ FOOTER ═══════════════ -->
<footer class="site-footer">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative z-10">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="footer-logo">Transport<span>Ops</span></div>
            <div class="flex flex-wrap gap-5 justify-center">
                <a href="user_dashboard.php" class="footer-link">Home</a>
                <a href="about.php"          class="footer-link">About</a>
                <a href="report.php"         class="footer-link">Submit Report</a>
                <a href="reports_map.php"    class="footer-link">Reports Map</a>
                <a href="routes.php"         class="footer-link">Routes</a>
            </div>
            <p style="color:#475569;font-size:.78rem;white-space:nowrap;">&copy; <?= date("Y") ?> Transport Ops</p>
        </div>
    </div>
</footer>

<script>
(function () {
    // Nav scroll
    var nav = document.getElementById("floatingNav");
    if (nav) {
        window.addEventListener("scroll", function () {
            if (window.scrollY > 20) { nav.classList.add("scrolled"); nav.style.top = "0.5rem"; }
            else                     { nav.classList.remove("scrolled"); nav.style.top = "1rem"; }
        }, { passive: true });
    }

    // Profile dropdown
    var btn  = document.getElementById("profileMenuButton");
    var menu = document.getElementById("profileMenu");
    if (btn && menu) {
        btn.addEventListener("click", function(e) { e.stopPropagation(); menu.classList.toggle("hidden"); });
        document.addEventListener("click", function() { if (menu) menu.classList.add("hidden"); });
    }

    // Mobile menu
    var brand  = document.getElementById("brandLink");
    var mobile = document.getElementById("mobileMenu");
    if (brand && mobile) {
        brand.addEventListener("click", function(e) {
            if (window.innerWidth < 768) { e.preventDefault(); mobile.classList.toggle("hidden"); }
        });
        document.addEventListener("click", function(ev) {
            if (mobile && !mobile.contains(ev.target) && ev.target !== brand) mobile.classList.add("hidden");
        });
    }

    // Scroll reveal
    var reveals = document.querySelectorAll(".reveal");
    if ("IntersectionObserver" in window) {
        var io = new IntersectionObserver(function(entries) {
            entries.forEach(function(e) { if (e.isIntersecting) { e.target.classList.add("visible"); io.unobserve(e.target); } });
        }, { threshold: 0.08 });
        reveals.forEach(function(el) { io.observe(el); });
    } else {
        reveals.forEach(function(el) { el.classList.add("visible"); });
    }
})();
</script>
</body>
</html>