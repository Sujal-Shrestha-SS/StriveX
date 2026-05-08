<?php
session_start();
require_once 'db.php';

// Fetch recent finished tournaments across all users
$recent = mysqli_query($conn,
    "SELECT t.id, t.name, t.created_at, u.username, p.name AS winner_name
     FROM tournaments t
     JOIN users u ON u.id = t.user_id
     LEFT JOIN winners w ON w.tournament_id = t.id
     LEFT JOIN players p ON p.id = w.player_id
     ORDER BY t.created_at DESC
     LIMIT 10");

$total_tournaments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tournaments"))['c'];
$total_players     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM players"))['c'];
$total_matches     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM fixtures WHERE played=1"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>StriveX - Tournament Manager</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header>
    <a class="logo" href="index.php">STRIVE<span>X</span></a>
    <nav>
        <a href="index.php" class="active">Home</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php" class="btn btn-primary btn-sm">Register</a>
        <?php endif; ?>
    </nav>
</header>

<div class="hero">
    <h1>STRIVE<span>X</span></h1>
    <p>Football Tournament Management</p>
    <div class="hero-cta">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="dashboard.php" class="btn btn-primary">My Dashboard</a>
            <a href="create_tournament.php" class="btn btn-secondary">New Tournament</a>
        <?php else: ?>
            <a href="register.php" class="btn btn-primary">Get Started</a>
            <a href="login.php" class="btn btn-secondary">Login</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">

    <!-- Stats -->
    <div class="grid-3 mt-3 mb-3">
        <div class="card text-center">
            <div style="font-family:'Barlow Condensed',sans-serif;font-size:3rem;font-weight:900;color:var(--accent)"><?= $total_tournaments ?></div>
            <div class="text-muted" style="text-transform:uppercase;font-size:0.8rem;letter-spacing:1px;">Tournaments</div>
        </div>
        <div class="card text-center">
            <div style="font-family:'Barlow Condensed',sans-serif;font-size:3rem;font-weight:900;color:var(--accent)"><?= $total_players ?></div>
            <div class="text-muted" style="text-transform:uppercase;font-size:0.8rem;letter-spacing:1px;">Players</div>
        </div>
        <div class="card text-center">
            <div style="font-family:'Barlow Condensed',sans-serif;font-size:3rem;font-weight:900;color:var(--accent)"><?= $total_matches ?></div>
            <div class="text-muted" style="text-transform:uppercase;font-size:0.8rem;letter-spacing:1px;">Matches Played</div>
        </div>
    </div>

    <!-- Recent Tournaments -->
    <div class="card">
        <div class="card-title">Recent Tournaments</div>
        <?php if (mysqli_num_rows($recent) === 0): ?>
            <p class="text-muted">No tournaments yet. <a href="register.php" class="text-accent">Register and create one!</a></p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Tournament</th>
                    <th>Host</th>
                    <th>Status</th>
                    <th>Winner</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php while ($t = mysqli_fetch_assoc($recent)): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                    <td class="text-muted"><?= htmlspecialchars($t['username']) ?></td>
                    <td>
                        <?php if ($t['winner_name']): ?>
                            <span class="text-gold">✓ Finished</span>
                        <?php else: ?>
                            <span class="text-muted">In Progress</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-gold"><?= $t['winner_name'] ? '🏆 ' . htmlspecialchars($t['winner_name']) : '—' ?></td>
                    <td class="text-muted"><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
                    <td><a href="view_tournament.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm">View</a></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Format Explainer -->
    <div class="card mt-3">
        <div class="card-title">How It Works</div>
        <div class="grid-2">
            <div>
                <h3 style="color:var(--accent);font-size:0.95rem;margin-bottom:0.5rem;">GROUP STAGE</h3>
                <p class="text-muted" style="font-size:0.9rem;line-height:1.6;">
                    Double round-robin — each player faces every other player twice (home & away). 
                </p>

                <p class="text-muted" style="font-size:0.85rem;">Top 4 advance to the knockout stage.</p>
                <p class="text-muted" style="font-size:0.85rem;">Win = 3pts · Draw = 1pt · Loss = 0pts</p>
            </div>
            <div>
                <h3 style="color:var(--accent);font-size:0.95rem;margin-bottom:0.5rem;">PAGE PLAYOFF KNOCKOUT</h3>
                <ul class="text-muted" style="font-size:0.9rem;line-height:1.8;padding-left:1.2rem;">
                    <li><strong>Semi Final 1:</strong> 1st vs 2nd</li>
                    <li><strong>Semi Final 2:</strong> 3rd vs 4th</li>
                    <li><strong>Qualifier:</strong> SF1 Loser vs SF2 Winner</li>
                    <li><strong>Final:</strong> SF1 Winner vs Qualifier Winner</li>
                </ul>
            </div>
        </div>
    </div>

</div>
</body>
</html>
