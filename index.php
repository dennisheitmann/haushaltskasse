<?php
session_start();

/**
 * KONFIGURATION
 */
$mypass = "passwort"; // Dein Passwort
$userA = "PersonA";
$userB = "PersonB";
$dataFile = "abrechnungsdaten.csv";

// Login-Logik (Passwort oder IP-Bereich 192.168.0.x)
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

/**
 * AKTIONEN (Hinzufügen, Löschen, Archivieren)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act'])) {
    $act = $_POST['act'];

    if ($act === "Add" || $act === "Nicht fuer mich") {
        $raw_preis = floatval(str_replace(',', '.', $_POST['preis'] ?? 0));
        // Spezialität aus dem Original: "Nicht für mich" wird verdoppelt gespeichert
        $gespeicherter_preis = ($act === "Nicht fuer mich") ? $raw_preis * 2 : $raw_preis;
        
        $newLine = [
            htmlspecialchars($_POST['wann'] ?? date("d.m.Y")),
            ($_POST['wer'] === $userB ? $userB : $userA),
            str_replace("#", "", htmlspecialchars($_POST['was'] ?? '')),
            round($gespeicherter_preis, 2),
            ($act === "Nicht fuer mich" ? "Nicht" : "Normal"),
            uniqid() // Eindeutige ID für sicheres Löschen
        ];
        
        $f = fopen($dataFile, "a");
        fputcsv($f, $newLine, "#"); 
        fclose($f);
    }

    if ($act === "del" && isset($_POST['id'])) {
        $lines = file_exists($dataFile) ? file($dataFile) : [];
        $f = fopen($dataFile, "w");
        foreach ($lines as $line) {
            $row = str_getcsv($line, "#");
            // Checksummen-Logik zur Sicherheit beim Löschen
            if ($row[5] !== $_POST['id'] || sha1(trim($line)) !== $_POST['checksum']) {
                fwrite($f, $line);
            }
        }
        fclose($f);
    }

    if ($act === "Alle loeschen") {
        if (file_exists($dataFile)) {
            rename($dataFile, $dataFile . "_" . date("Ymd_His") . ".bak");
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/**
 * BERECHNUNG DER SUMMEN
 */
$entries = file_exists($dataFile) ? array_map(fn($l) => str_getcsv($l, "#"), file($dataFile)) : [];
$sums = ['a' => 0, 'b' => 0, 'a_ex' => 0, 'b_ex' => 0];

foreach ($entries as $row) {
    if (count($row) < 5) continue;
    $val = (float)$row[3];
    if ($row[1] === $userA) {
        ($row[4] === "Normal") ? $sums['a'] += $val : $sums['a_ex'] += $val;
    } else {
        ($row[4] === "Normal") ? $sums['b'] += $val : $sums['b_ex'] += $val;
    }
}

