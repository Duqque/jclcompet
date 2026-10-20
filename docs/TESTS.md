# Tests

## Lancer les tests

```bash
composer install
vendor/bin/phpunit                                   # unitaires + intégration (base MySQL jclcompet_test, recréée à chaque lancement)
TEST_BASE_URL=http://localhost:8095 vendor/bin/phpunit --testsuite Http   # tests HTTP contre Apache (données de démonstration)
```

La base `jclcompet_test` doit exister et être accessible par l'utilisateur du `.env` ; elle est entièrement recréée au
démarrage, chaque test s'exécute dans une transaction annulée.

Dernier résultat (20/10/2026, PHP 8.5.11, MySQL 8.4.11, Apache 2.4.69) : **65 tests, 332 vérifications, tous réussis**
(59 unitaires/intégration + 6 HTTP).

## Couverture fonctionnelle

| Domaine | Vérifié automatiquement |
|---|---|
| Connexion / déconnexion | succès, mauvais mot de passe, limitation après 5 échecs, compte désactivé, compte de démonstration refusé en production |
| Permissions | séparation admin / club / commissaire / accueil ; commissaire limité à son tapis **actif et affecté** ; correction refusée au commissaire après la fin de la compétition |
| Isolation des clubs | liste, modification, retrait, export : un club ne voit ni ne modifie les inscriptions d'un autre |
| Compétition | création (tapis, catégories, durées d'exemple, barème), ajout/suppression de tapis |
| Inscriptions | catégorie d'âge calculée ; normalisation des noms ; refus : incomplète, doublon, catégorie incompatible ou fermée, après clôture (club) ; inscription tardive admin ; inscription sur place (présent, à valider) ; catégorie de poids par poids ou choisie ; incohérence sexe/catégorie ; quotas ; import CSV avec lignes en erreur ; export |
| Présences | pointage, historique, auteur, heure, journal ; statut invalide refusé |
| Poules | aucun présent → refus ; seuls les présents ; jamais de mélange sexe / âge ; tailles 3, 4, 5 ; effectifs impairs ; groupe impossible → décision explicite (aucun athlète perdu) ; échange, verrouillage conservé à la régénération ; régénération / annulation refusées après le premier combat ; athlète tardif ajouté sans toucher aux résultats (+4 combats) ; répartition sur 3 tapis |
| Algorithmes | 11 athlètes → 4+4+3 ; rapprochement des poids ; séparation des clubs ; n(n−1)/2 combats ; chaque paire une fois ; repos en poule de 5 ; équilibre des tapis ; poules verrouillées conservées |
| Combats | chronomètre serveur conservé au rechargement ; 2 waza-ari = victoire affichée 020, annulation ; 3 shido = hansoku-make ; osaekomi 5/10/20 s ; égalité → décision ou golden score ; golden score au premier avantage |
| Validation | résultat soumis deux fois sans effet ; événement renvoyé après coupure non réappliqué ; deux commissaires simultanés → conflit 409 avec état à jour |
| Classements | aucune médaille sur poule incomplète ; places, points et médailles ; classement des clubs ; égalité non tranchée signalée puis départage manuel |
| Corrections | motif et confirmation obligatoires ; ancienne et nouvelle valeur au journal ; révision conservée ; classements recalculés |
| Écran TV | jeton haché, limité au tapis, désactivé, expiré, réinitialisé ; états attente / prochain / en cours / résultat ; la saisie du commissaire apparaît sans double saisie ; passage au combat suivant après le délai |
| Archivage | lecture seule (inscriptions, informations, poules → 423) ; réouverture avec motif obligatoire |
| Ventes | enregistrement, renvoi sans doublon, prix figé, remboursement unique, brut / remboursements / net, export |
| Journal | chaîne intègre ; altération directe détectée |
| HTTP | en-têtes de sécurité, CSP, cookie HttpOnly ; `.env`, `app/`, `config/`, `storage/`, migrations non servis ; CSRF obligatoire ; API sans session → 401 ; TV sans jeton → 403 ; entraîneur refusé en administration et saisie ; API de classement publique sans données privées |

## Vérifications manuelles effectuées (navigateur intégré)

- Accueil (tableau de marque), programme complet de l'édition 2026 (horaires, catégories alignées comme la page 16, clubs,
  partenaires), en largeur ordinateur et mobile.
- Administration : toutes les pages répondent (34 pages contrôlées), PDF des poules et du planning générés (Dompdf).
- Écran TV 16:9 : combat en cours, barre d'osaekomi (11 s, 14 s, 35 s), chiffre clignotant, « VICTOIRE », bandeau « À suivre ».
- Commissaire : chargement, Hajime, deux waza-ari (victoire automatique), validation, mise à jour immédiate de l'écran TV.
- Accueil mobile : liste, compteurs, filtres, boutons de pointage.

## Limites connues et points à finaliser

- Format de compétition : poules toutes rondes uniquement (pas de tableau à élimination directe ni de repêchage automatique).
- Les écrans s'actualisent par polling (1 s) : un léger délai d'affichage (≤ 1 s) est normal ; pas de WebSocket ni SSE.
- Le glisser-déposer des poules recharge la page après chaque déplacement (simple et fiable, mais pas instantané).
- Pas d'envoi d'e-mails (identifiants des clubs à transmettre par l'organisation) ; les notifications sont internes.
- Non testé sur un vrai téléviseur ni sur l'hébergement Hostinger lui-même (testé sur Apache/PHP/MySQL locaux équivalents).
- Contenu de l'édition 2026 à compléter : adresse de la salle, golden score par catégorie, mentions légales (hébergeur).
- Dompdf utilise la police DejaVu Sans (les PDF n'ont pas la police Poppins du site).
