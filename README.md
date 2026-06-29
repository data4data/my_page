# OA Portfolio Visit Card

Laravel + Vue + Tailwind project for a bilingual digital visit card. The public page uses initials only (`OA`) and presents expertise, results, projects, and contact direction without concrete dates or workplaces.

## Features

- Public portfolio page in English and Dutch.
- Admin page for editing bilingual content.
- Ordered expertise, metrics, process steps, and projects.
- Reusable `SkillGear` component for expertise/technology gears.
- Gear size can be changed per expertise item in admin.
- Seeded default OA content.
- Ready to extend later for company or multi-person profiles.

## Requirements

- PHP 8.3+
- Composer
- Node.js + npm
- MySQL database named `portfolio`

## Setup

Install PHP dependencies:

```bash
composer install
```

Install frontend dependencies:

```bash
npm install
```

Create the environment file if it does not exist:

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Update `.env` with your local database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=portfolio
DB_USERNAME=your_mysql_user
DB_PASSWORD=your_mysql_password
```

Run migrations and seed the default OA content:

```bash
php artisan migrate --seed
```

## Run Locally

Start Laravel:

```bash
php artisan serve
```

Start Vite in a second terminal:

```bash
npm run dev
```

Open the public page:

```text
http://127.0.0.1:8000
```

Open the admin page:

```text
http://127.0.0.1:8000/admin
```

## Build

Create production frontend assets:

```bash
npm run build
```

## Content Model

Expertise items power both the expertise cards and the gear visual. In admin you can:

- add or remove expertise items
- move items up or down
- edit English and Dutch text
- set an icon key
- set a category
- set gear size from `82` to `180`
- hide or show an item

The reusable gear component lives at:

```text
resources/js/components/SkillGear.vue
```
