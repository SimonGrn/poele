# poele

Suivi local de la consommation de pellets : saisie des sacs ajoutés, graphes et moyennes.

## Lancer

- **WAMP** : http://localhost/poele/
- **Docker** : `docker compose up -d`, puis http://localhost:8088/

Les deux utilisent la même base SQLite : `data/poele.sqlite` (créée automatiquement, non versionnée).

## Moyennes

- **A · simple** : total des sacs ÷ jours entre le premier ajout et aujourd'hui.
- **B · brûlé** : total hors dernier jour d'ajout ÷ jours entre le premier et le dernier ajout
  (le dernier remplissage n'est pas encore consommé). Nécessite des ajouts sur au moins deux jours.

Sacs / semaine = sacs / jour × 7.
