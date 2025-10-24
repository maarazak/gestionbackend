# 🏢 Backend Application de Gestion Multi-Tenant

API REST complète pour la gestion de projets et tâches avec architecture multi-tenant. Chaque organisation dispose de son propre espace isolé et sécurisé.

## 🚀 Fonctionnalités

### Architecture Multi-Tenant
- **Isolation des données** : Chaque tenant (organisation) a ses propres données
- **Sécurité renforcée** : Middleware automatique pour filtrer les données par tenant
- **Scalabilité** : Support de multiples organisations sur une seule instance

### Gestion des Entités
- **Tenants** : Organisations avec paramètres personnalisables
- **Users** : Utilisateurs avec rôles (admin/user) par tenant
- **Projects** : Projets avec statuts (active/completed/archived)
- **Tasks** : Tâches avec priorités, assignations et échéances

### API REST Complète
- **Authentification** : Inscription, connexion, déconnexion
- **CRUD complet** : Création, lecture, mise à jour, suppression
- **Relations** : Gestion des liens entre entités
- **Validation** : Contrôles de données robustes

## 🏗️ Architecture Technique

### Stack Technologique
- **Laravel 11** : Framework PHP moderne
- **MySQL** : Base de données relationnelle
- **Laravel Sanctum** : Authentification API
- **UUID** : Identifiants uniques pour toutes les entités
- **CORS** : Support des requêtes cross-origin

### Structure des Modèles

```
Tenant (Organisation)
├── Users (Utilisateurs)
├── Projects (Projets)
└── Tasks (Tâches)
    ├── Project (Projet parent)
    └── AssignedUser (Utilisateur assigné)
```

### Base de Données

