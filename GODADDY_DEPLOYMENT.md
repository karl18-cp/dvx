# DVX deployment on GoDaddy Web Hosting (cPanel)

## Hosting requirements

- Linux Web Hosting with cPanel
- PHP 8.3 or newer
- MySQL or MariaDB with PDO MySQL enabled
- The domain document root must point to this Laravel project's `public` directory
- `storage` and `bootstrap/cache` must be writable by PHP

Do not expose the project root as the public web directory. It contains `.env`
and other private application files.

## 1. Create the database

1. Open GoDaddy, select the hosting account, and open **cPanel Admin**.
2. Open **MySQL Database Wizard**.
3. Create a database (for example, `account_dvx`), a database user, and a strong
   password.
4. Grant that user **All Privileges** on the database.
5. Open phpMyAdmin, select the new empty database, choose **Import**, and import:
   `database/sql/dvx_mysql.sql`.

The SQL import creates the Laravel tables and the initial `DVX001` administrator.

## 2. Configure production

Copy `.env.example` to `.env` on the server and replace every placeholder:

```dotenv
APP_NAME=DVX
APP_ENV=production
APP_KEY=base64:GENERATE_A_REAL_LARAVEL_KEY
APP_DEBUG=false
APP_URL=https://your-real-domain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_cpanel_database_name
DB_USERNAME=your_cpanel_database_user
DB_PASSWORD=your_strong_database_password
```

Use the database host shown by GoDaddy if it is not `localhost`. Never upload a
`.env` file containing production credentials to a public directory.

Generate `APP_KEY` before upload with:

```shell
php artisan key:generate
```

If SSH is unavailable, generate the key locally in a temporary `.env`, then
copy only the generated `APP_KEY` value into the server's private `.env`.

## 3. Upload the application

Upload the application with these already prepared:

- `vendor/` from `composer install --no-dev --optimize-autoloader`
- `public/build/` from `npm run build`
- All application files except `node_modules/`, tests, the local `.env`, and
  `database/database.sqlite`

Point the domain document root to the uploaded `public/` directory. Laravel's
existing `public/.htaccess` handles Apache routing.

## 4. Final production commands

When GoDaddy SSH/Terminal is available, run from the project root:

```shell
php artisan optimize
php artisan storage:link
```

The phpMyAdmin SQL import replaces the need to run the initial migrations. Run
`php artisan migrate --force` only for future schema updates.

After deployment, sign in with `DVX001`, confirm the dashboard works, and change
the initial password.
