# 3SV - Système de Suivi des Stocks et des Ventes

Plateforme web moderne de **gestion commerciale, de suivi des stocks et de gestion des ventes multi-entreprises**.

---

## 📋 Table des matières

- [Présentation](#-présentation)
- [Stack Technique](#-stack-technique)
- [Architecture Globale](#-architecture-globale)
- [Modèle de Données (ERD)](#-modèle-de-données-erd)
- [Rôles et Matrice des Droits (RBAC)](#-rôles-et-matrice-des-droits-rbac)
- [Flux Métier & Solutions Clés](#-flux-métier--solutions-clés)
  - [1. Entrée de stock (Livraison Fournisseur)](#1-entrée-de-stock-livraison-fournisseur)
  - [2. Sortie de stock (Vente Client avec Contrôle de Stock)](#2-sortie-de-stock-vente-client-avec-contrôle-de-stock)
  - [3. Annulation de Vente & Restauration du Stock](#3-annulation-de-vente--restauration-du-stock)
  - [4. Cycle de Vie d'une Vente](#4-cycle-de-vie-dune-vente)
- [Installation et Démarrage](#-installation-et-démarrage)
  - [Prérequis](#prérequis)
  - [Backend (Laravel 12)](#backend-laravel-12)
  - [Frontend (Angular 21)](#frontend-angular-21)
- [Comptes de Démonstration](#-comptes-de-démonstration)
- [Structure du Projet](#-structure-du-projet)

---

## 🚀 Présentation

**3SV** est une application web conçue pour répondre aux besoins de gestion quotidienne d'une ou plusieurs entreprises commerciales :
- **Multi-entreprises (Multi-tenant)** : Cloisonnement strict des données entre différentes entités économiques.
- **Gestion du catalogue** : Gestion hiérarchique des catégories et des articles avec unités de mesure fractionnaires (`kg`, `litre`, `sac`, `pièce`).
- **Traçabilité des flux de stocks** : Réception des approvisionnements (livraisons) et sorties (ventes) gérées sous transactions SQL avec horodatage et audibilité.
- **Contrôle d'intégrité** : Blocage automatique des ventes si le stock est insuffisant.
- **Reporting & Analyse financière** : Synthèse périodique (journalière, hebdomadaire, mensuelle, annuelle) du chiffre d'affaires, des dépenses d'achats, de la marge brute estimée et du top des articles.

---

## 🛠 Stack Technique

### Backend
- **Framework** : [Laravel 12](https://laravel.com) (PHP 8.2+)
- **Authentification** : Laravel Sanctum (Tokens d'API)
- **Permissions & Rôles** : `spatie/laravel-permission`
- **Data Transfer Objects (DTO)** : `spatie/laravel-data`
- **Base de données** : SQLite (développement) / MySQL ou PostgreSQL (production)

### Frontend
- **Framework** : [Angular 21](https://angular.dev) (Standalone Components, Signals réactifs, nouveau Control Flow `@if`/`@for`)
- **Design & Styles** : [Tailwind CSS 4](https://tailwindcss.com) + PostCSS
- **Tests Unitaires** : Vitest + JSDOM

---

## 🏗 Architecture Globale

L'application repose sur un modèle d'architecture découplée SPA / API REST.

```mermaid
flowchart TD
    subgraph Client["Frontend - Angular 21 (SPA)"]
        UI["Interface Utilisateur (Tailwind CSS 4)"]
        Signals["État Réactif (Signals & RxJS)"]
        AuthService["Auth & HTTP Service (Bearer Token)"]
        UI --> Signals
        Signals --> AuthService
    end

    subgraph BackendAPI["Backend - Laravel 12 (API REST)"]
        Routing["Routes API (routes/api.php)"]
        Sanctum["Auth Middleware (Laravel Sanctum)"]
        RBAC["Contrôle d'accès (Spatie Permission)"]
        Controllers["Contrôleurs (Articles, Livraisons, Ventes, Reports)"]
        DBTx["Gestion Transactionnelle (DB::transaction)"]
        Eloquent["Modèles Eloquent ORM"]

        Routing --> Sanctum
        Sanctum --> RBAC
        RBAC --> Controllers
        Controllers --> DBTx
        DBTx --> Eloquent
    end

    subgraph Storage["Persistance des Données"]
        DB[("Base de Données relationnelle")]
        Eloquent --> DB
    end

    AuthService -- "Requêtes HTTP REST (JSON)" --> Routing
```

---

## 🗄 Modèle de Données (ERD)

Le schéma ci-dessous décrit les entités du système et leurs interrelations :

```mermaid
erDiagram
    Entreprise ||--o{ User : "emploie"
    Entreprise ||--o{ Categorie : "possede"
    Entreprise ||--o{ Article : "possede"
    Entreprise ||--o{ Livraison : "enregistre"
    Entreprise ||--o{ Vente : "enregistre"

    Categorie ||--o{ Article : "regroupe"

    User ||--o{ Livraison : "saisit"
    User ||--o{ Vente : "realise"

    Livraison ||--|{ DetailLivraison : "comprend"
    Article ||--o{ DetailLivraison : "est approvisionne par"

    Vente ||--|{ DetailVente : "comprend"
    Article ||--o{ DetailVente : "fait partie de"

    Entreprise {
        bigint id PK
        string code UK
        string nom
        string logo
        string nom_base_donnee
    }

    User {
        bigint id PK
        bigint entreprise_id FK
        string name
        string username UK
        string email UK
        boolean statut
    }

    Categorie {
        bigint id PK
        bigint entreprise_id FK
        string code
        string nom
    }

    Article {
        bigint id PK
        bigint entreprise_id FK
        bigint categorie_id FK
        string code
        string nom
        string unite_mesure
        decimal quantite_stock
    }

    Livraison {
        bigint id PK
        bigint entreprise_id FK
        bigint user_id FK
        string code
        date date_livraison
        string fournisseur_nom
        string fournisseur_contact
    }

    DetailLivraison {
        bigint id PK
        bigint livraison_id FK
        bigint article_id FK
        decimal quantite
        string unite_mesure
        decimal prix_achat_unitaire
        decimal prix_vente_unitaire
    }

    Vente {
        bigint id PK
        bigint entreprise_id FK
        bigint user_id FK
        string code
        date date_vente
        string client_nom
        string client_contact
        decimal montant_total
        string statut
    }

    DetailVente {
        bigint id PK
        bigint vente_id FK
        bigint article_id FK
        decimal quantite
        string unite_mesure
        decimal prix_vente_unitaire
        decimal remise
    }
```

---

## 👥 Rôles et Matrice des Droits (RBAC)

| Fonctionnalité | `administrateur_plateforme` | `administrateur_entreprise` | `gestionnaire_stock` | `vendeur` |
| :--- | :---: | :---: | :---: | :---: |
| **Gestion globale des entreprises** | ✅ | ❌ | ❌ | ❌ |
| **Sélection / Changement d'entreprise** | ✅ | ❌ (Fixé) | ❌ (Fixé) | ❌ (Fixé) |
| **Gestion des utilisateurs & rôles** | ✅ (Tous) | ✅ (Locaux) | ❌ | ❌ |
| **Gestion du catalogue (Catégories/Articles)** | ✅ | ✅ | ✅ (Création rapide) | ❌ |
| **Entrées de stock (Livraisons)** | ✅ | ✅ | ✅ | ❌ |
| **Sorties de stock (Ventes)** | ✅ | ✅ | ❌ | ✅ |
| **Annulation de vente** | ✅ | ✅ | ❌ | ❌ |
| **États financiers & Rapports** | ✅ | ✅ | ✅ | ❌ |

---

## 🔄 Flux Métier & Solutions Clés

### 1. Entrée de stock (Livraison Fournisseur)
L'approvisionnement incrémente automatiquement et de manière atomique la quantité disponible en stock.

```mermaid
sequenceDiagram
    autonumber
    actor Gestionnaire as Gestionnaire de Stock
    participant UI as Frontend (Dashboard)
    participant API as Backend (LivraisonController)
    participant DB as Base de Données

    Gestionnaire->>UI: Saisit date, fournisseur et lignes d'articles
    UI->>API: POST /api/livraisons
    API->>API: Valide les données & les appartenances d'articles
    API->>DB: DB::transaction début
    API->>DB: Crée l'entité Livraison (Code LIV-YYYY-xxxx)
    loop Pour chaque article
        API->>DB: Crée la ligne DetailLivraison
        API->>DB: Article::increment('quantite_stock', quantite)
    end
    API->>DB: DB::transaction commit
    API-->>UI: Réponse 201 Created (Livraison complète)
    UI-->>Gestionnaire: Confirmation & mise à jour du tableau de stock
```

---

### 2. Sortie de stock (Vente Client avec Contrôle de Stock)
Lorsqu'un vendeur effectue une vente, le système vérifie d'abord que chaque article est en stock suffisant avant d'appliquer la décrémentation.

```mermaid
sequenceDiagram
    autonumber
    actor Vendeur as Vendeur
    participant UI as Frontend (Dashboard)
    participant API as Backend (VenteController)
    participant DB as Base de Données

    Vendeur->>UI: Saisie client et sélection des articles
    UI->>API: POST /api/ventes
    API->>DB: DB::transaction début
    loop Vérification de la disponibilité
        API->>DB: Vérifie quantite_stock >= quantite_demandee
        alt Stock insuffisant
            API-->>UI: 422 Unprocessable Entity ("Stock insuffisant pour...")
            UI-->>Vendeur: Affiche l'alerte d'erreur
        end
    end
    API->>DB: Crée l'entité Vente (Code VTE-YYYY-xxxx)
    loop Pour chaque article vendu
        API->>DB: Crée la ligne DetailVente (avec remise éventuelle)
        API->>DB: Article::decrement('quantite_stock', quantite)
    end
    API->>DB: Met à jour montant_total de la Vente
    API->>DB: DB::transaction commit
    API-->>UI: Réponse 201 Created (Vente validée)
    UI-->>Vendeur: Notification de succès & impression/reçu
```

---

### 3. Annulation de Vente & Restauration du Stock
Seul un administrateur peut annuler une vente. L'opération restaure intégralement les quantités en stock.

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Administrateur
    participant UI as Frontend (Dashboard)
    participant API as Backend (VenteController::annuler)
    participant DB as Base de Données

    Admin->>UI: Clique sur "Annuler la vente"
    UI->>API: PATCH /api/ventes/{id}/annuler
    API->>API: Vérifie les droits de l'utilisateur
    API->>DB: DB::transaction début
    loop Pour chaque détail de la vente
        API->>DB: Article::increment('quantite_stock', quantite)
    end
    API->>DB: Update Vente -> statut = 'annulee'
    API->>DB: DB::transaction commit
    API-->>UI: Réponse 200 OK ("Vente annulée et stock restauré")
    UI-->>Admin: Rafraîchissement de la vue et du stock
```

---

### 4. Cycle de Vie d'une Vente

```mermaid
stateDiagram-v2
    [*] --> Brouillon : Saisie par le vendeur
    Brouillon --> Validee : Validation & Stock suffisant
    Brouillon --> [*] : Annulation saisie
    Validee --> Annulee : Annulation par l'administrateur
    Annulee --> [*] : Stock restauré définitivement
```

---

## 💻 Installation et Démarrage

### Prérequis
- **PHP** : >= 8.2 avec extensions SQLite / PDO / cURL / OpenSSL / Mbstring
- **Composer** : >= 2.x
- **Node.js** : >= 20.x et **npm** >= 10.x

---

### Backend (Laravel 12)

1. Rendez-vous dans le dossier backend :
   ```bash
   cd backend
   ```

2. Installez les dépendances PHP :
   ```bash
   composer install
   ```

3. Configurez les variables d'environnement :
   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

4. Exécutez les migrations et les seeders :
   ```bash
   php artisan migrate --seed
   ```

5. Lancez le serveur local :
   ```bash
   php artisan serve
   ```
   > L'API sera accessible sur : `http://127.0.0.1:8000`

---

### Frontend (Angular 21)

1. Rendez-vous dans le dossier frontend :
   ```bash
   cd frontend
   ```

2. Installez les dépendances npm :
   ```bash
   npm install
   ```

3. Démarrez le serveur de développement :
   ```bash
   npm start
   # ou: npx ng serve
   ```
   > L'application web sera accessible sur : `http://localhost:4200`

---

## 🔑 Comptes de Démonstration

Après l'exécution des seeders (`php artisan migrate --seed`), deux comptes pré-configurés sont disponibles :

| Rôle | Nom d'utilisateur | Mot de passe | Description |
| :--- | :--- | :--- | :--- |
| **Super Admin Plateforme** | `admin` | `motdepasse` | Gestion transversale multi-entreprises |
| **Admin Entreprise** | `admin_ent` | `motdepasse` | Gestion de *"Ma Super Entreprise"* |

---

## 📂 Structure du Projet

```text
Gestion-Stock-BF/
├── backend/                        # API Laravel 12
│   ├── app/
│   │   ├── Http/Controllers/       # Contrôleurs API (Auth, Article, Vente, Livraison, Report...)
│   │   ├── Models/                 # Modèles Eloquent (Entreprise, Article, Vente, Livraison...)
│   │   ├── Support/                # Générateur de codes d'entités (EntityCode.php)
│   │   └── Data/                   # Objets de transfert de données Spatie
│   ├── config/                     # Configuration (CORS, Sanctum, Permission...)
│   ├── database/
│   │   ├── migrations/             # Schémas de base de données
│   │   └── seeders/                # Données initiales (Rôles, Entreprise, Utilisateurs)
│   └── routes/
│       └── api.php                 # Déclaration des endpoints REST
│
├── frontend/                       # Application Angular 21
│   ├── src/
│   │   ├── app/
│   │   │   ├── login/              # Page d'authentification
│   │   │   ├── register/           # Création de collaborateurs
│   │   │   ├── edit-user/          # Édition des utilisateurs
│   │   │   ├── dashboard/          # Tableau de bord principal (Catalogue, Stocks, Ventes, Rapports)
│   │   │   └── services/           # Service d'authentification et appels API HTTP
│   │   ├── styles.css              # Styles globaux & Tailwind CSS
│   │   └── main.ts                 # Point d'entrée Angular
│   └── package.json
│
└── README.md                       # Documentation du projet
```
