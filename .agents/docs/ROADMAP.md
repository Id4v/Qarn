# Roadmap Qarn : Kanban collaboratif

L'idée directrice : **chaque phase produit quelque chose d'utilisable**, et tu introduis une seule grande nouveauté à la fois. Les durées sont indicatives pour un rythme de side project (quelques soirées par semaine).

---

## Décisions d'architecture à prendre dès le départ

- **Symfony** : la dernière version stable (8.x) ou la 7.4 LTS si tu veux une base de compatibilité large pour les bundles. Vérifie la compatibilité de API Platform avant de choisir.
- **Base de données** : PostgreSQL (JSONB pour le journal d'activité, full-text search plus tard).
- **Environnement** : Docker Compose avec FrankenPHP (le Mercure hub y est intégré, donc pas de service en plus).
- **Frontend** : deux étapes volontaires. D'abord Symfony UX (Turbo + Stimulus), puis un front SPA (Vue ou React) sur la même API. Tu compares ainsi deux approches sur le même domaine métier.
- **Monorepo** : `/backend` et `/frontend` dans le même dépôt Git, ce qui simplifie la CI.

---

## Modèle de données de départ

```
User ── Team (via Membership: role OWNER/ADMIN/MEMBER)
Team ── Board ── Column ── Card
Card ── Comment, Label (N:N), Assignee (N:N User), Attachment
Board ── ActivityLog (qui, quoi, quand, payload JSONB)
```

**Point technique important : l'ordre des cartes et des colonnes.** Ne stocke pas de `position` entière contiguë (un déplacement obligerait à renuméroter des dizaines de lignes). Deux options à étudier :
- **Fractional indexing** (position décimale ou chaîne de type LexoRank) : un déplacement = 1 seule ligne modifiée.
- **Position entière avec pas** (1000, 2000, 3000...) et rééquilibrage occasionnel.

C'est un excellent sujet de réflexion, et il sera aussi pertinent côté frontend (optimistic UI).

---

## Phase 0 : Fondations et outillage (1 semaine)

**Objectif :** un projet vide mais industrialisé.

- Docker Compose (FrankenPHP, PostgreSQL, Mailpit pour les emails de dev)
- Symfony en mode webapp, AssetMapper pour le moment
- Qualité : PHPStan (niveau max progressif), PHP-CS-Fixer ou ECS, Rector
- Tests : PHPUnit (ou Pest), `zenstruck/foundry` pour les factories
- CI GitHub Actions : lint + analyse statique + tests
- `Makefile` ou `Taskfile` pour les commandes courantes
- Conventions : Conventional Commits, ADR (Architecture Decision Records) courts dans `/docs`

**Fini quand :** un push déclenche une CI verte sur un test trivial.

---

## Phase 1 : Domaine et authentification (2 à 3 semaines)

**Objectif :** un back solide avant tout JS.

- Entités Doctrine avec attributs, enums PHP pour les rôles, migrations
- Inscription / connexion (form login), vérification email, reset password (`symfonycasts/verify-email-bundle`, `reset-password-bundle`)
- Équipes et invitations par email (token signé, expiration)
- **Voters** pour toutes les autorisations (`BOARD_VIEW`, `BOARD_EDIT`, `CARD_MOVE`...)
- Réflexion DDD légère : services applicatifs / handlers plutôt que logique dans les contrôleurs
- Tests fonctionnels des règles d'accès (c'est le plus important à tester ici)

**Fini quand :** tu peux créer un compte, une équipe, inviter quelqu'un, et les accès sont verrouillés par des tests.

---

## Phase 2 : Kanban v1 en Symfony UX (3 semaines)

**Objectif :** un produit utilisable avec très peu de JS custom, pour apprécier ce que Symfony UX apporte.

- Pages Twig : liste des boards, vue board, CRUD colonnes et cartes
- **Turbo Drive / Frames / Streams** : édition en ligne, modales via frames, mises à jour partielles
- **Stimulus** : ton premier vrai contrôleur de drag & drop avec SortableJS, appel `fetch` pour persister l'ordre
- Twig Components pour la carte, le badge, la modale
- Implémentation et tests de la stratégie de position
- Premier jet du design : Tailwind ou CSS natif moderne (variables, nesting, container queries, ce qui te remettra aussi à niveau en CSS récent)

**Fini quand :** tu utilises réellement Qarn pour gérer tes propres tâches. Garde-le comme baseline de comparaison.

---

## Phase 3 : API Platform (2 semaines)

**Objectif :** exposer le domaine proprement, sans casser la v1.

