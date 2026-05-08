<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

$uid = (int)$_SESSION['user_id'];
$msg = '';
$err = '';

// Add player
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $name = trim($_POST['name'] ?? '');
        $club = trim($_POST['club'] ?? '');
        if ($name === '') {
            $err = "Player name is required.";
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO players (user_id, name, club) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iss", $uid, $name, $club);
            mysqli_stmt_execute($stmt);
            $msg = "Player added.";
        }
    }

    if ($_POST['action'] === 'edit') {
        $pid  = (int)$_POST['player_id'];
        $name = trim($_POST['name'] ?? '');
        $club = trim($_POST['club'] ?? '');
        if ($name === '') {
            $err = "Player name is required.";
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE players SET name=?, club=? WHERE id=? AND user_id=?");
            mysqli_stmt_bind_param($stmt, "ssii", $name, $club, $pid, $uid);
            mysqli_stmt_execute($stmt);
            $msg = "Player updated.";
        }
    }

    if ($_POST['action'] === 'delete') {
        $pid = (int)$_POST['player_id'];
        mysqli_query($conn, "DELETE FROM players WHERE id=$pid AND user_id=$uid");
        $msg = "Player deleted.";
    }
}

// Load players
$players = mysqli_query($conn,
    "SELECT p.*, COUNT(DISTINCT w.id) AS wins
     FROM players p
     LEFT JOIN winners w ON w.player_id = p.id
     WHERE p.user_id = $uid
     GROUP BY p.id ORDER BY p.name"
);

$edit_player = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $res = mysqli_query($conn, "SELECT * FROM players WHERE id=$eid AND user_id=$uid");
    $edit_player = mysqli_fetch_assoc($res);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>StriveX — My Players</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header>
    <a class="logo" href="index.php">STRIVE<span>X</span></a>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="players.php" class="active">My Players</a>
        <a href="create_tournament.php">New Tournament</a>
        <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
    </nav>
</header>

<div class="container">
    <div class="page-title">My Players</div>
    <div class="page-subtitle">Manage the players in your tournaments</div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= $err ?></div><?php endif; ?>

    <div class="grid-2">

        <!-- Add / Edit Form -->
        <div class="card">
            <div class="card-title"><?= $edit_player ? 'Edit Player' : 'Add Player' ?></div>
            <form method="POST">
                <input type="hidden" name="action" value="<?= $edit_player ? 'edit' : 'add' ?>">
                <?php if ($edit_player): ?>
                    <input type="hidden" name="player_id" value="<?= $edit_player['id'] ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label>Player Name *</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($edit_player['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Club / Team</label>
                    <input type="text" name="club" value="<?= htmlspecialchars($edit_player['club'] ?? '') ?>" placeholder="e.g. Barcelona">
                </div>
                <div style="display:flex;gap:0.5rem;">
                    <button type="submit" class="btn btn-primary"><?= $edit_player ? 'Update Player' : 'Add Player' ?></button>
                    <?php if ($edit_player): ?>
                        <a href="players.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Players List -->
        <div class="card">
            <div class="card-title">All My Players (<?= mysqli_num_rows($players) ?>)</div>
            <?php if (mysqli_num_rows($players) === 0): ?>
                <p class="text-muted">No players yet. Add your first player!</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Club</th>
                        <th>🏆</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($p = mysqli_fetch_assoc($players)): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                        <td class="text-muted"><?= htmlspecialchars($p['club'] ?: '—') ?></td>
                        <td class="text-gold"><?= $p['wins'] ?: '—' ?></td>
                        <td>
                            <a href="players.php?edit=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this player?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="player_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Del</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>
