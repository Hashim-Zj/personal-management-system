# Personal Management System (PMS)

A modern, glassmorphism-styled Personal Management System to manage tasks, finances, and generate reports. Built with PHP, MySQL, and Vanilla JavaScript.

## 🚀 Live Demo

Access the system here:  
👉 [PMS Dashboard](https://lifeguard.wuaze.com)
## Features

- **Auth System**: Secure login and registration with JWT.
- **Task Management**: Create, edit, and track tasks with deadline reminders.
- **Financial Tracking**: Manage income and expenses with Excel import support.
- **Reporting**: Generate PDF and Excel reports for your financial data.
- **Admin Dashboard**: manage users, roles, and view system logs (Actions, Errors, SMTP).
- **Modern UI**: Fully responsive glassmorphism design with fluid animations.

## Prerequisites

- **PHP 7.4+**
- **MySQL / MariaDB**
- **Composer** (PHP Package Manager)

## Installation & Setup

1. **Download the Project**:
   ```bash
   git clone https://github.com/your-username/pms.git
   cd pms
   ```

2. **Install Dependencies**:
   Ensure you have Composer installed, then run:
   ```bash
   # If you have composer globally
   composer install
   
   # Or using the local phar if provided
   php composer.phar install
   ```

3. **Database Setup**:
   - Create a new database named `pms`.
   - Import the schema files:
     ```bash
     mysql -u your_user -p pms < database.sql
     mysql -u your_user -p pms < update_schema.sql
     ```

4. **Environment Configuration**:
   - Copy `.env.example` to `.env`.
   - Update the database credentials and SMTP settings.
   ```bash
   cp .env.example .env
   ```

5. **Frontend API URL**:
   - Open `js/modules/api.js` and ensure `const API_URL` matches your local development environment (e.g., `http://localhost:8000/api`).

## Running Locally

You can use the PHP built-in server to run the project:

```bash
php -S localhost:8000 router.php
```

Then visit `http://localhost:8000` in your browser.

## Deployment

Refer to the [DEPLOYMENT.md](DEPLOYMENT.md) file for instructions on deploying to free hosting platforms like InfinityFree or 000webhost.

## License

This project is open-source and available under the [MIT License](LICENSE).

