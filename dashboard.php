<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

$uid = (int)$_SESSION['user_id'];

// Fetch only THIS user's tournaments
$tournaments = mysqli_query($conn,
    "SELECT t.*,
            COUNT(DISTINCT tp.player_id) AS player_count,
            COUNT(DISTINCT CASE WHEN f.round_type = 'group' AND f.played = 1 THEN f.id END) AS played_matches,
            COUNT(DISTINCT CASE WHEN f.round_type = 'group' THEN f.id END) AS total_group,
            w_name.name AS winner_name
     FROM tournaments t
     LEFT JOIN tournament_players tp ON tp.tournament_id = t.id
     LEFT JOIN fixtures f  ON f.tournament_id = t.id
     LEFT JOIN winners win ON win.tournament_id = t.id
     LEFT JOIN players w_name ON w_name.id = win.player_id
     WHERE t.user_id = $uid
     GROUP BY t.id
     ORDER BY t.created_at DESC"
);

$player_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM players WHERE user_id = $uid"
))['c'];

$msg = '';
if (isset($_GET['deleted'])) $msg = "Tournament deleted.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>StriveX — Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header>
    <a class="logo" href="index.php">STRIVE<span>X</span></a>
    <nav>
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="players.php">My Players</a>
        <a href="create_tournament.php">New Tournament</a>
        <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
    </nav>
</header>

<div class="container">
    <div class="flex-between">
        <div>
            <div class="page-title">Dashboard</div>
            <div class="page-subtitle">Welcome, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></div>
        </div>
        <div style="display:flex;gap:0.75rem;">
            <a href="players.php" class="btn btn-secondary">My Players</a>
            <a href="create_tournament.php" class="btn btn-primary">+ New Tournament</a>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

    <div class="grid-2 mt-3">
        <div class="card text-center">
            <div style="font-family:'Barlow Condensed',sans-serif;font-size:3rem;font-weight:900;color:var(--accent)"><?= $player_count ?></div>
            <div class="text-muted" style="text-transform:uppercase;font-size:0.8rem;letter-spacing:1px;">My Players</div>
        </div>
        <div class="card text-center">
            <div style="font-family:'Barlow Condensed',sans-serif;font-size:3rem;font-weight:900;color:var(--accent)"><?= mysqli_num_rows($tournaments) ?></div>
            <div class="text-muted" style="text-transform:uppercase;font-size:0.8rem;letter-spacing:1px;">My Tournaments</div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-title">My Tournaments</div>
        <?php if (mysqli_num_rows($tournaments) === 0): ?>
            <p class="text-muted">No tournaments yet. <a href="create_tournament.php" class="text-accent">Create one!</a></p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Players</th>
                    <th>Group Matches</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($t = mysqli_fetch_assoc($tournaments)): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($t['name']) ?></strong>
                        <?php if ($t['winner_name']): ?>
                            <span class="text-gold" style="font-size:0.8rem;"> 🏆 <?= htmlspecialchars($t['winner_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($t['status'] === 'finished'): ?>
                            <span class="text-gold">✓ Finished</span>
                        <?php elseif ($t['status'] === 'knockout'): ?>
                            <span class="text-accent">Knockout</span>
                        <?php else: ?>
                            <span class="text-muted">Group Stage</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $t['player_count'] ?></td>
                    <td><?= $t['played_matches'] ?>/<?= $t['total_group'] ?></td>
                    <td class="text-muted"><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
                    <td>
                        <a href="manage_tournament.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm">Manage</a>
                        <a href="view_tournament.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