- Ressources API Platform avec **DTO d'entrée/sortie** (évite d'exposer les entités directement)
- State Providers / Processors pour la logique métier (déplacement de carte, par exemple)
- Filtres, pagination, groupes de sérialisation, sous-ressources
- Sécurité : réutiliser les Voters via `security` sur les opérations
- Documentation OpenAPI générée, utilisée comme contrat
- Authentification de l'API : cookie de session pour le SPA same-origin (plus simple et plus sûr que stocker un JWT dans le navigateur), éventuellement tokens d'accès pour les clients externes
- Tests d'API (`ApiTestCase`) et gestion des erreurs en format Problem Details

**Fini quand :** toute la fonctionnalité de la phase 2 est accessible via l'API, avec les mêmes garanties de sécurité.

---

## Phase 4 : Frontend SPA (4 à 6 semaines, la plus importante pour tes objectifs)

**Objectif :** apprendre un framework JS en construisant quelque chose de réel.

**Choix du framework :** si tu pars de zéro, **Vue 3** (Composition API, `<script setup>`) a une courbe plus douce pour quelqu'un venant du HTML/CSS. **React** a un écosystème plus vaste et un marché plus large. Les deux sont valables, et la phase 2 te donne déjà une référence de comparaison. Tu peux aussi faire un second front plus tard (Phase 10).

**Étapes suggérées :**
1. **Setup** : Vite + TypeScript, ESLint, Prettier, Vitest
2. **TypeScript d'abord** : génère les types depuis l'OpenAPI (`openapi-typescript`) pour avoir un client typé
3. **Routing et auth** : routeur, garde de routes, page login
4. **Data fetching** : TanStack Query (existe pour Vue et React), gestion du cache, invalidation, états loading/error
5. **State UI** : Pinia (Vue) ou Zustand (React) uniquement pour l'état qui n'est pas du *server state*
6. **Board** : composants colonne/carte, drag & drop (`vue-draggable-plus` / `dnd-kit`)
7. **Optimistic updates** : le déplacement apparaît instantanément, rollback en cas d'erreur serveur
8. **Formulaires** : validation (Zod + lib de formulaires), gestion des erreurs venant de l'API
9. **Accessibilité** : drag & drop au clavier, focus management dans les modales, ARIA
10. **Tests** : composants (Testing Library), puis un premier test e2e Playwright

**Fini quand :** le SPA atteint la parité fonctionnelle avec la v1 Twig/UX, et tu peux écrire un comparatif honnête des deux approches (taille de code, complexité, ressenti utilisateur).

---

## Phase 5 : Temps réel avec Mercure (2 semaines)

**Objectif :** plusieurs utilisateurs voient les changements des autres en direct.

- Publication d'événements depuis le backend (Doctrine listener ou, mieux, événements de domaine) vers un topic par board
- **Autorisation des topics** via JWT Mercure (abonnement uniquement aux boards accessibles)
- Côté front : `EventSource`, intégration au cache TanStack Query (mise à jour ciblée plutôt que refetch global)
- **Problèmes à résoudre** (c'est là que c'est intéressant) :
    - Ne pas appliquer deux fois ta propre modification (id de l'émetteur dans l'événement)
    - Conflits : deux personnes déplacent la même carte simultanément
    - Reconnexion et rattrapage des événements manqués (`Last-Event-ID`)
- Indicateur de présence (qui est sur le board) en bonus

**Fini quand :** deux navigateurs côte à côte restent synchronisés, même après une coupure réseau.

---

## Phase 6 : Asynchrone, notifications, tâches planifiées (2 semaines)

- **Messenger** : transport Doctrine ou Redis/Valkey, retries, failure transport, messages idempotents
- Notifications : assignation, mention dans un commentaire (`@user`), échéance proche
- **Symfony Notifier** : email, plus un canal gratuit (Discord/Telegram/webhook)
- **Scheduler** : rappel des échéances, digest quotidien, archivage automatique
- Préférences de notification par utilisateur
- Centre de notifications dans l'UI (liste + compteur temps réel via Mercure)

---

## Phase 7 : Fonctionnalités riches (4 à 6 semaines, à découper en mini-lots)

Pioche dans cette liste selon ton envie :

