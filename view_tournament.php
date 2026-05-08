<?php
session_start();
require_once 'db.php';

$tid = (int)($_GET['id'] ?? 0);
if (!$tid) { header("Location: index.php"); exit; }

$t_res     = mysqli_query($conn, "SELECT t.*, u.username FROM tournaments t JOIN users u ON u.id = t.user_id WHERE t.id=$tid");
$tournament = mysqli_fetch_assoc($t_res);
if (!$tournament) { header("Location: index.php"); exit; }

$tab = $_GET['tab'] ?? 'standings';

// ── STANDINGS HELPER ──────────────────────────────────────────────────────────
function getStandings($conn, $tid) {
    $res = mysqli_query($conn,
        "SELECT p.id, p.name, p.club FROM players p
         JOIN tournament_players tp ON tp.player_id = p.id
         WHERE tp.tournament_id = $tid");
    $standings = [];
    while ($p = mysqli_fetch_assoc($res)) {
        $pid   = $p['id'];
        $stats = ['player_id' => $pid, 'name' => $p['name'], 'club' => $p['club'],
                  'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
                  'gf' => 0, 'ga' => 0, 'gd' => 0, 'points' => 0];
        $fres  = mysqli_query($conn,
            "SELECT * FROM fixtures
             WHERE tournament_id=$tid AND round_type='group' AND played=1
               AND (home_player=$pid OR away_player=$pid)");
        while ($fx = mysqli_fetch_assoc($fres)) {
            $stats['played']++;
            $gf = ($fx['home_player'] == $pid) ? $fx['home_score'] : $fx['away_score'];
            $ga = ($fx['home_player'] == $pid) ? $fx['away_score'] : $fx['home_score'];
            $stats['gf'] += $gf; $stats['ga'] += $ga;
            if ($gf > $ga)       { $stats['won']++;  $stats['points'] += 3; }
            elseif ($gf === $ga) { $stats['drawn']++; $stats['points'] += 1; }
            else                 { $stats['lost']++; }
        }
        $stats['gd'] = $stats['gf'] - $stats['ga'];
        $standings[] = $stats;
    }
    usort($standings, function($a, $b) {
        if ($b['points'] !== $a['points']) return $b['points'] - $a['points'];
        if ($b['gd']     !== $a['gd'])     return $b['gd']     - $a['gd'];
        return $b['gf'] - $a['gf'];
    });
    return $standings;
}

function playerName($conn, $pid) {
    if (!$pid) return '<em class="text-muted">TBD</em>';
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM players WHERE id=$pid"));
    return $r ? htmlspecialchars($r['name']) : 'Unknown';
}

$standings      = getStandings($conn, $tid);
$group_fixtures = mysqli_query($conn,
    "SELECT f.*, hp.name AS home_name, ap.name AS away_name
     FROM fixtures f
     JOIN players hp ON hp.id = f.home_player
     JOIN players ap ON ap.id = f.away_player
     WHERE f.tournament_id=$tid AND f.round_type='group'
     ORDER BY f.id");

$ko_map = [];
$ko_res = mysqli_query($conn,
    "SELECT * FROM fixtures WHERE tournament_id=$tid AND round_type IN ('semi1','semi2','qualifier','final')");
while ($kfx = mysqli_fetch_assoc($ko_res)) {
    $ko_map[$kfx['round_type']] = $kfx;
}

$winner_row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT p.name FROM winners w JOIN players p ON p.id=w.player_id WHERE w.tournament_id=$tid"));

$gp_stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total, SUM(played) AS done
     FROM fixtures WHERE tournament_id=$tid AND round_type='group'"));

// Is the viewer the owner?
$is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $tournament['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>StriveX — <?= htmlspecialchars($tournament['name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header>
    <a class="logo" href="index.php">STRIVE<span>X</span></a>
    <nav>
        <a href="index.php">Home</a>
        <?php if ($is_owner): ?>
            <a href="manage_tournament.php?id=<?= $tid ?>" class="btn btn-primary btn-sm">Manage</a>
            <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
        <?php endif; ?>
    </nav>
</header>

<div class="container">
    <div class="flex-between">
        <div>
            <div class="page-title"><?= htmlspecialchars($tournament['name']) ?></div>
            <div class="page-subtitle">
                By <strong><?= htmlspecialchars($tournament['username']) ?></strong>
                &nbsp;·&nbsp; Status: <strong class="text-accent"><?= ucfirst($tournament['status']) ?></strong>
                &nbsp;·&nbsp; Group: <?= (int)$gp_stats['done'] ?>/<?= (int)$gp_stats['total'] ?> played
                <?php if ($winner_row): ?>
                    &nbsp;·&nbsp; 🏆 <strong class="text-gold"><?= htmlspecialchars($winner_row['name']) ?></strong>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs">
        <a class="tab <?= $tab === 'standings' ? 'active' : '' ?>" href="?id=<?= $tid ?>&tab=standings">Standings</a>
        <a class="tab <?= $tab === 'fixtures'  ? 'active' : '' ?>" href="?id=<?= $tid ?>&tab=fixtures">Fixtures</a>
        <a class="tab <?= $tab === 'knockout'  ? 'active' : '' ?>" href="?id=<?= $tid ?>&tab=knockout">Knockout</a>
    </div>

    <!-- ══════════════════════════════════════════════════════════ STANDINGS -->
    <?php if ($tab === 'standings'): ?>
    <div class="card">
        <div class="card-title">Group Stage Standings</div>
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Player</th>
                    <th>P</th><th>W</th><th>D</th><th>L</th>
                    <th>GF</th><th>GA</th><th>GD</th><th>Pts</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($standings as $i => $s):
                $rank = $i + 1; ?>
                <tr class="<?= $rank <= 4 ? 'qualify-row' : '' ?>">
                    <td><?= $rank ?></td>
                    <td>
                        <strong><?= htmlspecialchars($s['name']) ?></strong>
                        <?php if ($rank <= 4): ?>
                            <span class="ko-badge">KO</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $s['played'] ?></td>
                    <td><?= $s['won'] ?></td>
                    <td><?= $s['drawn'] ?></td>
                    <td><?= $s['lost'] ?></td>
                    <td><?= $s['gf'] ?></td>
                    <td><?= $s['ga'] ?></td>
                    <td><?= ($s['gd'] >= 0 ? '+' : '') . $s['gd'] ?></td>
                    <td><strong><?= $s['points'] ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="text-muted mt-1" style="font-size:0.8rem;">Top 4 advance · Win=3pts · Draw=1pt · Loss=0pts</div>
    </div>

    <!-- ══════════════════════════════════════════════════════════ FIXTURES -->
    <?php elseif ($tab === 'fixtures'): ?>
    <div class="card">
        <div class="card-title">Group Stage Fixtures</div>
        <?php if (mysqli_num_rows($group_fixtures) === 0): ?>
            <p class="text-muted">No fixtures yet.</p>
        <?php else: ?>
        <?php while ($fx = mysqli_fetch_assoc($group_fixtures)): ?>
        <div class="fixture-row">
            <div class="fixture-team"><?= htmlspecialchars($fx['home_name']) ?></div>
            <div class="fixture-score">
                <?php if ($fx['played']): ?>
                    <span><?= $fx['home_score'] ?></span>
                    <span class="vs">—</span>
                    <span><?= $fx['away_score'] ?></span>
                <?php else: ?>
                    <span class="text-muted" style="font-size:0.85rem;">vs</span>
                <?php endif; ?>
            </div>
            <div class="fixture-team away"><?= htmlspecialchars($fx['away_name']) ?></div>
        </div>
        <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════════════════════════ KNOCKOUT -->
    <?php elseif ($tab === 'knockout'): ?>
    <?php if ($tournament['status'] === 'group'): ?>
        <div class="alert alert-info">The knockout stage hasn't started yet. Check back after the group stage is complete.</div>
    <?php else:
        $sf1  = $ko_map['semi1']    ?? null;
        $sf2  = $ko_map['semi2']    ?? null;
        $qual = $ko_map['qualifier'] ?? null;
        $fin  = $ko_map['final']    ?? null;
    ?>
    <div class="card">
        <div class="card-title">Semi Final 1 &nbsp;<span class="text-muted" style="font-size:0.8rem;">1st vs 2nd</span></div>
        <?php if ($sf1): ?>
        <div class="fixture-row">
            <div class="fixture-team"><?= playerName($conn, $sf1['home_player']) ?></div>
            <div class="fixture-score">
                <?php if ($sf1['played']): ?>
                    <span><?= $sf1['home_score'] ?></span><span class="vs">—</span><span><?= $sf1['away_score'] ?></span>
                <?php else: ?><span class="text-muted">vs</span><?php endif; ?>
            </div>
            <div class="fixture-team away"><?= playerName($conn, $sf1['away_player']) ?></div>
        </div>
        <?php else: ?><p class="text-muted">Not yet set.</p><?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">Semi Final 2 &nbsp;<span class="text-muted" style="font-size:0.8rem;">3rd vs 4th</span></div>
        <?php if ($sf2): ?>
        <div class="fixture-row">
            <div class="fixture-team"><?= playerName($conn, $sf2['home_player']) ?></div>
            <div class="fixture-score">
                <?php if ($sf2['played']): ?>
                    <span><?= $sf2['home_score'] ?></span><span class="vs">—</span><span><?= $sf2['away_score'] ?></span>
                <?php else: ?><span class="text-muted">vs</span><?php endif; ?>
            </div>
            <div class="fixture-team away"><?= playerName($conn, $sf2['away_player']) ?></div>
        </div>
        <?php else: ?><p class="text-muted">Not yet set.</p><?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">Qualifier &nbsp;<span class="text-muted" style="font-size:0.8rem;">SF1 Loser vs SF2 Winner</span></div>
        <?php if ($qual && $qual['home_player'] > 0): ?>
        <div class="fixture-row">
            <div class="fixture-team"><?= playerName($conn, $qual['home_player']) ?></div>
            <div class="fixture-score">
                <?php if ($qual['played']): ?>
                    <span><?= $qual['home_score'] ?></span><span class="vs">—</span><span><?= $qual['away_score'] ?></span>
                <?php else: ?><span class="text-muted">vs</span><?php endif; ?>
            </div>
            <div class="fixture-team away"><?= playerName($conn, $qual['away_player']) ?></div>
        </div>
        <?php else: ?><p class="text-muted">Waiting for semi finals.</p><?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">⚽ Final &nbsp;<span class="text-muted" style="font-size:0.8rem;">SF1 Winner vs Qualifier Winner</span></div>
        <?php if ($fin && $fin['home_player'] > 0 && $fin['away_player'] > 0): ?>
        <div class="fixture-row">
            <div class="fixture-team"><?= playerName($conn, $fin['home_player']) ?></div>
            <div class="fixture-score">
                <?php if ($fin['played']): ?>
                    <span><?= $fin['home_score'] ?></span><span class="vs">—</span><span><?= $fin['away_score'] ?></span>
                <?php else: ?><span class="text-muted">vs</span><?php endif; ?>
            </div>
            <div class="fixture-team away"><?= playerName($conn, $fin['away_player']) ?></div>
        </div>
        <?php if ($tournament['status'] === 'finished' && $winner_row): ?>
        <div class="alert alert-success mt-2">
            🏆 Tournament Winner: <strong><?= htmlspecialchars($winner_row['name']) ?></strong>
        </div>
        <?php endif; ?>
        <?php else: ?><p class="text-muted">Waiting for qualifier result.</p><?php endif; ?>
    </div>

    <?php endif; ?>
    <?php endif; ?>

</div>
</body>
</html>
