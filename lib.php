<?php
// Accès aux données et calcul des statistiques de consommation de pellets.

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $path = getenv('POELE_DB') ?: __DIR__ . '/data/poele.sqlite';
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE IF NOT EXISTS ajouts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL,
            sacs INTEGER NOT NULL CHECK (sacs > 0),
            cree_le TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ajouts_date ON ajouts(date)');
    }
    return $pdo;
}

function ajouter(string $date, int $sacs): void
{
    $stmt = db()->prepare('INSERT INTO ajouts (date, sacs) VALUES (?, ?)');
    $stmt->execute([$date, $sacs]);
}

function supprimer(int $id): void
{
    $stmt = db()->prepare('DELETE FROM ajouts WHERE id = ?');
    $stmt->execute([$id]);
}

function ajouts(): array
{
    return db()->query('SELECT id, date, sacs FROM ajouts ORDER BY date DESC, id DESC')->fetchAll();
}

function jours_entre(string $debut, string $fin): int
{
    return (int) (new DateTimeImmutable($debut))->diff(new DateTimeImmutable($fin))->days;
}

/**
 * Moyenne A : total des sacs / jours entre le premier ajout et aujourd'hui.
 * Moyenne B : sacs réellement brûlés (tout sauf le dernier jour d'ajout)
 *             / jours entre le premier et le dernier ajout.
 */
function statistiques(string $aujourdhui): array
{
    $parJour = db()->query('SELECT date, SUM(sacs) AS sacs FROM ajouts GROUP BY date ORDER BY date')
        ->fetchAll(PDO::FETCH_KEY_PAIR);

    $stats = [
        'total' => 0,
        'premier' => null,
        'dernier' => null,
        'jours_suivis' => 0,
        'jours_depuis_dernier' => null,
        'a' => null,
        'b' => null,
        'cumul' => ['labels' => [], 'valeurs' => []],
        'semaines' => ['labels' => [], 'valeurs' => []],
    ];
    if (!$parJour) {
        return $stats;
    }

    $dates = array_keys($parJour);
    $premier = $dates[0];
    $dernier = end($dates);
    $total = array_sum($parJour);
    $finGraphe = max($dernier, $aujourdhui);

    $stats['total'] = $total;
    $stats['premier'] = $premier;
    $stats['dernier'] = $dernier;
    $stats['jours_suivis'] = jours_entre($premier, $aujourdhui);
    $stats['jours_depuis_dernier'] = jours_entre($dernier, $aujourdhui);

    $parJourA = $total / max(1, $stats['jours_suivis']);
    $stats['a'] = ['jour' => $parJourA, 'semaine' => $parJourA * 7];

    if (count($dates) >= 2) {
        $parJourB = ($total - $parJour[$dernier]) / jours_entre($premier, $dernier);
        $stats['b'] = ['jour' => $parJourB, 'semaine' => $parJourB * 7];
    }

    // Courbe cumulée jour par jour, et barres par semaine ISO (semaines vides incluses).
    $cumul = 0;
    $semaines = [];
    $periode = new DatePeriod(
        new DateTimeImmutable($premier),
        new DateInterval('P1D'),
        (new DateTimeImmutable($finGraphe))->modify('+1 day')
    );
    foreach ($periode as $jour) {
        $cle = $jour->format('Y-m-d');
        $sacs = (int) ($parJour[$cle] ?? 0);
        $cumul += $sacs;
        $stats['cumul']['labels'][] = $cle;
        $stats['cumul']['valeurs'][] = $cumul;

        $semaine = 'S' . $jour->format('W o');
        $semaines[$semaine] = ($semaines[$semaine] ?? 0) + $sacs;
    }
    $stats['semaines']['labels'] = array_keys($semaines);
    $stats['semaines']['valeurs'] = array_values($semaines);

    return $stats;
}
