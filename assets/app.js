(function () {
    const data = window.POELE;
    if (!data || !data.cumul.labels.length || !window.Chart) {
        return;
    }

    const css = getComputedStyle(document.documentElement);
    const couleur = (nom) => css.getPropertyValue(nom).trim();
    const accent = couleur('--accent');
    const texte = couleur('--texte-doux');
    const grille = couleur('--grille');

    Chart.defaults.color = texte;
    Chart.defaults.borderColor = grille;
    Chart.defaults.font.family = 'system-ui, sans-serif';

    const dateFr = (iso) => iso.split('-').reverse().join('/');

    new Chart(document.getElementById('graphe-cumul'), {
        type: 'line',
        data: {
            labels: data.cumul.labels.map(dateFr),
            datasets: [{
                label: 'Sacs cumulés',
                data: data.cumul.valeurs,
                borderColor: accent,
                backgroundColor: accent + '22',
                fill: true,
                stepped: true,
                pointRadius: 0,
                borderWidth: 2,
            }],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { maxTicksLimit: 8, maxRotation: 0 }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
        },
    });

    new Chart(document.getElementById('graphe-semaines'), {
        type: 'bar',
        data: {
            labels: data.semaines.labels,
            datasets: [{
                label: 'Sacs ajoutés',
                data: data.semaines.valeurs,
                backgroundColor: accent,
                borderRadius: 4,
                maxBarThickness: 32,
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { maxTicksLimit: 12, maxRotation: 0 }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
        },
    });
})();
