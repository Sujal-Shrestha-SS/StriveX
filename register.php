<?php
session_start();
require_once 'db.php';

// Already logged in? Go to dashboard.
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    if ($username === '' || $password === '') {
        $error = "Username and password are required.";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check if username already taken
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "That username is already taken. Please choose another.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt2  = mysqli_prepare($conn, "INSERT INTO users (username, password) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt2, "ss", $username, $hashed);
            mysqli_stmt_execute($stmt2);

            $success = "Account created! You can now <a href='login.php'>log in</a>.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>StriveX — Register</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header>
    <a class="logo" href="index.php">STRIVE<span>X</span></a>
    <nav>
        <a href="index.php">← Home</a>
        <a href="login.php">Login</a>
    </nav>
</header>

<div class="login-wrap">
    <a class="logo" href="index.php">STRIVE<span>X</span></a>
    <div class="card">
        <div class="card-title">Create Account</div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php else: ?>
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
                <small class="text-muted">Minimum 6 characters</small>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Register</button>
        </form>
        <p class="text-muted mt-2" style="font-size:0.85rem;text-align:center;">
            Already have an account? <a href="login.php" class="text-accent">Log in</a>
        </p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
