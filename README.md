# Blog

A lightweight PHP blog system.

## Installation

1. Copy `.env.example` to `.env` and fill in your database credentials.
2. Run `composer install`.
3. Run `php install.php` to set up the database.
4. Point your web server to the `public/` directory.

## Structure

- `public/` - Web root
- `app/` - Application core (MVC)
- `config/` - Configuration files
- `resources/views/` - View templates
- `storage/` - Runtime files (cache, logs, uploads)
