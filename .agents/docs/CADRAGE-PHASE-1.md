# Cadrage — Phase 1 : domaine, comptes et équipes

**Statut : proposition à valider.** Ce document consigne les décisions prises pendant la discussion et propose un périmètre de phase 1. Aucune implémentation n'est autorisée par ce document seul.

## Objectif

Livrer le socle de comptes et d'équipes de Qarn : une personne peut créer et vérifier son compte, créer une équipe, y inviter des personnes et administrer ses membres selon un modèle de rôles cohérent. Les règles d'autorisation sont construites et testées en phase 1. Leur application aux écrans Kanban (tableaux et cartes) se fera en phase 2, avec les modèles concernés.

## Décisions validées

### Comptes et authentification

- L'inscription est ouverte ; une invitation n'est pas nécessaire pour créer un compte.
- L'inscription demande une adresse e-mail, un mot de passe et un nom affiché.
- La vérification de l'adresse e-mail est obligatoire pour valider toute inscription et accéder aux fonctions de l'application.
- La connexion utilise le formulaire Symfony prévu par la roadmap.
- La réinitialisation du mot de passe fait partie du périmètre de phase 1.

### Équipes et rôles

- Un compte peut appartenir à plusieurs équipes.
- La personne qui crée une équipe en devient propriétaire.
- Une équipe a deux rôles : **propriétaire** et **membre**. Une équipe peut avoir plusieurs propriétaires.
- Un propriétaire peut promouvoir un membre au rôle de propriétaire.
- Un propriétaire peut rétrograder ou retirer un autre propriétaire si un propriétaire demeure dans l'équipe.
- Seuls les propriétaires peuvent inviter des personnes et révoquer les invitations en attente.
- Toute invitation acceptée attribue le rôle membre. Une promotion ultérieure peut donner le rôle propriétaire.
- Un membre peut quitter une équipe. Un propriétaire peut également partir s'il reste au moins un autre propriétaire.
- Il doit toujours rester au moins un propriétaire. Le dernier propriétaire doit soit transférer son rôle avant de partir, soit confirmer la suppression définitive de l'équipe et de toutes les données qui lui sont rattachées.

### Invitations

- Une invitation cible l'adresse e-mail saisie par le propriétaire et est envoyée à cette adresse.
- Seul le lien signé présent dans le courriel permet d'accepter l'invitation.
- Le lien est lié à l'équipe et à l'adresse invitée, valable **14 jours**, utilisable une seule fois et révocable avant son acceptation.
- Pour un compte existant, la personne se connecte à ce compte pour rejoindre l'équipe.
- Pour un nouveau compte, la personne s'inscrit avec l'adresse invitée et vérifie cette adresse avant de rejoindre l'équipe.
- Dans les deux cas, l'acceptation rattache le compte correspondant à l'adresse invitée et crée une adhésion au rôle membre.

## Interprétation proposée pour l'autorisation

Les rôles propriétaire et membre sont **propres à chaque adhésion à une équipe**. Une même personne peut donc être propriétaire d'une équipe et membre d'une autre. Ils ne doivent pas être modélisés comme des rôles Symfony globaux sur le compte ; les décisions d'accès sont évaluées dans le contexte de l'équipe concernée.

La phase 1 établit et teste les règles d'autorisation des opérations de compte et d'équipe : créer une équipe, inviter et révoquer une invitation, gérer les membres et les rôles, quitter une équipe et supprimer une équipe. La phase 2 réutilise ce socle pour protéger les écrans et opérations des tableaux et cartes.

## Périmètre proposé pour la phase 1

1. Vérifier les dépendances, recettes Symfony et conventions déjà présentes dans le projet avant d'ajouter ou configurer quoi que ce soit.
2. Modéliser les comptes, équipes, adhésions et invitations avec Doctrine, attributs PHP, enums et migrations.
3. Mettre en place l'inscription ouverte, la connexion, la vérification obligatoire des e-mails et la réinitialisation du mot de passe.
4. Permettre à un compte vérifié de créer une équipe et d'en devenir propriétaire.
5. Implémenter le cycle de vie des invitations par e-mail : envoi, acceptation par lien signé, expiration à 14 jours, usage unique et révocation.
6. Construire les services applicatifs et les règles d'autorisation pour les opérations d'équipe, en gardant les contrôleurs minces.
7. Construire l'administration minimale des équipes et membres nécessaire pour créer une équipe, inviter, accepter, révoquer, promouvoir, rétrograder, retirer et quitter.
8. Implémenter la suppression définitive d'une équipe par son dernier propriétaire après confirmation. La suppression devra inclure les futures données rattachées aux tableaux et cartes, ajoutées en phase 2.
9. Ajouter des tests fonctionnels couvrant les parcours d'inscription, vérification, invitations et les règles d'accès et d'invariants.

## Critères d'acceptation proposés

- Une personne peut s'inscrire sans invitation, mais ne peut utiliser l'application qu'après vérification de son adresse.
- Une personne vérifiée peut créer une équipe et en devient propriétaire à la création. Elle peut aussi appartenir à plusieurs équipes.
- L'accès d'une personne et son rôle sont déterminés équipe par équipe.
- Seul un propriétaire peut inviter ou révoquer une invitation en attente.
- Une invitation acceptée n'accorde que le rôle membre.
- Un lien expiré, révoqué ou déjà utilisé ne peut pas créer une adhésion.
- Un propriétaire peut modifier les adhésions et rôles sans pouvoir laisser une équipe sans propriétaire.
- Le dernier propriétaire peut supprimer définitivement son équipe et les données associées après confirmation.
- Les parcours et règles ci-dessus sont protégés par des tests fonctionnels pertinents.

## Hors périmètre de cette phase

- La création et la gestion des tableaux, colonnes et cartes.
- L'application des règles d'accès aux écrans Kanban et aux actions sur les tableaux/cartes ; elles seront traitées en phase 2.
- L'API Platform, le SPA, Mercure et FrankenPHP.

## Détails à arrêter pendant la planification technique

Ces choix ne changent pas les règles métier validées ; ils seront précisés avant ou pendant l'implémentation : stockage et validation du jeton d'invitation, frontières transactionnelles des opérations de départ/suppression, comportement de renvoi d'une invitation expirée ou révoquée, et détails UX des liens de vérification et d'acceptation.

## Lien avec la roadmap

La roadmap place actuellement les autorisations `BOARD_*` et `CARD_*` en phase 1, alors que les tableaux et cartes sont créés en phase 2. Le cadrage propose de déplacer l'application des autorisations aux écrans et opérations Kanban en phase 2, tout en construisant et testant en phase 1 le modèle d'autorisation par équipe et sa hiérarchie de rôles.
