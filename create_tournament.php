<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

$uid = (int)$_SESSION['user_id'];
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tournament'])) {
    $tname   = trim($_POST['tournament_name'] ?? '');
    $sel_pids = $_POST['players'] ?? [];

    if ($tname === '') {
        $err = "Tournament name is required.";
    } elseif (count($sel_pids) < 5) {
        $err = "You must select at least 5 players for this format.";
    } else {
        // Create tournament
        $stmt = mysqli_prepare($conn, "INSERT INTO tournaments (user_id, name, status) VALUES (?, ?, 'group')");
        mysqli_stmt_bind_param($stmt, "is", $uid, $tname);
        mysqli_stmt_execute($stmt);
        $tid = mysqli_insert_id($conn);

        // Add players to tournament
        $pids = array_map('intval', $sel_pids);
        foreach ($pids as $pid) {
            mysqli_query($conn, "INSERT INTO tournament_players (tournament_id, player_id) VALUES ($tid, $pid)");
        }

        // Generate double round-robin group fixtures
        // Every player plays every other player twice (home and away)
        $fixtures = [];
        for ($i = 0; $i < count($pids); $i++) {
            for ($j = 0; $j < count($pids); $j++) {
                if ($i !== $j) {
                    $fixtures[] = [$pids[$i], $pids[$j]];
                }
            }
        }
        shuffle($fixtures);

        foreach ($fixtures as $fx) {
            mysqli_query($conn, "INSERT INTO fixtures (tournament_id, home_player, away_player, round_type, played)
                VALUES ($tid, {$fx[0]}, {$fx[1]}, 'group', 0)");
        }

        header("Location: manage_tournament.php?id=$tid&created=1");
        exit;
    }
}

// Load user's players
$players = mysqli_query($conn, "SELECT * FROM players WHERE user_id=$uid ORDER BY name");
$player_count = mysqli_num_rows($players);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>StriveX — Create Tournament</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header>
    <a class="logo" href="index.php">STRIVE<span>X</span></a>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="players.php">My Players</a>
        <a href="create_tournament.php" class="active">New Tournament</a>
        <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
    </nav>
</header>

<div class="container">
    <div class="page-title">Create Tournament</div>
    <div class="page-subtitle">10-player double round-robin format</div>

    <?php if ($err): ?><div class="alert alert-error"><?= $err ?></div><?php endif; ?>

    <?php if ($player_count < 5): ?>
        <div class="alert alert-info">
            You need at least <strong>5 players</strong> to create a tournament.<br>
            You currently have <strong><?= $player_count ?></strong> player(s).
            <a href="players.php" class="text-accent">Add more players →</a>
        </div>
    <?php else: ?>

    <div class="card" style="max-width:640px;">
        <div class="card-title">Tournament Details</div>
        <form method="POST">

            <div class="form-group">
                <label>Tournament Name *</label>
                <input type="text" name="tournament_name" placeholder="e.g. StriveX Season 3" required style="width:100%">
            </div>

            <div class="form-group">
                <label>Select Players * <span class="text-muted" style="font-size:0.75rem;text-transform:none;">(minimum 5, select as many as you like)</span></label>
                <div id="player-list" style="background:var(--bg3);border:1px solid var(--border);border-radius:4px;padding:12px;max-height:340px;overflow-y:auto;">
                    <?php mysqli_data_seek($players, 0); while ($p = mysqli_fetch_assoc($players)): ?>
                    <label style="display:flex;align-items:center;gap:8px;padding:6px 0;cursor:pointer;text-transform:none;font-size:0.9rem;color:var(--text);font-weight:normal;">
                        <input type="checkbox" name="players[]" value="<?= $p['id'] ?>" style="width:auto;margin:0;" onchange="countSelected()">
                        <strong><?= htmlspecialchars($p['name']) ?></strong>
                        <?php if ($p['club']): ?>
                            <span class="text-muted">(<?= htmlspecialchars($p['club']) ?>)</span>
                        <?php endif; ?>
                    </label>
                    <?php endwhile; ?>
                </div>
                <div id="sel-count" class="text-muted mt-1" style="font-size:0.85rem;">0 selected (need at least 5)</div>
            </div>

            <div class="alert alert-info">
                <strong>Tournament Format — Page Playoff System:</strong><br>
                • <strong>Group stage:</strong> Double round-robin (every player vs every other × 2)<br>
                • <strong>Top 4</strong> always qualify for knockout regardless of player count<br>
                • <strong>Semi Final 1:</strong> 1st vs 2nd<br>
                • <strong>Semi Final 2:</strong> 3rd vs 4th<br>
                • <strong>Qualifier:</strong> Loser of SF1 vs Winner of SF2<br>
                • <strong>Final:</strong> Winner of SF1 vs Winner of Qualifier
            </div>

            <button type="submit" name="create_tournament" class="btn btn-primary" id="create-btn" disabled>
                Generate Tournament + Fixtures
            </button>
        </form>
    </div>

    <?php endif; ?>
</div>

<script>
function countSelected() {
    var checked = document.querySelectorAll('input[name="players[]"]:checked').length;
    var label = checked < 5
        ? checked + ' selected (need at least 5)'
        : checked + ' selected ✓';
    document.getElementById('sel-count').textContent = label;
    document.getElementById('create-btn').disabled = (checked < 5);
}
</script>

</body>
</html>
