<?php
session_start();

/**
 * KONFIGURATION
 */
$mypass = "passwort"; // Dein Passwort
$userA = "PersonA";
$userB = "PersonB";
$filePattern = "abrechnungsdaten.csv*.bak"; // Sucht nach den archivierten Dateien

// Login-Prüfung (analog zur Hauptdatei)
$ip = explode(".", $_SERVER['REMOTE_ADDR']);
$is_local = ($ip[0] == "192" && $ip[1] == "168" && $ip[2] == "0");

if (isset($_POST['login_passwort']) && $_POST['login_passwort'] === $mypass) {
    $_SESSION['auth'] = true;
}

if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (!($_SESSION['auth'] ?? false) && !$is_local) {
    render_login();
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasse - Archiv</title>
    <style>
        :root { --a-bg: #FFFFE0; --b-bg: #E4EEFF; }
        body { font-family: 'Segoe UI', Helvetica, Arial, sans-serif; background: #C9C9C9; margin: 20px; color: #333; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        h2 { border-bottom: 2px solid #555; padding-bottom: 10px; margin-top: 40px; }
        h2:first-of-type { margin-top: 0; }
        .archive-date { color: #666; font-size: 0.8em; font-weight: normal; }
        
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.9em; }
        th { text-align: left; background: #f2f2f2; padding: 8px; border-bottom: 2px solid #ddd; }
        td { padding: 8px; border-bottom: 1px solid #eee; }
        
        .user-a { background-color: var(--a-bg); }
        .user-b { background-color: var(--b-bg); }
        
        .summary-box { background: #f9f9f9; padding: 15px; border-radius: 8px; border-left: 5px solid #555; margin-bottom: 40px; }
        .back-link { display: inline-block; margin-bottom: 20px; text-decoration: none; font-weight: bold; color: #444; }
        .back-link:hover { text-decoration: underline; }
        
        @media print { .back-link { display: none; } }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-link">← Zurück zur aktuellen Kasse</a>
    <h1>📊 Archivierte Abrechnungen</h1>

    <?php
    // Alle Archivdateien finden und nach Datum sortieren (neueste oben)
    $files = glob($filePattern);
    rsort($files);

    if (empty($files)) {
        echo "<p>Keine archivierten Zeiträume gefunden.</p>";
    }

    foreach ($files as $filename) {
        // Zeitstempel aus Dateinamen extrahieren (falls vorhanden)
        $timestamp = "";
        if (preg_match('/_(\d+)\.bak$/', $filename, $matches)) {
            $timestamp = " - Abgeschlossen am " . date("d.m.Y H:i", $matches[1]);
        }

        echo "<h2>Datei: " . htmlspecialchars($filename) . "<span class='archive-date'>$timestamp</span></h2>";

        $entries = array_map(fn($l) => str_getcsv($l, "#"), file($filename));
        $sums = ['a' => 0, 'b' => 0, 'a_ex' => 0, 'b_ex' => 0];

        echo "<table>
                <thead>
                    <tr><th>Datum</th><th>Wer</th><th>Was</th><th>Typ</th><th style='text-align:right'>Betrag</th></tr>
                </thead>
                <tbody>";

        foreach ($entries as $row) {
            if (count($row) < 5) continue;
            $val = (float)$row[3];
            $class = ($row[1] === $userA) ? 'user-a' : 'user-b';
            
            // Summen-Logik (wie in der Hauptdatei)
            if ($row[1] === $userA) {
                ($row[4] === "Normal") ? $sums['a'] += $val : $sums['a_ex'] += $val;
            } else {
                ($row[4] === "Normal") ? $sums['b'] += $val : $sums['b_ex'] += $val;
            }

            echo "<tr class='$class'>
                    <td>{$row[0]}</td>
                    <td><strong>{$row[1]}</strong></td>
                    <td>{$row[2]}</td>
                    <td><small>" . ($row[4] === 'Normal' ? 'Gemeinsam' : 'Exklusiv') . "</small></td>
                    <td style='text-align:right'>" . number_format($row[4] === "Nicht" ? $row[3]/2 : $row[3], 2, ',', '.') . " €</td>
                  </tr>";
        }
        echo "</tbody></table>";

        // Berechnungen für diesen Zeitraum
        $ausgaben_a = $sums['a'] + ($sums['a_ex'] / 2);
        $ausgaben_b = $sums['b'] + ($sums['b_ex'] / 2);
        $guthaben_a = (($sums['a'] + $sums['a_ex']) - ($sums['b'] + $sums['b_ex'])) / 2;
        ?>

        <div class="summary-box">
            <strong>Ergebnis für diesen Zeitraum:</strong><br>
            <?= $userA ?> Ausgaben: <?= number_format($ausgaben_a, 2, ',', '.') ?> € | 
            Guthaben: <?= number_format($guthaben_a, 2, ',', '.') ?> €<br>
            
            <?= $userB ?> Ausgaben: <?= number_format($ausgaben_b, 2, ',', '.') ?> € | 
            Guthaben: <?= number_format($guthaben_a * -1, 2, ',', '.') ?> €<br>
            
            <small>Gemeinsame Ausgaben: <?= number_format($sums['a'] + $sums['b'], 2, ',', '.') ?> €</small>
        </div>
        <hr>
    <?php } ?>

    <div style="text-align: center; margin-top: 20px; font-size: 0.8em; color: #777;">
        Kasse Archiv Modul
    </div>
</div>

</body>
</html>

<?php
function render_login() {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Login</title><style>body{font-family:sans-serif; display:flex; justify-content:center; align-items:center; height:100vh; background:#C9C9C9; margin:0;} .login-card{background:white; padding:30px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.2); text-align:center;} input[type=password]{padding:10px; border:1px solid #ccc; border-radius:4px; margin-bottom:15px; width:200px; display:block; margin-left:auto; margin-right:auto;} .btn-login{background:#555; color:white; padding:10px 20px; border:none; border-radius:4px; cursor:pointer; font-weight:bold;}</style></head><body>';
    echo '<div class="login-card"><form method="post"><h3>Archiv-Login</h3><input type="password" name="login_passwort" autofocus placeholder="Passwort"><button type="submit" class="btn-login">Anmelden</button></form></div></body></html>';
}
?>
