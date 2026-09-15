# NexusCore Code Explanation

This guide explains the custom code in this project. It intentionally skips full line-by-line explanations of generated or third-party files such as `vendor/`, `node_modules/`, `public/assets/css/bootstrap.min.css`, `public/assets/js/vendor/*`, and the Bootstrap Icons asset folder. Those files are installed libraries, not project logic.

## Project Flow

Every browser request enters through `public/index.php`.

`public/index.php` loads Composer autoloading, imports controller/core classes, loads environment variables, creates the application object, registers routes, and passes the current request method and URL to the router.

The router checks whether the requested URL matches a registered route. If it matches, it creates the controller and calls the controller method. If not, it prints a 404 message.

Controllers decide what page or action should happen. For example, `AuthController` shows the login page, processes login forms, and logs users out.

Services hold business logic. `AuthService` handles the actual login process: find user, check status, verify password, store session data, and update last login.

Models talk to the database. `UserModel` queries the `users` table.

Views render HTML. `View::render()` loads a content template inside a layout template.

Sessions keep temporary state such as the logged-in user and flash messages.

The database SQL files create the tables, indexes, constraints, relationships, and starting data for an academic symposium management system.

## Root Files

### `composer.json`

The project is a PHP project called `sugumaran/nexuscore`.

It requires PHP 8.2 or higher and `vlucas/phpdotenv`, which reads `.env` values.

