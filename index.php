<?php
require __DIR__ . '/lib.php';

date_default_timezone_set('Europe/Paris');
$aujourdhui = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $erreur = null;

    if ($action === 'ajouter') {
        $date = $_POST['date'] ?? '';
        $sacs = filter_var($_POST['sacs'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            $erreur = 'Date invalide.';
        } elseif ($date > $aujourdhui) {
            $erreur = 'La date ne peut pas être dans le futur.';
        } elseif ($sacs === false) {
            $erreur = 'Le nombre de sacs doit être un entier entre 1 et 50.';
        } else {
            ajouter($date, $sacs);
        }
    } elseif ($action === 'supprimer') {
        supprimer((int) ($_POST['id'] ?? 0));
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . ($erreur ? '?erreur=' . urlencode($erreur) : ''));
    exit;
}

$erreur = $_GET['erreur'] ?? null;
$ajouts = ajouts();
$stats = statistiques($aujourdhui);

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function nombre(?float $v, int $decimales = 2): string
{
    return $v === null ? '—' : number_format($v, $decimales, ',', ' ');
}

function date_fr(string $date): string
{
    return (new DateTimeImmutable($date))->format('d/m/Y');
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Suivi pellets</title>
    <link rel="icon" href="assets/icons/favicon.ico" sizes="any">
    <link rel="icon" href="assets/icons/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link rel="manifest" href="assets/icons/manifest.webmanifest">
    <meta name="theme-color" content="#c2561a">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main>
    <header>
        <h1>Suivi pellets</h1>
        <?php if ($stats['premier']): ?>
            <p class="sous-titre">Depuis le <?= date_fr($stats['premier']) ?> · <?= $stats['jours_suivis'] ?> jours suivis</p>
        <?php endif ?>
    </header>

    <section class="carte">
        <h2>Ajouter des sacs</h2>
        <?php if ($erreur): ?>
            <p class="erreur"><?= h($erreur) ?></p>
        <?php endif ?>
        <form method="post" class="saisie">
            <input type="hidden" name="action" value="ajouter">
            <label>Date
                <input type="date" name="date" value="<?= h($aujourdhui) ?>" max="<?= h($aujourdhui) ?>" required>
            </label>
            <label>Sacs
                <input type="number" name="sacs" value="1" min="1" max="50" step="1" required>
            </label>
            <button type="submit">Enregistrer</button>
        </form>
    </section>

    <section class="indicateurs">
        <div class="tuile">
            <span class="libelle">Total</span>
            <span class="valeur"><?= $stats['total'] ?></span>
            <span class="detail">sacs</span>
        </div>
        <div class="tuile">
            <span class="libelle">Dernier ajout</span>
            <span class="valeur"><?= $stats['jours_depuis_dernier'] ?? '—' ?></span>
            <span class="detail"><?= $stats['dernier'] ? 'jours (' . date_fr($stats['dernier']) . ')' : 'aucun' ?></span>
        </div>
    </section>

    <section class="carte">
        <h2>Moyennes</h2>
        <table class="moyennes">
            <thead>
            <tr><th></th><th>Sacs / jour</th><th>Sacs / semaine</th></tr>
            </thead>
            <tbody>
            <tr>
                <th>A · simple<small>total ÷ jours jusqu'à aujourd'hui</small></th>
                <td><?= nombre($stats['a']['jour'] ?? null) ?></td>
                <td><?= nombre($stats['a']['semaine'] ?? null) ?></td>
            </tr>
            <tr>
                <th>B · brûlé<small>hors dernier ajout ÷ jours entre 1er et dernier ajout</small></th>
                <td><?= nombre($stats['b']['jour'] ?? null) ?></td>
                <td><?= nombre($stats['b']['semaine'] ?? null) ?></td>
            </tr>
            </tbody>
        </table>
        <?php if ($stats['total'] && !$stats['b']): ?>
            <p class="note">La moyenne B nécessite des ajouts sur au moins deux jours différents.</p>
        <?php endif ?>
    </section>

    <?php if ($stats['total']): ?>
        <section class="carte">
            <h2>Cumul des sacs</h2>
            <div class="graphe"><canvas id="graphe-cumul"></canvas></div>
        </section>

        <section class="carte">
            <h2>Sacs par semaine</h2>
            <div class="graphe"><canvas id="graphe-semaines"></canvas></div>
        </section>
    <?php endif ?>

    <section class="carte">
        <h2>Historique</h2>
        <?php if (!$ajouts): ?>
            <p class="note">Aucun ajout enregistré pour l'instant.</p>
        <?php else: ?>
            <table class="historique">
                <thead><tr><th>Date</th><th>Sacs</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($ajouts as $a): ?>
                    <tr>
                        <td><?= date_fr($a['date']) ?></td>
                        <td><?= (int) $a['sacs'] ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Supprimer cet ajout ?')">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <button type="submit" class="lien">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </section>
</main>

<script>window.POELE = <?= json_encode(['cumul' => $stats['cumul'], 'semaines' => $stats['semaines']]) ?>;</script>
<script src="assets/vendor/chart.umd.min.js"></script>
<script src="assets/app.js"></script>
</body>
</html>
