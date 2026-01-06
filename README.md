# File Converter Web App

## Prerequisites
- **PHP**: You have PHP 8.2 installed.
- **MySQL**: You need a MySQL server running (e.g., via XAMPP, WAMP, or standalone MySQL).

## Setup Instructions

### 1. Database Setup
1.  Start your MySQL server (open XAMPP Control Panel and click "Start" next to MySQL).
2.  Open your database management tool (e.g., phpMyAdmin at http://localhost/phpmyadmin).
3.  Create a new database named `file_converter` (if it doesn't strictly require this name, check `includes/db.php`, but the default is `file_converter`).
4.  Import the schema:
    - Click on the `file_converter` database.
    - Go to the "Import" tab.
    - Choose the file `database/schema.sql` from this project directory.
    - Click "Go" or "Import".

### 2. Configuration
Open `includes/db.php` and verify the database credentials.
- **Host**: localhost
- **Database**: file_converter
- **User**: root (default for XAMPP)
- **Password**: (empty by default for XAMPP)

If you have set a password for your root user, update `$password = '';` in `includes/db.php`.

### 3. Running the Website
You can use PHP's built-in development server.

1.  Open a terminal (Command Prompt or PowerShell) in this directory.
2.  Run the following command:
    ```bash
    php -S localhost:8000
    ```
3.  Open your web browser and go to:
    [http://localhost:8000](http://localhost:8000)

## Features
- Drag & Drop file upload.
- Convert images (JPG, PNG, GIF, WebP).
- Conversion history with status tracking.