// Berechnung der Guthaben (Original-Formel)
$ausgaben_a = $sums['a'] + ($sums['a_ex'] / 2);
$ausgaben_b = $sums['b'] + ($sums['b_ex'] / 2);
$guthaben_a = (($sums['a'] + $sums['a_ex']) - ($sums['b'] + $sums['b_ex'])) / 2;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasse</title>
    <style>
        :root { --a-bg: #FFFFE0; --b-bg: #E4EEFF; --green: #28a745; --gray: #555; --red: #cc0000; }
        body { font-family: 'Segoe UI', Helvetica, Arial, sans-serif; background: #C9C9C9; margin: 20px; color: #333; }
        .container { max-width: 1100px; margin: auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        
        /* Eingabebereich Layout */
        .input-area { background: #fcfcfc; padding: 20px; border-radius: 8px; margin-bottom: 25px; display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end; border: 1px solid #ddd; }
        .input-group { display: flex; flex-direction: column; gap: 5px; }
        .input-group label { font-weight: bold; font-size: 0.9em; }
        .input-area input, .input-area select { height: 40px; padding: 0 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        
        /* Buttons */
        .btn { height: 40px; padding: 0 20px; cursor: pointer; border: none; border-radius: 4px; font-weight: bold; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; color: white; }
        .btn:hover { opacity: 0.85; }
        .btn-green { background: var(--green); }
        .btn-gray { background: var(--gray); }
        .btn-del { background: var(--red); height: 28px; padding: 0 10px; font-size: 0.8em; }
        .btn-group { display: flex; gap: 10px; }
        .btn-group .btn { min-width: 140px; }

        /* Tabelle */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; background: #f2f2f2; padding: 12px; border-bottom: 2px solid #ddd; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .user-a { background-color: var(--a-bg); }
        .user-b { background-color: var(--b-bg); }
        .type-tag { font-size: 0.75em; padding: 3px 7px; border-radius: 4px; background: rgba(0,0,0,0.05); text-transform: uppercase; font-weight: bold; color: #666; }

        /* Zusammenfassung */
        .summary-box { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding: 20px; background: #333; color: white; border-radius: 8px; }
        .user-stat { font-size: 1.1em; }
        .guthaben-pos { color: #85ffad; }
        .guthaben-neg { color: #ff8585; }

        .footer { margin-top: 25px; text-align: center; border-top: 1px solid #ddd; padding-top: 20px; }
        a { color: #444; text-decoration: none; font-weight: bold; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2>🛒 Haushaltskasse</h2>
        <form method="post"><button type="submit" name="logout" class="btn btn-gray" style="height:30px; font-size:0.7em;">Logout</button></form>
    </div>

    <form method="post" class="input-area">
        <div class="input-group">
            <label>Wann:</label>
            <input type="text" name="wann" value="<?= date("d.m.Y") ?>" size="10">
        </div>
        <div class="input-group">
            <label>Wer:</label>
            <select name="wer">
                <option><?= $userA ?></option>
                <option><?= $userB ?></option>
            </select>
        </div>
        <div class="input-group" style="flex-grow: 1;">
            <label>Was:</label>
            <input type="text" name="was" placeholder="Wofür war der Einkauf?" style="width: 100%;" required>
        </div>
        <div class="input-group">
            <label>Preis (€):</label>
            <input type="text" name="preis" size="8" placeholder="0,00" required>
        </div>
        <div class="btn-group">
            <button type="submit" name="act" value="Add" class="btn btn-green">Hinzufügen</button>
            <button type="submit" name="act" value="Nicht fuer mich" class="btn btn-gray">Nur für Partner</button>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>Datum</th>
                <th>Wer</th>
                <th>Was</th>
                <th>Typ</th>
                <th style="text-align:right">Betrag</th>
                <th style="text-align:center">Aktion</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($entries)): ?>
                <tr><td colspan="6" style="text-align:center; padding: 30px; color: #888;">Noch keine Einträge für diesen Zeitraum.</td></tr>
            <?php endif; ?>
            <?php foreach ($entries as $line_index => $row): 
                $original_line = file($dataFile)[$line_index] ?? ''; ?>
                <tr class="<?= $row[1] === $userA ? 'user-a' : 'user-b' ?>">
                    <td><?= $row[0] ?></td>
                    <td><strong><?= $row[1] ?></strong></td>
                    <td><?= $row[2] ?></td>
                    <td><span class="type-tag"><?= $row[4] === "Normal" ? "Gemeinsam" : "Exklusiv" ?></span></td>
                    <td style="text-align:right; font-family: monospace; font-size: 1.1em;">
                        <?= number_format($row[4] === "Nicht" ? $row[3]/2 : $row[3], 2, ',', '.') ?> €
                    </td>
                    <td style="text-align:center">
                        <form method="post">
                            <input type="hidden" name="id" value="<?= $row[5] ?>">
                            <input type="hidden" name="checksum" value="<?= sha1(trim($original_line)) ?>">
                            <button type="submit" name="act" value="del" class="btn btn-del" onclick="return confirm('Eintrag löschen?')">Löschen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="summary-box">
        <div class="user-stat">
            <?= $userA ?>: <?= number_format($ausgaben_a, 2, ',', '.') ?> € 
            <span class="<?= $guthaben_a >= 0 ? 'guthaben-pos' : 'guthaben-neg' ?>">
                (<?= $guthaben_a >= 0 ? 'Guthaben' : 'Schulden' ?>: <?= number_format(abs($guthaben_a), 2, ',', '.') ?> €)
            </span>
        </div>
        <div class="user-stat">
            <?= $userB ?>: <?= number_format($ausgaben_b, 2, ',', '.') ?> € 
            <span class="<?= $guthaben_a <= 0 ? 'guthaben-pos' : 'guthaben-neg' ?>">
                (<?= $guthaben_a <= 0 ? 'Guthaben' : 'Schulden' ?>: <?= number_format(abs($guthaben_a), 2, ',', '.') ?> €)
            </span>
        </div>
    </div>

    <div class="footer">
        <form method="post" onsubmit="return confirm('Soll der aktuelle Zeitraum wirklich archiviert und die Liste geleert werden?');" style="display:inline;">
            <button type="submit" name="act" value="Alle loeschen" class="btn btn-gray" style="background:#888;">Zeitraum abschließen & Archivieren</button>
        </form>
        <p><a href="auswertung.php">📊 Vergangene Zeiträume anzeigen</a></p>
    </div>
</div>

</body>
</html>
<?php
// Hilfsfunktion für den Login-Screen
function render_login() {
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Login</title><style>body{font-family:sans-serif; display:flex; justify-content:center; align-items:center; height:100vh; background:#C9C9C9; margin:0;} .login-card{background:white; padding:30px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.2); text-align:center;} input[type=password]{padding:10px; border:1px solid #ccc; border-radius:4px; margin-bottom:15px; width:200px; display:block; margin-left:auto; margin-right:auto;} .btn-login{background:#555; color:white; padding:10px 20px; border:none; border-radius:4px; cursor:pointer; font-weight:bold;}</style></head><body>';
    echo '<div class="login-card"><form method="post"><h3>Kassen-Login</h3><input type="password" name="login_passwort" autofocus placeholder="Passwort"><button type="submit" class="btn-login">Anmelden</button></form></div></body></html>';
}
?>
