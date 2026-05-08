<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

$uid = (int)$_SESSION['user_id'];
$tid = (int)($_GET['id'] ?? 0);
if (!$tid) { header("Location: dashboard.php"); exit; }

// Load tournament
$t_res    = mysqli_query($conn, "SELECT * FROM tournaments WHERE id=$tid AND user_id=$uid");
$tournament = mysqli_fetch_assoc($t_res);
if (!$tournament) { header("Location: dashboard.php"); exit; }

$msg = '';
$err = '';

// SAVE GROUP MATCH RESULT 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_result'])) {
    $fid  = (int)$_POST['fixture_id'];
    $home = max(0, (int)$_POST['home_score']);
    $away = max(0, (int)$_POST['away_score']);
    mysqli_query($conn, "UPDATE fixtures SET home_score=$home, away_score=$away, played=1
        WHERE id=$fid AND tournament_id=$tid AND round_type='group'");
    $msg = "Result saved.";
}

// SAVE KNOCKOUT RESULT 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ko_result'])) {
    $fid  = (int)$_POST['fixture_id'];
    $home = max(0, (int)$_POST['home_score']);
    $away = max(0, (int)$_POST['away_score']);
    mysqli_query($conn, "UPDATE fixtures SET home_score=$home, away_score=$away, played=1
        WHERE id=$fid AND tournament_id=$tid");
    $msg = "Knockout result saved.";
}

