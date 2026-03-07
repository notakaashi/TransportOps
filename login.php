<?php
/**
 * Combined Auth Page - Login, Register & Admin Login
 * Three-panel sliding UI; all three forms handled in a single file.
 */
require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";

if (isset($_SESSION["user_id"])) {
    header(
        "Location: " .
            ($_SESSION["role"] === "Admin"
                ? "admin_dashboard.php"
                : "index.php"),
    );
    exit();
}

// State variables
$login_error = "";
$login_email = "";
$reg_error = "";
$reg_success = "";
$reg_name = "";
$reg_email = "";
$admin_error = "";
$admin_email = "";
$info_message = "";

// Active panel from URL
$panel_get = $_GET["panel"] ?? "login";
$active_panel = in_array($panel_get, ["register", "admin"])
    ? $panel_get
    : "login";

// Deactivated flag
if (isset($_GET["error"]) && $_GET["error"] === "deactivated") {
    $login_error =
        "Your account has been deactivated. Please contact an administrator.";
}

// Session flash message
if (isset($_SESSION["login_message"])) {
    $info_message = $_SESSION["login_message"];
    unset($_SESSION["login_message"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $form_type = $_POST["form_type"] ?? "login";

    // â”€â”€ USER LOGIN â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($form_type === "login") {
        $active_panel = "login";
        $login_email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        if (empty($login_email) || empty($password)) {
            $login_error = "Email and password are required.";
        } else {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare(
                    "SELECT id,name,email,password,role,is_active,profile_image
                     FROM users WHERE LOWER(email)=LOWER(?)",
                );
                $stmt->execute([trim($login_email)]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user["password"])) {
                    if ($user["role"] === "Admin") {
                        $login_error =
                            "Administrators must use the Admin Login.";
                    } elseif (!$user["is_active"]) {
                        $login_error =
                            "Your account has been deactivated. Please contact an administrator.";
                    } else {
                        regenerateSession();
                        $_SESSION["user_id"] = $user["id"];
                        $_SESSION["user_name"] = $user["name"];
                        $_SESSION["user_email"] = $user["email"];
                        $_SESSION["role"] = $user["role"];
                        $_SESSION["profile_image"] = $user["profile_image"];
                        $redirect =
                            $_SESSION["redirect_after_login"] ??
                            "user_dashboard.php";
                        unset($_SESSION["redirect_after_login"]);
                        header("Location: " . $redirect);
                        exit();
                    }
                } else {
                    $login_error = "Invalid email or password.";
                }
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $login_error = "Login failed. Please try again.";
            }
        }
    }

    // â”€â”€ REGISTER â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    elseif ($form_type === "register") {
        $active_panel = "register";
        $reg_name = trim($_POST["name"] ?? "");
        $reg_email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        $confirm_pw = $_POST["confirm_password"] ?? "";
        if (empty($reg_name) || empty($reg_email) || empty($password)) {
            $reg_error = "All fields are required.";
        } elseif (!filter_var($reg_email, FILTER_VALIDATE_EMAIL)) {
            $reg_error = "Invalid email format.";
        } elseif (strlen($password) < 6) {
            $reg_error = "Password must be at least 6 characters long.";
        } elseif ($password !== $confirm_pw) {
            $reg_error = "Passwords do not match.";
        } else {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
                $stmt->execute([$reg_email]);
                if ($stmt->fetch()) {
                    $reg_error = "Email already registered.";
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare(
                        "INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)",
                    );
                    $stmt->execute([
                        $reg_name,
                        $reg_email,
                        $hashed,
                        "Commuter",
                    ]);
                    $reg_success = "Account created! You can now sign in.";
                    $reg_name = "";
                    $reg_email = "";
                }
            } catch (PDOException $e) {
                error_log("Registration error: " . $e->getMessage());
                $reg_error = "Registration failed. Please try again.";
            }
        }
    }

    // â”€â”€ ADMIN LOGIN â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    elseif ($form_type === "admin") {
        $active_panel = "admin";
        $admin_email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        if (empty($admin_email) || empty($password)) {
            $admin_error = "Email and password are required.";
        } else {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare(
                    "SELECT id,name,email,password,role,is_active,profile_image
                     FROM users WHERE LOWER(email)=LOWER(?)",
                );
                $stmt->execute([trim($admin_email)]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user["password"])) {
                    if ($user["role"] !== "Admin") {
                        $admin_error =
                            "This portal is for administrators only.";
                    } elseif (!$user["is_active"]) {
                        $admin_error = "Your account has been deactivated.";
                    } else {
                        regenerateSession();
                        $_SESSION["user_id"] = $user["id"];
                        $_SESSION["user_name"] = $user["name"];
                        $_SESSION["user_email"] = $user["email"];
                        $_SESSION["role"] = $user["role"];
                        $_SESSION["profile_image"] = $user["profile_image"];
                        header("Location: admin_dashboard.php");
                        exit();
                    }
                } else {
                    $admin_error = "Invalid email or password.";
                }
            } catch (PDOException $e) {
                error_log("Admin login error: " . $e->getMessage());
                $admin_error = "Login failed. Please try again.";
            }
        }
    }
}

