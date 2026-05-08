# StriveX — Tournament Management System

A web-based eFootball tournament manager built with PHP and MySQL. Create your own tournament space, register players, generate double round-robin fixtures automatically, track standings, and run a full Page Playoff knockout bracket — all from your browser.

---

## Features

- **User Accounts** — Register, login, and manage your own private tournament space
- **Player Management** — Add and edit your own pool of players
- **Auto Fixture Generation** — Double round-robin fixtures generated instantly on tournament creation
- **Live Standings** — Points, goal difference, and goals scored updated in real time as results are entered
- **Page Playoff Knockout** — Top 4 from group stage enter a 4-round bracket (SF1 → SF2 → Qualifier → Final)
- **Public View** — Anyone can view a tournament's standings, fixtures, and bracket without an account
- **Data Isolation** — Each user only sees and manages their own tournaments and players

---

## Tech Stack

| Layer    | Technology                        |
|----------|-----------------------------------|
| Backend  | PHP 7.4+ (procedural)             |
| Database | MySQL via mysqli                  |
| Frontend | HTML, CSS, vanilla JavaScript     |
| Server   | XAMPP / WAMP (Apache + MySQL)     |
| Fonts    | Barlow Condensed (Google Fonts)   |

No frameworks. No Composer. No PDO. Beginner-friendly and easy to read.

---

## Tournament Format

### Group Stage
- Minimum 5 players, no upper limit
- Every player faces every other player **twice** (home and away) — double round-robin
- A tournament with N players produces **N × (N−1) × 2** total matches
- **Win = 3pts · Draw = 1pt · Loss = 0pts**
- Tiebreaker order: Points → Goal Difference → Goals Scored

### Page Playoff Knockout (Top 4)
```
Semi Final 1 ──► Winner ──────────────────────► FINAL
  1st vs 2nd                                       ▲
               └─► Loser ──► QUALIFIER ──► Winner ─┘
Semi Final 2 ──► Winner ─────┘
  3rd vs 4th
```

| Match       | Teams                        |
|-------------|------------------------------|
| Semi Final 1 | 1st place vs 2nd place      |
| Semi Final 2 | 3rd place vs 4th place      |
| Qualifier    | SF1 Loser vs SF2 Winner     |
| Final        | SF1 Winner vs Qualifier Winner |

The group stage winner has the biggest advantage — they only need to win once (SF1) to reach the Final, while the SF1 loser gets a second chance through the Qualifier.

---

## Screenshots

> Add your own screenshots here after setup.

| Dashboard | Standings | Knockout |
|-----------|-----------|----------|
| ![dashboard]() | ![standings]() | ![knockout]() |

---

## Setup — XAMPP / WAMP

### Requirements
- XAMPP or WAMP installed and running
- PHP 7.4 or higher
- MySQL
- A modern web browser

---

### Step 1 — Copy the project folder

Place the `strivex` folder into your web root:

```
XAMPP → C:/xampp/htdocs/strivex/
WAMP  → C:/wamp64/www/strivex/
```

---

### Step 2 — Import the database

1. Start **Apache** and **MySQL** in your XAMPP/WAMP control panel
2. Open **phpMyAdmin** at `http://localhost/phpmyadmin`
3. Click the **SQL** tab
4. Open `strivex.sql` in any text editor, copy all contents, paste into the SQL tab
5. Click **Go**

This creates the `strivex` database and all required tables automatically.

---

### Step 3 — Check database credentials

Open `db.php` and confirm these match your setup:

```php
$host     = "localhost";
$user     = "root";
$pass     = "";        // XAMPP default is empty; WAMP may differ
$database = "strivex";
```

---

### Step 4 — Open in browser

```
http://localhost/strivex/
```

Register an account and you're ready to go.

---

## File Structure

```
strivex/
├── index.php                 ← Public homepage with recent tournaments and stats
├── db.php                    ← Database connection (configure credentials here)
├── auth.php                  ← Session guard — included on all protected pages
├── register.php              ← New user registration
├── login.php                 ← User login
├── logout.php                ← Destroys session and redirects
├── dashboard.php             ← User's personal tournament overview
├── players.php               ← Add / edit / delete your players
├── create_tournament.php     ← Select players and generate fixtures
├── manage_tournament.php     ← Enter results, manage knockout progression
├── view_tournament.php       ← Public read-only view of any tournament
├── strivex.sql               ← Full database schema (import this first)
└── assets/
    └── css/
        └── style.css         ← All styling — dark industrial theme
```

---

## Database Schema

```sql
users               → id, username, password, created_at
players             → id, user_id, name, club, created_at
tournaments         → id, user_id, name, status, created_at
tournament_players  → id, tournament_id, player_id
fixtures            → id, tournament_id, home_player, away_player,
                       home_score, away_score, round_type, played
winners             → id, tournament_id, player_id, won_at
```

`round_type` in the fixtures table uses: `group` · `semi1` · `semi2` · `qualifier` · `final`

`status` in tournaments uses: `group` · `knockout` · `finished`

---

## How to Use

### 1. Register & Login
Go to `/register.php`, create an account, then log in.

### 2. Add Players
Navigate to **My Players** and add all the players who will participate in your tournaments. Players are private to your account.

### 3. Create a Tournament
Go to **New Tournament**, give it a name, select at least 5 players, and click **Generate**. Fixtures are created automatically.

### 4. Enter Group Stage Results
On the **Group Stage** tab inside your tournament, enter scores for each match and click Save.

### 5. Generate Knockout
Once all group matches are played, go to the **Standings** tab and click **Generate Knockout Stage**. The top 4 are seeded automatically.

### 6. Play the Knockout
Under the **Knockout** tab, play matches in this order:
1. Enter **Semi Final 1** result → Save
2. Enter **Semi Final 2** result → Save
3. Click **Resolve Semis** → Qualifier and Final slots are populated
4. Enter **Qualifier** result → Save
5. Click **Resolve Qualifier** → Final is now set
6. Enter **Final** result → Save
7. Click **Set Tournament Winner** → Tournament is closed

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| "Database connection failed" | Make sure MySQL is running in XAMPP/WAMP |
| Blank white page | Check `db.php` credentials |
| "Table doesn't exist" | Re-run `strivex.sql` in phpMyAdmin |
| Fonts not loading | Requires an internet connection (Google Fonts CDN) |
| Knockout not generating | All group stage matches must be marked as played first |

---

## Security Notes

- Passwords are hashed using PHP's `password_hash()` with bcrypt — never stored in plain text
- Login verification uses `password_verify()` — no MD5 or SHA1
- Auth queries use prepared statements (`mysqli_prepare`) to prevent SQL injection
- All tournament/player queries are scoped to `$_SESSION['user_id']` server-side — UI hiding alone is not relied upon

---

## License

This project is open source and free to use for personal and educational purposes.

---

## Author

Built by [your name here] · [your GitHub profile link]