The `autoload.psr-4` block maps the namespace `App\` to the `app/` folder, so `App\Core\Router` means `app/Core/Router.php`.

The `autoload.files` block always loads `app/Helpers/url_helper.php`, making helper functions like `base_url()` and `asset()` available everywhere.

### `package.json`

The frontend package is called `nexuscore`.

It depends on Bootstrap, Bootstrap Icons, and Chart.js.

The `test` script is only a placeholder and currently fails intentionally.

## Configuration

### `config/app.php`

`<?php` starts PHP mode.

`declare(strict_types=1);` asks PHP to enforce strict scalar type handling in this file.

The file returns an array instead of defining a class.

`name` reads `APP_NAME` from the environment, falling back to `NexusCore`.

`environment` reads `APP_ENV`, falling back to `production`.

`debug` reads `APP_DEBUG` and converts it into a real boolean.

`base_url` is set to `/NexusCore/public`; this is used when generating links.

`timezone` is set to `Asia/Kolkata`.

`charset` is set to `UTF-8`.

### `config/database.php`

This file returns database connection settings.

Each key reads from `$_ENV`, which is filled from `.env` by `Bootstrap::loadEnvironment()`.

`charset` is hardcoded as `utf8mb4`, which supports full Unicode text in MySQL.

### `config/constants.php`

This file is empty. It is likely reserved for future shared constants.

## Public Entry

### `public/index.php`

`require_once dirname(__DIR__) . '/vendor/autoload.php';` loads Composer autoloading and helper files.

The `use` lines import controller and core class names so the file can refer to short names like `Router`.

`Bootstrap::loadEnvironment(dirname(__DIR__));` loads `.env` from the project root.

`$app = new Application();` loads app/database config, starts the session, and creates a database connection.

`$router = new Router();` creates the custom route registry.

`$router->get('/', [HomeController::class, 'index']);` maps the home page to `HomeController::index()`.

`$router->get('/login', ...)` maps the login page.

`$router->post('/login', ...)` maps login form submission.

`$router->get('/logout', ...)` maps logout.

The dashboard routes map four role-specific dashboard URLs to `DashboardController`.

The final `$router->dispatch(...)` line sends the actual browser request into the router.

### `public/.htaccess`

`<IfModule mod_rewrite.c>` only runs the block if Apache rewrite support is enabled.

`RewriteEngine On` enables URL rewriting.

`RewriteBase /NexusCore/public/` sets the app's base folder.

The two `RewriteCond` lines say: if the request is not a real file and not a real directory, rewrite it.

`RewriteRule ^ index.php [QSA,L]` sends matching requests to `index.php`, preserving query strings and stopping further rewrite rules.

### `public/service-worker.js`

This file is empty. It is reserved for future offline/PWA behavior.

## Core Classes

### `app/Core/Bootstrap.php`

The namespace is `App\Core`.

`use Dotenv\Dotenv;` imports the dotenv package.

`final class Bootstrap` means this class cannot be extended.

`loadEnvironment(string $basePath)` receives the project root path.

`Dotenv::createImmutable($basePath)` prepares dotenv loading from that path.

`safeLoad()` loads `.env` if it exists and does not crash if it is missing.

### `app/Core/Application.php`

The class owns the basic application startup process.

`$appConfig` stores values from `config/app.php`.

`$databaseConfig` stores values from `config/database.php`.

`$database` stores a PDO database connection.

The constructor calls `loadConfigurations()`, starts the session through `Session::start()`, and connects to the database.

`loadConfigurations()` includes the two config files using `dirname(__DIR__, 2)` to move from `app/Core` to the project root.

`startSession()` is a private helper that starts PHP sessions directly, but it is not currently used because the constructor calls `Session::start()` instead.

`connectDatabase()` calls `App\Core\Database::getConnection()` with the config array.

`getDatabase()` returns the PDO connection.

`getAppConfig()` returns the app config array.

Important note: this project currently has two database connection classes, `App\Core\Database` and `App\Database\Database`. Core startup uses `App\Core\Database`; models use `App\Database\Database`.

### `app/Core/Database.php`

This class creates one shared PDO connection when given a config array.

`private static ?PDO $connection = null;` stores the single reusable connection.

The private constructor prevents `new Database()`.

`getConnection(array $config): PDO` creates or returns the shared connection.

If no connection exists, it builds a MySQL DSN using host, port, database name, and charset.

`new PDO(...)` connects to MySQL.

The PDO options make database errors throw exceptions, return rows as associative arrays, and use real prepared statements.

The method returns the shared PDO connection.

### `app/Database/Database.php`

This is another singleton PDO class, used by `BaseModel`.

It reads database details directly from `$_ENV` instead of receiving a config array.

If a PDO connection already exists, it returns it immediately.

It falls back to localhost, port 3306, and empty credentials if env values are missing.

It builds a MySQL DSN with `utf8mb4`.

It tries to create a PDO connection with exception mode, associative fetch mode, and non-emulated prepares.

If PDO throws a `PDOException`, this class wraps it in a `RuntimeException` with a clearer message.

### `app/Core/Router.php`

`$routes` stores registered routes grouped by HTTP method.

`get($uri, $action)` stores a GET route after normalizing the URI.

`post($uri, $action)` stores a POST route after normalizing the URI.

`dispatch($method, $uri)` receives the current request method and URL.

`parse_url($uri, PHP_URL_PATH)` removes the query string.

`base_url()` is used to find and remove the project base path from the requested URL.

The URI is normalized so `login`, `/login`, and `/login/` are treated consistently.

If no matching route exists, it sends HTTP status 404 and prints a simple not-found message.

If the route action is an array, the first item is the controller class and the second item is the method.

The router creates a controller instance and calls the method.

If the route action is a callable function, it calls it directly.

`normalizeUri()` trims whitespace, converts an empty URI to `/`, and guarantees a single leading slash.

### `app/Core/Session.php`

`start()` starts the PHP session only if it is not already running.

`set($key, $value)` writes a value into `$_SESSION`.

`get($key, $default)` reads from `$_SESSION` and returns the default if the key is missing.

`has($key)` checks whether a session value exists.

`remove($key)` deletes one session value.

`regenerate()` starts the session and creates a new session ID, which helps protect login sessions.

`flash($key, $value)` stores a one-time message under `$_SESSION['_flash']`.

`getFlash($key)` reads a flash message, deletes it, and returns it.

`destroy()` clears session data, expires the session cookie if cookies are used, and destroys the active session.

### `app/Core/View.php`

`View::render($view, $data, $layout)` renders one template inside one layout.

`extract($data, EXTR_SKIP)` turns array keys into variables for the view, without overwriting existing variables.

The view name uses dots, so `auth.login` becomes `templates/auth/login.php`.

If the view file is missing, it throws a `RuntimeException`.

`$contentFile` stores the resolved view path so the layout can require it.

The layout path is built from `templates/layouts/{$layout}.php`.

If the layout is missing, it throws a `RuntimeException`.

`require $layoutFile;` loads the layout; the layout then loads `$contentFile`.

### `app/Core/Request.php` and `app/Core/Response.php`

Both files are empty placeholders. They are likely planned for future request/response abstraction.

## Helpers

### `app/Helpers/url_helper.php`

The file defines global helper functions only if they do not already exist.

`config($key)` loads `config/app.php` once into a static variable, then returns the requested value.

The static `$config` prevents reloading the config file on every helper call.

`base_url()` returns the configured base URL without a trailing slash.

`asset($path)` joins `base_url()` with a public asset path, trimming extra slashes.

## Controllers

### `app/Controllers/HomeController.php`

`index()` renders `templates/home/index.php` with the default `master` layout.

It passes `pageTitle` as `NexusCore`.

`dashboard()` simply prints `Dashboard Coming Soon`; it is not registered in the current router.

### `app/Controllers/AuthController.php`

The constructor creates an `AuthService`.

`showLogin()` checks whether the user is already logged in.

If logged in, it redirects to `/dashboard`. Note: no `/dashboard` route is currently registered, only role-specific dashboard routes.

If not logged in, it renders `auth.login` using the `auth` layout.

`login()` starts the session, reads `email` and `password` from `$_POST`, and trims the email.

If either field is empty, it flashes an error and redirects back to login.

It calls `$this->authService->login($email, $password)` to authenticate.

If authentication fails, it flashes an invalid-login error and redirects back.

If authentication succeeds, it reads the logged-in user from the session.

The `switch` redirects Admin, Principal, HOD, and Staff users to their matching dashboard.

Unknown roles redirect to the home page.

Every redirect is followed by `exit` so no extra output is sent.

`logout()` delegates session cleanup to `AuthService`, redirects home, and exits.

### `app/Controllers/DashboardController.php`

Each dashboard method calls `AuthMiddleware::handle()` first, so guests are redirected to login.

Each method renders a matching dashboard view and passes `pageTitle` plus the logged-in user.

`admin()` renders `dashboard.admin`.

`principal()` renders `dashboard.principal`; that template is currently empty.

`hod()` renders `dashboard.hod`; that template is currently empty.

`staff()` renders `dashboard.staff`; that template is currently empty.

## Middleware

### `app/Middleware/AuthMiddleware.php`

`handle()` starts the session.

It checks whether the `user` session key exists.

If the user is missing, it stores a flash error saying login is required.

It redirects the browser to `/login` and exits.

If the session has a user, the method returns normally and the protected controller continues.

## Services

### `app/Services/AuthService.php`

The constructor creates a `UserModel`.

`login($email, $password)` asks `UserModel` to find the user by email.

If no user is found, it returns `false`.

If the account is not active, it returns `false`.

`password_verify()` checks the submitted password against the stored password hash.

If the password is wrong, it returns `false`.

`Session::regenerate()` changes the session ID after successful login.

`Session::set('user', [...])` stores only the safe user fields needed by the app.

`updateLastLogin()` writes the login time to the database.

The method returns `true` when login succeeds.

`logout()` destroys the session.

`check()` returns whether a user is logged in.

`user()` returns the logged-in user array or `null`.

## Models

### `app/Models/BaseModel.php`

This abstract class gives child models access to the database.

`protected PDO $db;` stores the PDO connection for subclasses.

The constructor calls `App\Database\Database::getConnection()`.

Because it is abstract, the app should extend it rather than instantiate it directly.

### `app/Models/UserModel.php`

`$table = 'users'` stores the table name used in SQL queries.

`findByEmail($email)` builds a SELECT query for one user by email.

It prepares the SQL to avoid SQL injection.

It binds the email as a string.

It executes the statement.

It fetches one row and returns it as an array, or returns `null` if not found.

`findById($userId)` does the same process but searches by `user_id` and binds the ID as an integer.

`updateLastLogin($userId)` updates `last_login` to the current database time for that user.

It returns whether the update executed successfully.

`isActive($user)` returns true only when `account_status` equals `Active`.

### `app/Models/DepartmentModel.php`

This file is empty. It is likely intended for future department database operations.

## Validators

### `app/Validators/DepartmentValidator.php`

`validate($data)` receives department form data and returns an error array.

It starts with an empty `$errors` array.

It reads and trims `department_name`.

If the department name is empty, it adds a required error.

If the name is longer than 100 characters, it adds a length error.

It reads, trims, and uppercases `department_code`.

If the code is empty, it adds a required error.

If the code does not match `/^[A-Z0-9]{2,10}$/`, it adds a format error.

It reads `status`.

If status is not exactly `Active` or `Inactive`, it adds a status error.

It returns the collected errors. An empty array means validation passed.

## Layout Templates

### `templates/layouts/master.php`

This is the default page shell.

It sets `$pageTitle` to the passed value or the app name.

It outputs the HTML document structure.

It escapes the title with `htmlspecialchars()` before placing it in `<title>`.

It loads Bootstrap CSS, Bootstrap Icons, theme variables, global app CSS, and home page CSS.

Inside `<body>`, it requires `$contentFile`, which is the page template selected by `View::render()`.

At the bottom, it loads Bootstrap JavaScript and `app.js`.

### `templates/layouts/auth.php`

This is the shell for login-related pages.

It sets title metadata, viewport metadata, description, and author.

It loads Bootstrap, Bootstrap Icons, variables, app CSS, and auth CSS.

The body uses `class="login-page"`, which activates the auth page background styling.

It requires `$contentFile` to display the actual login form.

It loads Bootstrap JavaScript and app JavaScript.

### `templates/layouts/dashboard.php`

This is the shell for dashboard pages.

It loads Bootstrap, Bootstrap Icons, variables, global app CSS, and dashboard CSS.

It requires `$contentFile` inside the body.

It loads Bootstrap JavaScript.

### Other layout files

`admin.php`, `footer.php`, `header.php`, `hod.php`, `navbar.php`, `principal.php`, `sidebar.php`, and `staff.php` are currently empty placeholders.

## Page Templates

### `templates/auth/login.php`

The file imports `App\Core\Session`.

`$error = Session::getFlash('error');` reads and removes the one-time login error.

The HTML builds a centered Bootstrap card.

The trophy icon and headings show the NexusCore identity.

If `$error` exists, it renders a Bootstrap danger alert and escapes the message.

The form submits a POST request to `/login`.

The email input uses type `email`, name `email`, Bootstrap styling, required validation, and autofocus.

The password input uses type `password`, name `password`, Bootstrap styling, and required validation.

The remember-me checkbox is rendered but is not currently used by the backend.

The submit button sends the form.

The footer shows the college name, location, current year, and product name.

Note: the copyright symbol appears as `Â©`, which suggests an encoding issue. It should likely be `©` if the file is saved as UTF-8.

### `templates/home/index.php`

This is the public landing page.

It contains a Bootstrap navbar with branding, section links, and login button.

It contains a hero section for NexusCore with call-to-action buttons.

It uses Bootstrap Icons for visual emphasis.

It has feature cards describing symposiums, registrations, competitions, results, certificates, and reports.

It has an about section describing the college and platform purpose.

It ends with a footer.

Dynamic URLs are generated through `base_url()` and `asset()`.

Static text and Bootstrap classes control the layout.

### `templates/dashboard/admin.php`

It imports `Session` and reads the logged-in user.

It creates a two-column dashboard layout.

The left column is a dark sidebar with Dashboard, Departments, Users, and Logout links.

The logout link points to `/logout`.

The right column greets the logged-in user by `full_name`, escaped with `htmlspecialchars()`.

It shows four statistic cards: departments, students, symposiums, and competitions.

All counts are currently hardcoded as `0`.

### Other page/component templates

`dashboard/hod.php`, `dashboard/principal.php`, `dashboard/staff.php`, `errors/404.php`, `errors/500.php`, `components/alert.php`, `components/breadcrumb.php`, `components/card.php`, and all `templates/partials/*` files are empty placeholders.

## CSS Files

### `public/assets/css/variables.css`

`:root` defines reusable CSS variables.

The primary colors are blue.

Sidebar, body, card, text, status, border, radius, and shadow values are centralized here.

Other CSS files use these variables with `var(...)`.

### `public/assets/css/app.css`

This contains global styles.

`body` sets background, text color, and font.

`.card` removes borders, applies shared radius, and applies shadow.

`.btn-primary` sets the primary button background and removes the border.

`.btn-primary:hover` darkens the primary button on hover.

### `public/assets/css/auth.css`

`body.login-page` applies a blue gradient background.

`.login-card` removes card border, rounds corners, and clips overflow.

`.input-group-text` gives icon boxes a white background.

`.form-control` gives inputs a fixed height.

`.btn-primary` makes auth buttons bold, larger, and rounded.

`.card-body` makes the login card body white.

### `public/assets/css/dashboard.css`

`body` sets a light dashboard background.

`.nav-link` rounds sidebar links.

`.nav-link:hover` changes the sidebar hover background.

`.card` removes borders and rounds dashboard cards.

### `public/assets/css/home.css`

`html` enables smooth scrolling for anchor links.

`body` prevents horizontal overflow.

The navbar styles set a white background, transitions, brand spacing, and link hover color.

`.hero-section` creates a large centered hero with a blue-to-white gradient.

The `::before` and `::after` pseudo-elements create decorative background circles.

Hero images scale slightly on hover.

Primary and outline buttons receive padding and rounded corners.

Feature cards animate upward and gain a stronger shadow on hover.

The about section uses a light background.

The footer uses a dark background and lighter text.

Media queries adjust the hero layout and buttons on tablet/mobile widths.

### Empty and vendor CSS/JS

`public/assets/css/component.css` and `public/assets/js/app.js` are empty placeholders.

`bootstrap.min.css`, `bootstrap.bundle.min.js`, `chart.umd.min.js`, and Bootstrap Icons files are third-party assets.

## Database Schema

### `database/schema/01_create_database.sql`

Drops the old `nexus_ems_db` database if it exists.

Creates a fresh `nexus_ems_db` database using `utf8mb4`.

Selects that database with `USE nexus_ems_db`.

### `database/schema/02_master_tables.sql`

Creates `departments`, the master list of departments.

Creates `venues`, the list of event locations.

Creates `competition_types`, the master list of competition categories and behavior flags.

Creates `system_settings`, a flexible key-value table for platform settings.

Unique constraints prevent duplicate department codes, department names, venue codes, competition type codes, and setting keys.

The `competition_types` check constraint keeps default team size between 1 and 10.

### `database/schema/03_users_students.sql`

Creates `users` for staff/admin/principal/HOD accounts.

Creates `students` for student accounts.

Both tables store password hashes, not plain passwords.

Unique constraints prevent duplicate employee IDs, register numbers, emails, and phone numbers.

Role and account status fields use MySQL `ENUM` values.

### `database/schema/04_symposium_competition.sql`

Creates `symposiums`, which represent symposium events.

Creates `competitions`, which belong to symposiums and define event-specific details.

Both tables include status, scheduling, creator, and timestamp fields.

`competition_code` is unique.

### `database/schema/05_competition_registration.sql`

Creates `competition_coordinators`, connecting users to competitions.

Creates `applications`, representing student competition registration.

Creates `teams`, representing team registrations.

Creates `team_members`, connecting students to teams.

### `database/schema/06_results_documents.sql`

Creates `competition_results` for scores and ranks.

Creates `competition_submissions` for uploaded files.

Creates `certificates` for generated certificates and verification hashes.

Creates `reports_archive` for generated reports.

Creates `notifications` for system/email/WhatsApp messages.

Creates `audit_logs` for tracking user actions.

### `database/schema/07_indexes.sql`

Adds indexes to frequently filtered or joined columns.

Indexes improve query speed for departments, roles, years, statuses, competition dates, application lookups, team lookups, ranks, certificates, notifications, and audit logs.

### `database/schema/08_foreign_keys.sql`

Adds relationships between tables.

Examples: users belong to departments, students belong to departments, competitions belong to symposiums, applications belong to competitions and students, certificates belong to applications.

`ON DELETE CASCADE` removes dependent child rows when parent rows are deleted.

`ON DELETE RESTRICT` prevents deleting important parent records while they are still referenced.

`ON DELETE SET NULL` keeps the child row but clears the reference.

### `database/schema/09_constraints.sql`

Adds unique constraints that enforce business rules.

A student can apply to a competition only once.

A user can be assigned to a competition only once.

Only one user can hold each responsibility for a competition.

A student can appear only once per team.

Each application can have one result and one certificate.

### `database/schema/11_schema_improvements.sql`

Adds extra columns to symposiums, competitions, applications, results, certificates, notifications, and audit logs.

Adds symposium code/type, organizing department, circular, and banner paths.

Adds competition duration, submission deadline, score limit, display order, locking, soft delete, and delete tracking.

Adds approval fields to applications.

Adds publish fields to results.

Adds verification URL to certificates.

Adds sent/read timestamps to notifications.

Adds module name to audit logs.

Adds foreign keys for the new department, deleted-by, and approved-by fields.

## Seed Data

### `database/seeders/10_master_data.sql`

Inserts initial departments: Computer Science and BCA.

Inserts venues such as labs, seminar hall, auditorium, classrooms, and open stage.

Inserts competition types such as Coding, Debugging, Quiz, Paper Presentation, Poster Presentation, Web Design, UI/UX, Photography, Short Film, Drawing, E-Waste Innovation, Cooking, Meme Creation, Treasure Hunt, and Connections.

Inserts system settings such as college name, current academic year, certificate prefix, application prefix, timezone, registration status, login attempts, session timeout, password policy, audit logging, notifications, and certificate verification.

### `database/seeders/12_seed_data.sql`

Inserts one symposium record for `NEXUS2027`.

It sets the symposium as intra-department, assigns organizing department ID `2`, sets registration dates, event dates, status `Draft`, and creator user ID `1`.

Important note: this seed expects a user with `user_id = 1` to already exist because `symposiums.created_by` references `users.user_id`.

## Current Gaps To Notice

The app has two database classes. Consolidating them later would make the project easier to maintain.

`AuthController::showLogin()` redirects existing users to `/dashboard`, but that route is not registered.

The principal, HOD, and staff dashboard templates are empty, so those routes will render blank dashboard pages.

There is no seed file creating the first admin user, but login depends on records in the `users` table.

Several files are placeholders for future features.

Some text shows encoding artifacts such as `Â©` and `2â€“10`, which should be corrected by saving files as UTF-8 and replacing those characters.