#### Table `tenants`
- `id` (UUID, Primary Key)
- `name` (Nom de l'organisation)
- `slug` (Identifiant unique)
- `domain` (Domaine personnalisé, optionnel)
- `settings` (Configuration JSON, optionnel)

#### Table `users`
- `id` (UUID, Primary Key)
- `tenant_id` (Foreign Key vers tenants)
- `name`, `email`, `password`
- Contrainte unique : `[tenant_id, email]`

#### Table `projects`
- `id` (UUID, Primary Key)
- `tenant_id` (Foreign Key vers tenants)
- `name`, `description`
- `status` (active/completed/archived)
- Index sur `tenant_id`

#### Table `tasks`
- `id` (UUID, Primary Key)
- `tenant_id` (Foreign Key vers tenants)
- `project_id` (Foreign Key vers projects)
- `assigned_to` (Foreign Key vers users, optionnel)
- `title`, `description`
- `status` (todo/in_progress/done)
- `priority` (low/medium/high)
- `due_date` (Date d'échéance, optionnel)
- Index composite sur `[tenant_id, project_id]`

## 🔧 Installation

### Prérequis
- PHP 8.2+
- Composer
- MySQL 8.0+
- Node.js (pour les assets)

### Installation
```bash
# Cloner le projet
git clone <repository-url>
cd gestion-api

# Installer les dépendances
composer install
npm install

# Configuration
cp .env.example .env
php artisan key:generate

# Base de données
php artisan migrate

# Démarrer le serveur
php artisan serve
```

## 📡 API Endpoints

### Authentification
```
POST /api/register          # Inscription (création tenant + admin)
POST /api/login             # Connexion
GET  /api/me               # Profil utilisateur
POST /api/logout           # Déconnexion
```

### Gestion des Projets
```
GET    /api/projects       # Liste des projets
POST   /api/projects       # Créer un projet
GET    /api/projects/{id}  # Détail d'un projet
PUT    /api/projects/{id}  # Modifier un projet
DELETE /api/projects/{id}  # Supprimer un projet
```

### Gestion des Tâches
```
GET    /api/tasks          # Liste des tâches
POST   /api/tasks          # Créer une tâche
GET    /api/tasks/{id}     # Détail d'une tâche
PUT    /api/tasks/{id}     # Modifier une tâche
DELETE /api/tasks/{id}     # Supprimer une tâche
```

### Gestion des Tenants (Admin)
```
GET    /api/tenants                    # Liste des tenants
POST   /api/tenants                    # Créer un tenant
GET    /api/tenants/{id}               # Détail d'un tenant
PUT    /api/tenants/{id}               # Modifier un tenant
DELETE /api/tenants/{id}               # Supprimer un tenant
POST   /api/tenants/{id}/users         # Ajouter un utilisateur
GET    /api/tenants/{id}/users         # Utilisateurs du tenant
GET    /api/tenants/{id}/projects      # Projets du tenant
GET    /api/tenants/{id}/tasks         # Tâches du tenant
```

## 🔒 Sécurité

### Middleware TenantScope
- **Isolation automatique** : Filtre les données par tenant
- **Injection de contexte** : Ajoute `tenant_id` aux requêtes
- **Scope global** : Applique automatiquement les contraintes

### Authentification
- **Laravel Sanctum** : Tokens d'authentification
- **CORS configuré** : Support des requêtes frontend
- **Validation robuste** : Contrôles de données stricts

### Isolation des Données
- **Contraintes de clés étrangères** : Intégrité référentielle
- **Index optimisés** : Performance des requêtes
- **Cascade delete** : Suppression en cascade sécurisée

## 🎯 Utilisation

### Inscription d'une Organisation
```json
POST /api/register
{
  "tenant_name": "Mon Entreprise",
  "tenant_slug": "mon-entreprise",
  "name": "Admin User",
  "email": "admin@mon-entreprise.com",
  "password": "motdepasse123"
}
```

### Connexion
```json
POST /api/login
{
  "email": "admin@mon-entreprise.com",
  "password": "motdepasse123",
  "tenant_slug": "mon-entreprise"
}
```

### Création d'un Projet
```json
POST /api/projects
Authorization: Bearer {token}
{
  "name": "Nouveau Projet",
  "description": "Description du projet",
  "status": "active"
}
```

### Création d'une Tâche
```json
POST /api/tasks
Authorization: Bearer {token}
{
  "project_id": "uuid-du-projet",
  "title": "Nouvelle Tâche",
  "description": "Description de la tâche",
  "priority": "high",
  "due_date": "2024-12-31"
}
```

## 🏛️ Architecture des Controllers

### BaseController
- **Réponses standardisées** : Format JSON cohérent
- **Codes de statut** : Gestion appropriée des erreurs
- **Messages** : Retours utilisateur clairs

### Controllers Spécialisés
- **AuthController** : Authentification et gestion des sessions
- **ProjectController** : CRUD des projets
- **TaskController** : CRUD des tâches
- **TenantController** : Gestion des organisations

## 🔄 Réponses API

### Format Standard
```json
{
  "success": true,
  "message": "Opération réussie",
  "data": { ... }
}
```

### Codes de Statut
- `200` : Succès
- `201` : Création réussie
- `204` : Suppression réussie
- `400` : Erreur de requête
- `401` : Non authentifié
- `404` : Ressource non trouvée
- `422` : Erreur de validation

## 🚀 Déploiement

### Variables d'Environnement
```env
APP_NAME="Gestion API Multi-Tenant"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestion_api
DB_USERNAME=username
DB_PASSWORD=password

SANCTUM_STATEFUL_DOMAINS=localhost:3000
```

### Optimisations Production
```bash
# Cache des configurations
php artisan config:cache

# Cache des routes
php artisan route:cache

# Cache des vues
php artisan view:cache

# Optimisation Composer
composer install --optimize-autoloader --no-dev
```

## 📊 Monitoring

### Logs
- **Laravel Log** : Journaux d'application
- **Database Log** : Requêtes SQL
- **Error Tracking** : Gestion des erreurs

### Métriques
- **Performance** : Temps de réponse API
- **Utilisation** : Statistiques d'usage
- **Sécurité** : Tentatives d'accès

## 🤝 Contribution

### Développement
1. Fork du projet
2. Création d'une branche feature
3. Développement des fonctionnalités
4. Tests et validation
5. Pull Request

### Standards
- **PSR-12** : Standards de codage PHP
- **Tests** : Couverture de tests
- **Documentation** : Documentation à jour

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 🆘 Support

Pour toute question ou problème :
- **Issues GitHub** : Signalement de bugs
- **Documentation** : Guide d'utilisation
- **Communauté** : Forum de discussion

---

**Développé avec ❤️ en Laravel 11**