// Container class
$container_class = match ($active_panel) {
    "register" => " register-mode",
    "admin" => " admin-mode",
    default => "",
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TransportOps <?php if ($active_panel === "admin") {
    echo "- Admin Portal";
} elseif ($active_panel === "register") {
    echo "- Create Account";
} else {
    echo "- Sign In";
} ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy:#22335C; --navy2:#18243f; --steel:#5B7B99;
  --gold:#FBC061; --gold2:#e8a93e; --cream:#E8E1D8;
  --white:#ffffff; --error:#d94f4f; --success:#2e9e72;
  --dur:0.68s; --ease:cubic-bezier(0.65,0,0.35,1); --r:26px;
}
html,body{height:100%;font-family:'DM Sans',system-ui,sans-serif;background:var(--navy2);overflow-x:hidden}

/* â”€â”€ Animated background â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.bg-canvas{position:fixed;inset:0;z-index:0;overflow:hidden;
  background:linear-gradient(140deg,#111d38 0%,#1e2f52 45%,#28426b 100%)}
.bg-orb{position:absolute;border-radius:50%;filter:blur(80px);opacity:.22;
  animation:drift 20s ease-in-out infinite alternate}
.bg-orb:nth-child(1){width:520px;height:520px;background:var(--steel);top:-140px;left:-160px;animation-duration:18s}
.bg-orb:nth-child(2){width:400px;height:400px;background:var(--gold);bottom:-120px;right:-120px;
  animation-duration:24s;animation-direction:alternate-reverse}
.bg-orb:nth-child(3){width:260px;height:260px;background:var(--steel);top:55%;right:8%;animation-duration:14s}
.bg-grid{position:absolute;inset:0;
  background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),
                   linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);
  background-size:48px 48px}
@keyframes drift{from{transform:translate(0,0) scale(1)}to{transform:translate(40px,50px) scale(1.1)}}

/* â”€â”€ Page wrap â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.page-wrap{position:relative;z-index:1;min-height:100vh;display:flex;flex-direction:column;
  align-items:center;justify-content:center;padding:28px 16px}

/* â”€â”€ Auth container â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.auth-container{position:relative;width:100%;max-width:940px;min-height:610px;
  border-radius:var(--r);overflow:hidden;
  box-shadow:0 48px 96px rgba(0,0,0,.55),0 0 0 1px rgba(255,255,255,.06)}

/* â”€â”€ Forms area â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.forms-area{display:flex;width:100%;min-height:610px}

/* Left slot: register form */
.left-slot{width:50%;min-height:610px;display:flex;flex-direction:column;
  justify-content:center;background:#f5f2ee;overflow:hidden}

/* Right slot: login + admin forms stacked, clipped */
.right-slot{width:50%;min-height:610px;position:relative;overflow:hidden;
  background:#f5f2ee}

.form-panel{width:100%;min-height:610px;display:flex;flex-direction:column;
  justify-content:center;padding:54px 48px;overflow-y:auto}

/* â”€â”€ Register form visibility (left slot) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.form-register{opacity:0;pointer-events:none;transition:opacity var(--dur) var(--ease)}
.auth-container.register-mode .form-register{opacity:1;pointer-events:auto}

/* â”€â”€ Right slot: login vs admin â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
/* Both forms are absolutely stacked inside .right-slot */
.form-login,.form-admin{position:absolute;top:0;left:0;width:100%;
  transition:transform var(--dur) var(--ease), opacity var(--dur) var(--ease)}

/* Default (login visible, admin off to the right) */
.form-login {transform:translateX(0);   opacity:1;pointer-events:auto}
.form-admin {transform:translateX(100%);opacity:0;pointer-events:none}

/* Admin mode: login slides out left, admin slides in from right */
.auth-container.admin-mode .form-login {transform:translateX(-100%);opacity:0;pointer-events:none}
.auth-container.admin-mode .form-admin {transform:translateX(0);    opacity:1;pointer-events:auto}

/* â”€â”€ Overlay sliding panel â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.overlay-container{position:absolute;top:0;left:0;width:50%;height:100%;z-index:20;
  overflow:hidden;transition:transform var(--dur) var(--ease);will-change:transform}
/* Register: overlay slides right */
.auth-container.register-mode .overlay-container{transform:translateX(100%)}
/* Admin: overlay stays left (same as default) */

.overlay-track{display:flex;width:200%;height:100%;
  transition:transform var(--dur) var(--ease);will-change:transform}
.auth-container.register-mode .overlay-track{transform:translateX(-50%)}

.overlay-panel{width:50%;height:100%;display:flex;flex-direction:column;align-items:center;
  justify-content:center;padding:54px 44px;text-align:center;color:var(--white);
  position:relative;overflow:hidden;
  background:linear-gradient(155deg,var(--navy) 0%,#2a4468 55%,var(--steel) 100%)}
.overlay-panel::before{content:'';position:absolute;inset:0;pointer-events:none;
  background:repeating-linear-gradient(-52deg,transparent,transparent 55px,
    rgba(255,255,255,.028) 55px,rgba(255,255,255,.028) 56px)}
.overlay-panel::after{content:'';position:absolute;width:380px;height:380px;border-radius:50%;
  border:1px solid rgba(251,192,97,.15);bottom:-140px;right:-110px;pointer-events:none}
.ov-ring{position:absolute;width:220px;height:220px;border-radius:50%;
  border:1px solid rgba(255,255,255,.07);top:-70px;left:-70px;pointer-events:none}

.ov-icon{width:64px;height:64px;border-radius:18px;
  background:rgba(251,192,97,.12);border:1.5px solid rgba(251,192,97,.35);
  display:flex;align-items:center;justify-content:center;margin-bottom:26px;
  backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
.ov-icon svg{width:32px;height:32px}

.overlay-panel h2{font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:800;
  line-height:1.15;margin-bottom:14px;letter-spacing:-.01em}
.overlay-panel p{font-size:.9rem;line-height:1.7;color:rgba(255,255,255,.68);
  margin-bottom:30px;max-width:240px}

.ov-pills{display:flex;flex-direction:column;gap:9px;margin-bottom:32px;width:100%;max-width:250px}
.ov-pill{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.10);border-radius:100px;padding:8px 16px;
  font-size:.8rem;color:rgba(255,255,255,.8);
  backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px)}
.ov-dot{width:6px;height:6px;border-radius:50%;background:var(--gold);flex-shrink:0}

.ov-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 32px;
  border:2px solid var(--gold);background:transparent;color:var(--gold);
  border-radius:100px;font-family:'DM Sans',sans-serif;font-size:.9rem;font-weight:600;
  letter-spacing:.03em;cursor:pointer;
  transition:background .22s ease,color .22s ease,transform .18s ease,box-shadow .22s ease}
.ov-btn:hover{background:var(--gold);color:var(--navy);transform:translateY(-2px);
  box-shadow:0 8px 24px rgba(251,192,97,.28)}
.ov-btn:active{transform:translateY(0)}
.ov-btn svg{width:15px;height:15px;transition:transform .2s ease;flex-shrink:0}
.ov-btn:hover svg{transform:translateX(3px)}

/* â”€â”€ Form content styles â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.form-brand{display:flex;align-items:center;gap:8px;margin-bottom:20px}
.form-brand-dot{width:9px;height:9px;border-radius:50%;background:var(--gold)}
.form-brand-dot.admin-dot{background:var(--steel)}
.form-brand-label{font-family:'Syne',sans-serif;font-size:.74rem;font-weight:700;
  letter-spacing:.13em;text-transform:uppercase;color:var(--steel)}
.form-title{font-family:'Syne',sans-serif;font-size:1.7rem;font-weight:800;
  color:var(--navy);line-height:1.18;letter-spacing:-.01em;margin-bottom:6px}
.form-sub{font-size:.855rem;color:#7585a0;line-height:1.55;margin-bottom:26px}

/* Admin badge on title */
.admin-badge{display:inline-flex;align-items:center;gap:6px;
  background:rgba(34,51,92,.08);border:1px solid rgba(34,51,92,.15);
  border-radius:100px;padding:4px 12px;font-size:.72rem;font-weight:700;
  color:var(--navy);letter-spacing:.08em;text-transform:uppercase;margin-bottom:14px}
.admin-badge svg{width:13px;height:13px}

/* Alerts */
.alert{display:flex;align-items:flex-start;gap:9px;padding:11px 13px;
  border-radius:11px;font-size:.835rem;line-height:1.45;margin-bottom:18px}
.alert svg{width:16px;height:16px;flex-shrink:0;margin-top:1px}
.alert-error  {background:#fef0f0;border:1px solid #f5c5c5;color:#b93030}
.alert-success{background:#effaf5;border:1px solid #a3dfc4;color:#1e7a52}
.alert-info   {background:#eff4fb;border:1px solid #b5ccec;color:#2a4d80}

/* Field groups */
.field-group{display:flex;flex-direction:column;gap:14px;margin-bottom:20px}
.field{display:flex;flex-direction:column;gap:5px}
.field label{font-size:.8rem;font-weight:600;color:#4e5e77;letter-spacing:.01em}
.field-wrap{position:relative}
.field-wrap .fi{position:absolute;left:13px;top:50%;transform:translateY(-50%);
  width:16px;height:16px;color:#a0aec0;pointer-events:none;transition:color .2s}
.field-wrap:focus-within .fi{color:var(--steel)}
.field input{width:100%;padding:11px 40px 11px 38px;border:1.5px solid #dce3ef;
  border-radius:11px;font-family:'DM Sans',sans-serif;font-size:.88rem;
  color:var(--navy);background:var(--white);outline:none;
  transition:border-color .2s ease,box-shadow .2s ease;
  -webkit-appearance:none;appearance:none}
.field input:focus{border-color:var(--steel);box-shadow:0 0 0 3px rgba(91,123,153,.13)}
.field input::placeholder{color:#b0bdd0}

/* Admin form inputs get a slightly darker focus ring */
.admin-input:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(34,51,92,.12)}

/* Password toggle */
.pw-btn{position:absolute;right:11px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;color:#a0aec0;padding:3px;
  display:flex;align-items:center;transition:color .2s;line-height:0}
.pw-btn:hover{color:var(--steel)}
.pw-btn svg{width:16px;height:16px}

/* Primary submit button */
.btn-submit{width:100%;padding:13px;background:var(--navy);color:var(--white);
  border:none;border-radius:11px;font-family:'DM Sans',sans-serif;font-size:.92rem;
  font-weight:600;cursor:pointer;letter-spacing:.02em;min-height:48px;
  transition:background .22s ease,transform .15s ease,box-shadow .22s ease}
.btn-submit:hover{background:var(--steel);transform:translateY(-1px);
  box-shadow:0 6px 20px rgba(34,51,92,.22)}
.btn-submit:active{transform:translateY(0);box-shadow:none}

/* Admin submit button - slightly darker */
.btn-submit-admin{background:var(--navy)}
.btn-submit-admin:hover{background:#1a2847}

/* Switch-panel button (Register / Sign In / Admin Login) */
.btn-switch{width:100%;padding:12px;margin-top:10px;background:transparent;
  border:1.5px solid #dce3ef;border-radius:11px;font-family:'DM Sans',sans-serif;
  font-size:.88rem;font-weight:600;color:#7585a0;cursor:pointer;letter-spacing:.02em;
  transition:border-color .22s ease,color .22s ease,background .22s ease,transform .15s ease}
.btn-switch:hover{border-color:var(--steel);color:var(--navy);
  background:rgba(91,123,153,.07);transform:translateY(-1px)}
.btn-switch:active{transform:translateY(0)}

/* Footer */
.form-footer{margin-top:18px;text-align:center}
.form-footer p{font-size:.8rem;color:#8a98ac;line-height:1.9}
.form-footer a{color:var(--steel);font-weight:600;text-decoration:none;transition:color .2s}
.form-footer a:hover{color:var(--navy)}

/* Page-level links */
.page-links{margin-top:22px;display:flex;gap:22px;justify-content:center;flex-wrap:wrap}
.page-links a{font-size:.76rem;color:rgba(255,255,255,.32);text-decoration:none;
  transition:color .2s;letter-spacing:.02em}
.page-links a:hover{color:rgba(255,255,255,.65)}

/* â”€â”€ Mobile (<=680px) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
@media (max-width:680px){
  .auth-container{min-height:unset;border-radius:20px}
  .forms-area{flex-direction:column;min-height:unset}
  .left-slot,.right-slot{width:100%;min-height:unset}
  .right-slot{overflow:visible}
  .form-panel{min-height:unset;padding:38px 26px}
  /* Show only active panel */
  .left-slot .form-register{display:none;opacity:1;pointer-events:auto}
  .auth-container.register-mode .left-slot .form-register{display:flex}
  .form-login{position:static;transform:none!important;opacity:1!important;
    pointer-events:auto!important;display:flex}
  .form-admin{position:static;transform:none!important;opacity:0!important;
    pointer-events:none!important;display:none}
  .auth-container.admin-mode .form-login{display:none!important;opacity:0!important;pointer-events:none!important}
  .auth-container.admin-mode .form-admin{display:flex!important;opacity:1!important;pointer-events:auto!important}
  .auth-container.register-mode .form-login{display:none!important}
  .auth-container.register-mode .right-slot{display:none}
  .overlay-container{display:none}
}
</style>
</head>
<body>

<!-- Animated background -->
<div class="bg-canvas">
  <div class="bg-orb"></div>
  <div class="bg-orb"></div>
  <div class="bg-orb"></div>
  <div class="bg-grid"></div>
</div>

<div class="page-wrap">

  <div id="authContainer" class="auth-container<?php echo $container_class; ?>">

    <!-- FORMS AREA -->
    <div class="forms-area">

      <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           LEFT SLOT â€” Register Form
      â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
      <div class="left-slot">
        <div class="form-panel form-register">

          <div class="form-brand">
            <span class="form-brand-dot"></span>
            <span class="form-brand-label">TransportOps</span>
          </div>
          <h2 class="form-title">Create Account</h2>
          <p class="form-sub">Join the network. Help fellow commuters with live route updates and delay reports.</p>

          <?php if ($reg_error): ?>
          <div class="alert alert-error">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php echo htmlspecialchars($reg_error); ?>
          </div>
          <?php endif; ?>

          <?php if ($reg_success): ?>
          <div class="alert alert-success">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php echo htmlspecialchars($reg_success); ?>
            &nbsp;<a href="login.php" style="font-weight:600;color:var(--steel)">Sign in now &rarr;</a>
          </div>
          <?php endif; ?>

          <form method="POST" action="" novalidate>
            <input type="hidden" name="form_type" value="register">
            <div class="field-group">

              <div class="field">
                <label for="reg_name">Full Name</label>
                <div class="field-wrap">
                  <input type="text" id="reg_name" name="name" required autocomplete="name"
                         placeholder="Juan dela Cruz"
                         value="<?php echo htmlspecialchars($reg_name); ?>">
                  <svg class="fi" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                  </svg>
                </div>
              </div>

              <div class="field">
                <label for="reg_email">Email Address</label>
                <div class="field-wrap">
                  <input type="email" id="reg_email" name="email" required autocomplete="email"
                         placeholder="you@example.com"
                         value="<?php echo htmlspecialchars($reg_email); ?>">
                  <svg class="fi" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                </div>
              </div>

              <div class="field">
                <label for="reg_pw">Password</label>
                <div class="field-wrap">
                  <input type="password" id="reg_pw" name="password" required minlength="6"
                         placeholder="At least 6 characters" autocomplete="new-password">
                  <svg class="fi" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                  </svg>
                  <button type="button" class="pw-btn" onclick="togglePw('reg_pw',this)" aria-label="Toggle password">
                    <svg class="eye-show" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg class="eye-hide" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.066 5.717m0 0L21 21"/>
                    </svg>
                  </button>
                </div>
              </div>

              <div class="field">
                <label for="reg_cpw">Confirm Password</label>
                <div class="field-wrap">
                  <input type="password" id="reg_cpw" name="confirm_password" required minlength="6"
                         placeholder="Repeat your password" autocomplete="new-password">
                  <svg class="fi" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <button type="button" class="pw-btn" onclick="togglePw('reg_cpw',this)" aria-label="Toggle confirm password">
                    <svg class="eye-show" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg class="eye-hide" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.066 5.717m0 0L21 21"/>
                    </svg>
                  </button>
                </div>
              </div>

            </div><!-- /field-group -->
            <button type="submit" class="btn-submit">Create Account</button>
          </form>

          <div class="form-footer">
            <button type="button" class="btn-switch" onclick="switchPanel('login')">
              Already have an account? &nbsp;<strong>Sign In</strong>
            </button>
            <p style="margin-top:10px"><a href="index.php">&larr; Back to Home</a></p>
          </div>

        </div><!-- /form-register -->
      </div><!-- /left-slot -->

      <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           RIGHT SLOT â€” Login Form + Admin Form (stacked)
      â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
      <div class="right-slot">

        <!-- â”€â”€ USER LOGIN FORM â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
        <div class="form-panel form-login">

          <div class="form-brand">
            <span class="form-brand-dot"></span>
            <span class="form-brand-label">TransportOps</span>
          </div>
          <h2 class="form-title">Welcome Back</h2>
          <p class="form-sub">Sign in to track live routes, check crowding levels, and report transit delays.</p>

          <?php if ($info_message): ?>
          <div class="alert alert-info">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php echo htmlspecialchars($info_message); ?>
          </div>
          <?php endif; ?>

          <?php if ($login_error): ?>
          <div class="alert alert-error">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php echo htmlspecialchars($login_error); ?>
          </div>
          <?php endif; ?>

          <form method="POST" action="" novalidate>
            <input type="hidden" name="form_type" value="login">
            <div class="field-group">

              <div class="field">
                <label for="login_email">Email Address</label>
                <div class="field-wrap">
                  <input type="email" id="login_email" name="email" required autocomplete="email"
                         placeholder="you@example.com"
                         value="<?php echo htmlspecialchars($login_email); ?>">
                  <svg class="fi" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                </div>
              </div>

              <div class="field">
                <label for="login_pw">Password</label>
                <div class="field-wrap">
                  <input type="password" id="login_pw" name="password" required
                         placeholder="Enter your password" autocomplete="current-password">
                  <svg class="fi" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                  </svg>
                  <button type="button" class="pw-btn" onclick="togglePw('login_pw',this)" aria-label="Toggle password">
                    <svg class="eye-show" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg class="eye-hide" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.066 5.717m0 0L21 21"/>
                    </svg>
                  </button>
                </div>
              </div>

            </div><!-- /field-group -->
            <button type="submit" class="btn-submit">Sign In</button>
          </form>

          <div class="form-footer">
            <p>
              <a href="index.php">&larr; Back to Home</a>
            </p>
            <button type="button" class="btn-switch" onclick="switchPanel('register')">
              Don't have an account? &nbsp;<strong>Register</strong>
            </button>
            <button type="button" class="btn-switch" onclick="switchPanel('admin')" style="margin-top:8px">
              Administrator? &nbsp;<strong>Admin Login</strong>
            </button>
          </div>

        </div><!-- /form-login -->

        <!-- â”€â”€ ADMIN LOGIN FORM â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
        <div class="form-panel form-admin">

          <div class="form-brand">
            <span class="form-brand-dot admin-dot"></span>
            <span class="form-brand-label">TransportOps</span>
          </div>

          <div class="admin-badge">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            Admin Portal
          </div>

          <h2 class="form-title">Admin Sign In</h2>
          <p class="form-sub">Restricted access. Authorized administrators only.</p>

          <?php if ($admin_error): ?>
          <div class="alert alert-error">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php echo htmlspecialchars($admin_error); ?>
          </div>
          <?php endif; ?>

          <form method="POST" action="" novalidate>
            <input type="hidden" name="form_type" value="admin">
            <div class="field-group">

              <div class="field">
                <label for="admin_email">Admin Email</label>
                <div class="field-wrap">
                  <input type="email" id="admin_email" name="email" required autocomplete="email"
                         class="admin-input"
                         placeholder="admin@example.com"
                         value="<?php echo htmlspecialchars($admin_email); ?>">
                  <svg class="fi" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                </div>
              </div>

              <div class="field">
                <label for="admin_pw">Password</label>
                <div class="field-wrap">
                  <input type="password" id="admin_pw" name="password" required
                         class="admin-input"
                         placeholder="Enter admin password" autocomplete="current-password">
                  <svg class="fi" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                  </svg>
                  <button type="button" class="pw-btn" onclick="togglePw('admin_pw',this)" aria-label="Toggle password">
                    <svg class="eye-show" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg class="eye-hide" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.066 5.717m0 0L21 21"/>
                    </svg>
                  </button>
                </div>
              </div>

            </div><!-- /field-group -->
            <button type="submit" class="btn-submit btn-submit-admin">Access Admin Panel</button>
          </form>

          <div class="form-footer">
            <button type="button" class="btn-switch" onclick="switchPanel('login')">
              &larr; &nbsp;<strong>Back to User Login</strong>
            </button>
            <p style="margin-top:10px"><a href="index.php">&larr; Back to Home</a></p>
          </div>

        </div><!-- /form-admin -->

      </div><!-- /right-slot -->

    </div><!-- /forms-area -->

    <!-- OVERLAY SLIDING PANEL -->
    <div class="overlay-container">
      <div class="overlay-track">

        <!-- Left sub-panel: shown in register-mode (overlay on RIGHT) -->
        <div class="overlay-panel overlay-left">
          <span class="ov-ring"></span>
          <div class="ov-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--gold)">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
          </div>
          <h2>Already a Member?</h2>
          <p>Sign in to your TransportOps account and get back on track with real-time commute data.</p>
          <div class="ov-pills">
            <div class="ov-pill"><span class="ov-dot"></span>View live crowd levels</div>
            <div class="ov-pill"><span class="ov-dot"></span>Track route delays</div>
            <div class="ov-pill"><span class="ov-dot"></span>Submit transit reports</div>
          </div>
          <button class="ov-btn" onclick="switchPanel('login')">
            Sign In
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
            </svg>
          </button>
        </div><!-- /overlay-left -->

        <!-- Right sub-panel: shown in login-mode and admin-mode (overlay on LEFT) -->
        <div class="overlay-panel overlay-right">
          <span class="ov-ring"></span>
          <div class="ov-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--gold)">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
          </div>
          <h2>New to TransportOps?</h2>
          <p>Create a free account and help your community navigate smarter with crowdsourced transit data.</p>
          <div class="ov-pills">
            <div class="ov-pill"><span class="ov-dot"></span>Free commuter account</div>
            <div class="ov-pill"><span class="ov-dot"></span>Report delays &amp; crowding</div>
            <div class="ov-pill"><span class="ov-dot"></span>Build your trust score</div>
          </div>
          <button class="ov-btn" onclick="switchPanel('register')">
            Create Account
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
            </svg>
          </button>
        </div><!-- /overlay-right -->

      </div><!-- /overlay-track -->
    </div><!-- /overlay-container -->

  </div><!-- /auth-container -->

  <!-- Page-level links -->
  <div class="page-links">
    <a href="index.php">Home</a>
    <a href="about.php">About</a>
  </div>

</div><!-- /page-wrap -->

<script>
const container = document.getElementById('authContainer');

function switchPanel(mode) {
  container.classList.remove('register-mode', 'admin-mode');
  if (mode === 'register') {
    container.classList.add('register-mode');
    history.replaceState(null, '', '?panel=register');
  } else if (mode === 'admin') {
    container.classList.add('admin-mode');
    history.replaceState(null, '', '?panel=admin');
  } else {
    history.replaceState(null, '', '?panel=login');
  }
}

function togglePw(fieldId, btn) {
  const input   = document.getElementById(fieldId);
  const showSvg = btn.querySelector('.eye-show');
  const hideSvg = btn.querySelector('.eye-hide');
  if (input.type === 'password') {
    input.type = 'text';
    showSvg.style.display = 'none';
    hideSvg.style.display = '';
  } else {
    input.type = 'password';
    showSvg.style.display = '';
    hideSvg.style.display = 'none';
  }
}
</script>
</body>
</html>