// GENERATE KNOCKOUT STAGE 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_knockout'])) {
    $unplayed = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM fixtures WHERE tournament_id=$tid AND round_type='group' AND played=0"
    ));
    if ($unplayed['c'] > 0) {
        $err = "All group stage matches must be played before generating the knockout.";
    } else {
        $standings = getStandings($conn, $tid);
        if (count($standings) < 4) {
            $err = "Need at least 4 players for the knockout stage.";
        } else {
            // Remove any old knockout fixtures
            mysqli_query($conn, "DELETE FROM fixtures WHERE tournament_id=$tid AND round_type IN ('semi1','semi2','qualifier','final')");
            mysqli_query($conn, "DELETE FROM winners WHERE tournament_id=$tid");

            $r1 = $standings[0]['player_id'];
            $r2 = $standings[1]['player_id'];
            $r3 = $standings[2]['player_id'];
            $r4 = $standings[3]['player_id'];

            // Semi Final 1: 1st vs 2nd
            mysqli_query($conn, "INSERT INTO fixtures (tournament_id, home_player, away_player, round_type, played)
                VALUES ($tid, $r1, $r2, 'semi1', 0)");

            // Semi Final 2: 3rd vs 4th
            mysqli_query($conn, "INSERT INTO fixtures (tournament_id, home_player, away_player, round_type, played)
                VALUES ($tid, $r3, $r4, 'semi2', 0)");

            // Qualifier & Final are created with TBD (0) until semis are resolved
            mysqli_query($conn, "INSERT INTO fixtures (tournament_id, home_player, away_player, round_type, played)
                VALUES ($tid, 0, 0, 'qualifier', 0)");

            mysqli_query($conn, "INSERT INTO fixtures (tournament_id, home_player, away_player, round_type, played)
                VALUES ($tid, 0, 0, 'final', 0)");

            mysqli_query($conn, "UPDATE tournaments SET status='knockout' WHERE id=$tid");
            $msg = "Knockout stage generated! Play Semi Finals next.";
        }
    }
}

// RESOLVE SEMIS - POPULATE QUALIFIER & FINAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_semis'])) {
    $sf1 = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM fixtures WHERE tournament_id=$tid AND round_type='semi1' LIMIT 1"));
    $sf2 = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM fixtures WHERE tournament_id=$tid AND round_type='semi2' LIMIT 1"));

    if (!$sf1 || !$sf2) {
        $err = "Semi final fixtures not found.";
    } elseif (!$sf1['played'] || !$sf2['played']) {
        $err = "Both semi finals must be played before resolving.";
    } else {
        // SF1 winner: 1st vs 2nd
        $sf1_winner = ($sf1['home_score'] > $sf1['away_score'])
            ? $sf1['home_player'] : $sf1['away_player'];
        $sf1_loser  = ($sf1['home_score'] > $sf1['away_score'])
            ? $sf1['away_player'] : $sf1['home_player'];

        // SF2 winner: 3rd vs 4th
        $sf2_winner = ($sf2['home_score'] > $sf2['away_score'])
            ? $sf2['home_player'] : $sf2['away_player'];

        // Qualifier: Loser of SF1 vs Winner of SF2
        mysqli_query($conn, "UPDATE fixtures SET home_player=$sf1_loser, away_player=$sf2_winner
            WHERE tournament_id=$tid AND round_type='qualifier'");

        // Final: Winner of SF1 vs TBD (qualifier winner)
        mysqli_query($conn, "UPDATE fixtures SET home_player=$sf1_winner, away_player=0
            WHERE tournament_id=$tid AND round_type='final'");

        $msg = "Semi-finals resolved! Play the Qualifier match next.";
    }
}

//  RESOLVE QUALIFIER - POPULATE FINAL 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_qualifier'])) {
    $qual = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM fixtures WHERE tournament_id=$tid AND round_type='qualifier' LIMIT 1"));

    if (!$qual || !$qual['played']) {
        $err = "The qualifier match must be played first.";
    } else {
        $qual_winner = ($qual['home_score'] > $qual['away_score'])
            ? $qual['home_player'] : $qual['away_player'];

        mysqli_query($conn, "UPDATE fixtures SET away_player=$qual_winner
            WHERE tournament_id=$tid AND round_type='final'");

        $msg = "Qualifier resolved! The Final is now ready.";
    }
}

//  SET TOURNAMENT WINNER 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_winner'])) {
    $final = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM fixtures WHERE tournament_id=$tid AND round_type='final' LIMIT 1"));

    if (!$final || !$final['played'] || $final['away_player'] == 0) {
        $err = "The Final must be played before setting a winner.";
    } else {
        $winner_id = ($final['home_score'] > $final['away_score'])
            ? $final['home_player'] : $final['away_player'];

        mysqli_query($conn, "DELETE FROM winners WHERE tournament_id=$tid");
        mysqli_query($conn, "INSERT INTO winners (tournament_id, player_id) VALUES ($tid, $winner_id)");
        mysqli_query($conn, "UPDATE tournaments SET status='finished' WHERE id=$tid");
        $msg = "🏆 Tournament complete! Winner recorded.";
    }
}

//  DELETE TOURNAMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tournament'])) {
    mysqli_query($conn, "DELETE FROM fixtures WHERE tournament_id=$tid");
    mysqli_query($conn, "DELETE FROM tournament_players WHERE tournament_id=$tid");
    mysqli_query($conn, "DELETE FROM winners WHERE tournament_id=$tid");
    mysqli_query($conn, "DELETE FROM tournaments WHERE id=$tid AND user_id=$uid");
    header("Location: dashboard.php?deleted=1");
    exit;
}

//  STANDINGS HELPER  
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

        $fres = mysqli_query($conn,
            "SELECT * FROM fixtures
             WHERE tournament_id=$tid AND round_type='group' AND played=1
               AND (home_player=$pid OR away_player=$pid)");

        while ($fx = mysqli_fetch_assoc($fres)) {
            $stats['played']++;
            if ($fx['home_player'] == $pid) {
                $gf = $fx['home_score']; $ga = $fx['away_score'];
            } else {
                $gf = $fx['away_score']; $ga = $fx['home_score'];
            }
            $stats['gf'] += $gf;
            $stats['ga'] += $ga;
            if ($gf > $ga)       { $stats['won']++;   $stats['points'] += 3; }
            elseif ($gf === $ga) { $stats['drawn']++;  $stats['points'] += 1; }
            else                 { $stats['lost']++; }
        }
        $stats['gd'] = $stats['gf'] - $stats['ga'];
        $standings[]  = $stats;
    }

    usort($standings, function($a, $b) {
        if ($b['points'] !== $a['points']) return $b['points'] - $a['points'];
        if ($b['gd']     !== $a['gd'])     return $b['gd']     - $a['gd'];
        return $b['gf'] - $a['gf'];
    });

    return $standings;
}

// Helper: get player name by id
function playerName($conn, $pid) {
    if (!$pid) return '<span class="text-muted">TBD</span>';
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM players WHERE id=$pid"));
    return $r ? htmlspecialchars($r['name']) : 'Unknown';
}

// FETCH DATA
$standings = getStandings($conn, $tid);

$group_fixtures = mysqli_query($conn,
    "SELECT f.*, hp.name AS home_name, ap.name AS away_name
     FROM fixtures f
     JOIN players hp ON hp.id = f.home_player
     JOIN players ap ON ap.id = f.away_player
     WHERE f.tournament_id=$tid AND f.round_type='group'
     ORDER BY f.id");

// KO fixtures
$ko_map = [];
$ko_res = mysqli_query($conn,
    "SELECT * FROM fixtures WHERE tournament_id=$tid AND round_type IN ('semi1','semi2','qualifier','final')");
while ($kfx = mysqli_fetch_assoc($ko_res)) {
    $ko_map[$kfx['round_type']] = $kfx;
}

$winner_row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT p.name FROM winners w JOIN players p ON p.id=w.player_id WHERE w.tournament_id=$tid"));

$tab = $_GET['tab'] ?? 'group';

// Count group matches played/total
$gp_stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total,
            SUM(played) AS done
     FROM fixtures WHERE tournament_id=$tid AND round_type='group'"));
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
        <a href="dashboard.php">Dashboard</a>
        <a href="players.php">My Players</a>
        <a href="view_tournament.php?id=<?= $tid ?>">Public View</a>
        <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
    </nav>
</header>

<div class="container">
    <div class="flex-between">
        <div>
            <div class="page-title"><?= htmlspecialchars($tournament['name']) ?></div>
            <div class="page-subtitle">
                Status: <strong class="text-accent"><?= ucfirst($tournament['status']) ?></strong>
                &nbsp;·&nbsp; Group: <?= (int)$gp_stats['done'] ?>/<?= (int)$gp_stats['total'] ?> played
                <?php if ($winner_row): ?>
                    &nbsp;·&nbsp; 🏆 <strong class="text-gold"><?= htmlspecialchars($winner_row['name']) ?></strong>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <form method="POST" onsubmit="return confirm('Delete this tournament? This cannot be undone.')">
                <button type="submit" name="delete_tournament" class="btn btn-danger btn-sm">Delete</button>
            </form>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if (isset($_GET['created'])): ?><div class="alert alert-success">Tournament created! Enter group stage results below.</div><?php endif; ?>

    <!-- TABS -->
    <div class="tabs">
        <a class="tab <?= $tab === 'group'     ? 'active' : '' ?>" href="?id=<?= $tid ?>&tab=group">Group Stage</a>
        <a class="tab <?= $tab === 'standings' ? 'active' : '' ?>" href="?id=<?= $tid ?>&tab=standings">Standings</a>
        <a class="tab <?= $tab === 'knockout'  ? 'active' : '' ?>" href="?id=<?= $tid ?>&tab=knockout">Knockout</a>
    </div>

    <!-- GROUP STAGE -->
    <?php if ($tab === 'group'): ?>
    <div class="card">
        <div class="card-title">Group Stage Fixtures (Double Round-Robin)</div>
        <?php if (mysqli_num_rows($group_fixtures) === 0): ?>
            <p class="text-muted">No fixtures found.</p>
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
                    <form method="POST" style="display:flex;align-items:center;gap:6px;">
                        <input type="hidden" name="fixture_id" value="<?= $fx['id'] ?>">
                        <input type="number" name="home_score" min="0" value="0" style="width:52px;">
                        <span class="vs" style="font-size:0.9rem;">—</span>
                        <input type="number" name="away_score" min="0" value="0" style="width:52px;">
                        <button type="submit" name="save_result" class="btn btn-primary btn-sm">Save</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="fixture-team away"><?= htmlspecialchars($fx['away_name']) ?></div>
            <?php if ($fx['played']): ?>
                <a href="#" onclick="editFixture(<?= $fx['id'] ?>, <?= $fx['home_score'] ?>, <?= $fx['away_score'] ?>)"
                   style="font-size:0.75rem;color:var(--text-muted);margin-left:0.5rem;">edit</a>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <!-- STANDINGS -->
    <?php elseif ($tab === 'standings'): ?>
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
                $rank = $i + 1;
                $hl   = $rank <= 4 ? 'qualify-row' : '';
            ?>
                <tr class="<?= $hl ?>">
                    <td><?= $rank ?></td>
                    <td>
                        <strong><?= htmlspecialchars($s['name']) ?></strong>
                        <?php if ($rank === 1): ?><span class="ko-badge">SF1</span><?php endif; ?>
                        <?php if ($rank === 2): ?><span class="ko-badge">SF1</span><?php endif; ?>
                        <?php if ($rank === 3): ?><span class="ko-badge">SF2</span><?php endif; ?>
                        <?php if ($rank === 4): ?><span class="ko-badge">SF2</span><?php endif; ?>
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
        <div class="text-muted mt-1" style="font-size:0.8rem;">Top 4 advance to the knockout stage.</div>

        <?php if ($tournament['status'] === 'group'): ?>
        <div class="mt-2">
            <form method="POST" onsubmit="return confirm('Generate knockout? All group matches must be complete.')">
                <button type="submit" name="generate_knockout" class="btn btn-primary">
                    Generate Knockout Stage →
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- KNOCKOUT -->
    <?php elseif ($tab === 'knockout'): ?>

    <?php if ($tournament['status'] === 'group'): ?>
        <div class="alert alert-info">Complete all group stage matches, then generate the knockout from the <a href="?id=<?= $tid ?>&tab=standings" class="text-accent">Standings tab</a>.</div>
    <?php else: ?>

    <!-- Bracket diagram -->
    <div class="card">
        <div class="card-title">Knockout Bracket — Page Playoff</div>
        <div class="bracket-info">
            <div class="bracket-col">
                <div class="bracket-label">Semi Final 1</div>
                <div class="bracket-match">1st vs 2nd</div>
            </div>
            <div class="bracket-arrow">→</div>
            <div class="bracket-col">
                <div class="bracket-label">Final</div>
                <div class="bracket-match">SF1 Winner vs Qualifier Winner</div>
            </div>
            <div class="bracket-arrow">←</div>
            <div class="bracket-col">
                <div class="bracket-label">Qualifier</div>
                <div class="bracket-match">SF1 Loser vs SF2 Winner</div>
            </div>
            <div class="bracket-arrow">←</div>
            <div class="bracket-col">
                <div class="bracket-label">Semi Final 2</div>
                <div class="bracket-match">3rd vs 4th</div>
            </div>
        </div>
    </div>

    <?php
    $sf1  = $ko_map['semi1']    ?? null;
    $sf2  = $ko_map['semi2']    ?? null;
    $qual = $ko_map['qualifier'] ?? null;
    $fin  = $ko_map['final']    ?? null;

    $semis_played = $sf1 && $sf2 && $sf1['played'] && $sf2['played'];
    $qual_ready   = $qual && $qual['home_player'] > 0;
    $qual_played  = $qual_ready && $qual['played'];
    $final_ready  = $fin && $fin['home_player'] > 0 && $fin['away_player'] > 0;
    ?>

    <!-- SEMI FINAL 1 -->
    <div class="card">
        <div class="card-title">Semi Final 1 &nbsp;<span class="text-muted" style="font-size:0.8rem;font-weight:normal;">1st vs 2nd</span></div>
        <?php if ($sf1): ?>
        <?= renderKOFixture($conn, $sf1, $tid, !$sf1['played']) ?>
        <?php else: ?><p class="text-muted">Not yet generated.</p><?php endif; ?>
    </div>

    <!-- SEMI FINAL 2 -->
    <div class="card">
        <div class="card-title">Semi Final 2 &nbsp;<span class="text-muted" style="font-size:0.8rem;font-weight:normal;">3rd vs 4th</span></div>
        <?php if ($sf2): ?>
        <?= renderKOFixture($conn, $sf2, $tid, !$sf2['played']) ?>
        <?php else: ?><p class="text-muted">Not yet generated.</p><?php endif; ?>
    </div>

    <!-- Resolve Semis Button -->
    <?php if ($semis_played && $qual && $qual['home_player'] == 0): ?>
    <div class="card">
        <p>Both semi finals have been played. Click below to set up the Qualifier and Final.</p>
        <form method="POST">
            <button type="submit" name="resolve_semis" class="btn btn-primary">Resolve Semis → Set Up Qualifier & Final</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- QUALIFIER -->
    <div class="card">
        <div class="card-title">Qualifier &nbsp;<span class="text-muted" style="font-size:0.8rem;font-weight:normal;">SF1 Loser vs SF2 Winner</span></div>
        <?php if ($qual_ready): ?>
            <?= renderKOFixture($conn, $qual, $tid, !$qual['played']) ?>
        <?php else: ?><p class="text-muted">Waiting for semi finals to be resolved.</p><?php endif; ?>
    </div>

    <!-- Resolve Qualifier Button -->
    <?php if ($qual_played && $fin && $fin['away_player'] == 0): ?>
    <div class="card">
        <p>Qualifier played. Click below to set up the Final.</p>
        <form method="POST">
            <button type="submit" name="resolve_qualifier" class="btn btn-primary">Resolve Qualifier → Set Up Final</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- FINAL -->
    <div class="card">
        <div class="card-title">⚽ Final &nbsp;<span class="text-muted" style="font-size:0.8rem;font-weight:normal;">SF1 Winner vs Qualifier Winner</span></div>
        <?php if ($final_ready): ?>
            <?= renderKOFixture($conn, $fin, $tid, !$fin['played']) ?>
            <?php if ($fin['played'] && $tournament['status'] !== 'finished'): ?>
            <div class="mt-2">
                <form method="POST" onsubmit="return confirm('Record this result as the final and close the tournament?')">
                    <button type="submit" name="set_winner" class="btn btn-primary">🏆 Set Tournament Winner</button>
                </form>
            </div>
            <?php endif; ?>
            <?php if ($tournament['status'] === 'finished' && $winner_row): ?>
            <div class="alert alert-success mt-2">
                🏆 Tournament Winner: <strong><?= htmlspecialchars($winner_row['name']) ?></strong>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-muted">Waiting for the Qualifier to be played and resolved.</p>
        <?php endif; ?>
    </div>

    <?php endif; // end knockout status check ?>
    <?php endif; // end tab check ?>

</div>

<?php
// Helper function to render a KO fixture row
function renderKOFixture($conn, $fx, $tid, $show_form) {
    $home_name = playerName($conn, $fx['home_player']);
    $away_name = playerName($conn, $fx['away_player']);
    ob_start();
    ?>
    <div class="fixture-row">
        <div class="fixture-team"><?= $home_name ?></div>
        <div class="fixture-score">
            <?php if ($fx['played']): ?>
                <span><?= $fx['home_score'] ?></span>
                <span class="vs">—</span>
                <span><?= $fx['away_score'] ?></span>
            <?php elseif ($show_form && $fx['home_player'] > 0 && $fx['away_player'] > 0): ?>
                <form method="POST" style="display:flex;align-items:center;gap:6px;">
                    <input type="hidden" name="fixture_id" value="<?= $fx['id'] ?>">
                    <input type="number" name="home_score" min="0" value="0" style="width:52px;">
                    <span class="vs">—</span>
                    <input type="number" name="away_score" min="0" value="0" style="width:52px;">
                    <button type="submit" name="save_ko_result" class="btn btn-primary btn-sm">Save</button>
                </form>
            <?php else: ?>
                <span class="text-muted">vs</span>
            <?php endif; ?>
        </div>
        <div class="fixture-team away"><?= $away_name ?></div>
    </div>
    <?php
    return ob_get_clean();
}
?>

<script>
function editFixture(id, home, away) {
    var newHome = prompt("Home score:", home);
    if (newHome === null) return;
    var newAway = prompt("Away score:", away);
    if (newAway === null) return;
    var f = document.createElement('form');
    f.method = 'POST';
    f.innerHTML = '<input name="fixture_id" value="'+id+'">'
                + '<input name="home_score" value="'+parseInt(newHome)+'">'
                + '<input name="away_score" value="'+parseInt(newAway)+'">'
                + '<input name="save_result" value="1">';
    document.body.appendChild(f);
    f.submit();
}
</script>

</body>
</html>
