# NexusCore - Academic Symposium Management Platform

NexusCore is a comprehensive Event Management System built for managing academic symposiums, student registrations, event scheduling, attendance tracking, and certificate generation.

## Technology Stack

* **Backend**: PHP 8.2 (Custom MVC Architecture)
* **Frontend**: HTML5, CSS3, JavaScript (Vanilla ES6+), Bootstrap
* **Database**: MySQL / MariaDB
* **Package Management**: Composer (PHP), NPM (JS/CSS assets)
* **Environment**: Docker, Docker Compose (or XAMPP/LAMP stack)
* **Key Libraries**: PHPMailer, DomPDF, FPDF, FPDI, chillerlan/php-qrcode

## Requirements

* PHP >= 8.2
* MySQL >= 8.0 or MariaDB >= 10.5
* Composer
* Node.js & NPM
* Docker & Docker Compose (optional, for containerized environments)

## Installation

1. **Clone the repository**:
   ```bash
   git clone <repository-url>
   cd NexusCore
   ```

2. **Install PHP Dependencies**:
   ```bash
   composer install
   ```

3. **Install Frontend Dependencies**:
   ```bash
   npm install
   ```

## Environment Configuration

1. Copy the example environment file:
   ```bash
   cp .env.example .env
   ```
2. Open `.env` and configure your local database credentials and other necessary environment variables.

## Database Setup

1. Create a new MySQL database (e.g., `nexus_ems_db`).
2. Import the schema definitions found in `database/schema/` in numerical order (from `01` to `16`).
3. Import the initial master data found in `database/seeders/10_master_data.sql`.

Alternatively, if a migration script is provided in the repository, you can run it.

## Running Locally

### Using XAMPP / LAMP / Built-in PHP Server
* Set your web server's document root to the `public/` directory.
* Or run using PHP's built-in server:
  ```bash
  php -S localhost:8000 -t public
  ```

### Using Docker
You can spin up the application using the provided Docker Compose configuration:
```bash
docker-compose up -d
```
Access the application at `http://localhost`.

## Testing

The project uses PHPUnit for automated testing. Tests are located in the `tests/` directory.

```bash
vendor/bin/phpunit
```

## Deployment Basics

* **Document Root**: Ensure your production web server (Apache/Nginx) is configured to serve the `public/` directory. The root of the project should NOT be accessible to the public.
* **Environment**: Ensure `.env` is properly configured for production (`APP_ENV=production`, `APP_DEBUG=false`) and securely stored.
* **Permissions**: Ensure the web server has write access to the `storage/` and `public/uploads/` directories.