- **Commentaires** avec Markdown et mentions
- **Labels, checklists, dates d'échéance, pièces jointes** (upload sécurisé, stockage local abstrait par Flysystem pour pouvoir passer à S3-compatible/MinIO plus tard)
- **Journal d'activité** (event sourcing léger : chaque action produit une entrée JSONB)
- **Recherche** : PostgreSQL full-text d'abord, Meilisearch ensuite pour comparer
- **Filtres et vues** sauvegardées (par assigné, label, échéance), état dans l'URL
- **Webhooks sortants** et tokens d'API personnels
- **Import/export** (JSON, import Trello)
- **Archivage / corbeille** (soft delete, restauration)
- **Modèles de board**
- **i18n** (FR/EN) côté Symfony et côté front
- **Thème clair/sombre**, raccourcis clavier, palette de commandes (Ctrl+K)

---

## Phase 8 : Qualité, performance, sécurité (2 semaines, puis en continu)

- **Perf backend** : profilage (Blackfire a une version gratuite limitée, sinon le profiler Symfony + `EXPLAIN ANALYZE`), traque des N+1, index, cache HTTP et Symfony Cache
- **Perf frontend** : code splitting, lazy loading des routes, Lighthouse, Web Vitals
- **Sécurité** : revue OWASP, CSP, rate limiting (composant RateLimiter), protection CSRF cohérente selon le mode (session vs token), audit des dépendances (`composer audit`, `npm audit`)
- **Tests e2e** Playwright : parcours critiques (inscription → créer un board → déplacer une carte avec 2 utilisateurs)
- **Mutation testing** (Infection) sur le cœur du domaine, pour voir si tes tests sont réellement utiles

---

## Phase 9 : Mise en production (1 à 2 semaines)

- Image Docker de production (multi-stage, FrankenPHP en mode worker)
- Déploiement sur un petit VPS ou un hébergement gratuit/peu cher (selon ton budget : l'objectif "gratuit" concerne les outils, un VPS à quelques euros reste facultatif ; tu peux aussi tout faire tourner en local ou sur un vieux PC/Raspberry Pi)
- CI/CD : build, tests, publication d'image, déploiement automatique
- HTTPS (Caddy gère Let's Encrypt automatiquement avec FrankenPHP)
- Sauvegardes de la base, gestion des secrets (`secrets:set` de Symfony)
- Observabilité : logs structurés (Monolog), healthchecks, Sentry en auto-hébergé ou GlitchTip

---

## Phase 10 : Extensions "long terme" (au choix)

- **PWA** : installation, cache hors ligne en lecture, file d'actions en attente
- **Réécrire le front dans l'autre framework** (Vue ↔ React) pour avoir une vraie comparaison sur la même API
- **Découverte de Svelte/SvelteKit** ou **Nuxt/Next** pour le SSR sur les pages publiques
- **Multi-tenancy** et facturation fictive (plans, quotas)
- **Application mobile** (Capacitor ou React Native/Expo)
- **Plugin / extension system** côté backend
- Contribuer un correctif à un bundle ou au framework que tu utilises

---

## Vue d'ensemble

| Phase | Contenu | Durée indicative |
|---|---|---|
| 0 | Fondations & CI | 1 sem. |
| 1 | Domaine, auth, Voters | 2-3 sem. |
| 2 | Kanban v1 Symfony UX | 3 sem. |
| 3 | API Platform | 2 sem. |
| 4 | SPA Vue/React | 4-6 sem. |
| 5 | Mercure temps réel | 2 sem. |
| 6 | Messenger, Notifier, Scheduler | 2 sem. |
| 7 | Fonctionnalités riches | 4-6 sem. |
| 8 | Qualité, perf, sécurité | 2 sem. |
| 9 | Production | 1-2 sem. |
| 10 | Extensions | libre |

---

## Conseils pour tenir sur la durée

1. **Tiens un journal de bord** (`/docs/journal.md`) : ce que tu as appris, ce qui t'a surpris. C'est utile pour toi et c'est une excellente matière pour des articles de blog.
2. **Utilise Qarn pour gérer le développement de Qarn** dès la phase 2 : le dogfooding garde la motivation.
3. **Écris les tests du domaine avant le frontend** : quand le front bouge beaucoup, un back stable fait gagner un temps énorme.
4. **Résiste à la tentation de tout faire en même temps** : fais d'abord que ça marche, puis rends-le propre, puis rapide.
5. **Fixe-toi des tickets courts** (une soirée maximum chacun) pour garder un sentiment de progression.

Je peux aussi te détailler le backlog de la Phase 0 et 1 sous forme de tickets prêts à copier, ou te proposer la structure exacte des dossiers et le `compose.yaml` de départ. Dis-moi par quoi tu veux commencer, ou si tu préfères que je mette cette roadmap dans un fichier.
