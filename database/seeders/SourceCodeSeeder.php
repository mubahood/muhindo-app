<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * The source-code catalogue.
 *
 * OWNER: these are the products, fully written. What is in the archive, what
 * it is built with, what a buyer needs to run it, and a step-by-step install
 * guide for each. What they do NOT have is the archive itself.
 *
 * That is deliberate. The code is yours and only you can supply it. They are
 * listed (fully described, so the page is worth reading) but the shop
 * refuses to sell anything it cannot hand over: no basket, no checkout, no
 * payment. Upload a zip in the admin and the buy button appears by itself.
 *
 * `php artisan shop:deliverability` lists exactly what each one is still
 * waiting for.
 */
class SourceCodeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->products() as $i => $row) {
            Product::updateOrCreate(
                ['slug' => $row['slug']],
                $row + ['sort_order' => $i, 'currency' => 'UGX', 'is_published' => true],
            );
        }
    }

    /** @return list<array<string, mixed>> */
    private function products(): array
    {
        return [

            [
                'slug' => 'invetotrack-inventory-management-system',
                'name' => 'InvetoTrack, Inventory Management System',
                'type' => 'template',
                'category' => 'Business systems',
                'price' => '450000.00',
                'compare_at_price' => '650000.00',
                'version' => '2.1',
                'summary' => 'A complete stock, sales and reporting system: Laravel back office, REST API, '
                    .'and a Flutter app that keeps working when the shop loses signal.',
                'description' => 'The full source of the system taught in the InvetoTrack capstone, three '
                    ."codebases that talk to each other, not three separate demos.\n\n"
                    .'The back office runs on Laravel with a role-based admin panel: products, stock levels, '
                    .'suppliers, sales, users and permissions. The REST API is the same one the mobile app '
                    .'uses, token-authenticated and versioned. The Flutter app is offline-first, a sale '
                    .'recorded with no signal is stored on the device and syncs itself when the connection '
                    .'comes back, which is the part most tutorials skip and every real shop needs.',
                'whats_inside' => [
                    'Laravel back office, products, stock, suppliers, sales, users, roles and permissions',
                    'REST API with token authentication, the same one the app consumes',
                    'Flutter mobile app, offline-first, with local storage and a sync queue',
                    'Database migrations and seeders with realistic sample data',
                    'Postman collection covering every endpoint',
                    'Deployment notes for a shared host and for a VPS',
                ],
                'stack' => ['Laravel 11', 'PHP 8.2', 'MySQL 8', 'Flutter 3', 'Dart', 'REST', 'Blade'],
                'requirements' => [
                    'PHP 8.2 or newer with the standard Laravel extensions',
                    'Composer 2 and MySQL 8 (MariaDB 10.6+ works too)',
                    'Node 18+ for the asset build',
                    'Flutter 3.x and Android Studio, only if you want the mobile app',
                ],
                'license_terms' => 'One developer, unlimited client projects. Resale of the source itself is not permitted.',
                'install_guide' => $this->laravelPlusFlutterGuide('InvetoTrack', 'invetotrack'),
            ],

            [
                'slug' => 'marketlink-multi-vendor-marketplace',
                'name' => 'MarketLink, Multi-Vendor Marketplace',
                'type' => 'template',
                'category' => 'E-commerce',
                'price' => '550000.00',
                'compare_at_price' => '800000.00',
                'version' => '1.4',
                'summary' => 'Many sellers, many products, one platform, Laravel back office with vendor '
                    .'onboarding, commission splits and payouts, plus a Flutter shopping app.',
                'description' => "A working marketplace, not a shop with a vendor column bolted on.\n\n"
                    .'Sellers register, are approved, and get their own dashboard scoped to their own '
                    .'products and orders. An order that spans three vendors splits into three fulfilments '
                    .'and three commission lines, which is where most marketplace tutorials fall over. '
                    .'Payouts are tracked per seller with a running balance. The customer side is a Flutter '
                    .'app: browse, search, cart, checkout, order history.',
                'whats_inside' => [
                    'Laravel platform, vendors, approval workflow, catalogue, orders, commissions, payouts',
                    'Vendor dashboard scoped so no seller can see another seller\'s data',
                    'Split orders and per-vendor fulfilment',
                    'Flutter customer app, browse, search, cart, checkout, order history',
                    'Mobile Money and card checkout wired to a gateway interface you can swap',
                    'Migrations, seeders and a Postman collection',
                ],
                'stack' => ['Laravel 11', 'PHP 8.2', 'MySQL 8', 'Flutter 3', 'REST', 'Payment gateway'],
                'requirements' => [
                    'PHP 8.2 or newer, Composer 2, MySQL 8',
                    'Node 18+ for the asset build',
                    'Flutter 3.x for the customer app',
                    'A payment gateway account if you want live payments, test keys are included',
                ],
                'license_terms' => 'One developer, unlimited client projects. Resale of the source itself is not permitted.',
                'install_guide' => $this->laravelPlusFlutterGuide('MarketLink', 'marketlink'),
            ],

            [
                'slug' => 'hotelpro-booking-management-system',
                'name' => 'HotelPro, Hotel Booking System',
                'type' => 'template',
                'category' => 'Business systems',
                'price' => '280000.00',
                'version' => '1.2',
                'summary' => 'A complete hotel booking system in plain PHP and MySQL, no framework hiding '
                    .'the logic. Customer booking side and staff back office, both included.',
                'description' => 'Written in plain PHP on purpose. Every query, every session check and '
                    .'every redirect is in front of you, which makes it the best thing to read if you want '
                    ."to understand what a framework is doing for you.\n\n"
                    .'The customer side searches availability by date and room type, takes a booking and '
                    .'confirms it by email. The staff side manages rooms, rates, bookings, check-in and '
                    .'check-out, and reports on occupancy. Availability is calculated properly, overlapping '
                    .'date ranges, not a boolean flag on the room.',
                'whats_inside' => [
                    'Customer booking site, availability search, room detail, booking and confirmation',
                    'Staff back office, rooms, rates, bookings, check-in and check-out, occupancy reports',
                    'Correct date-range availability, so two guests cannot book one room',
                    'A .sql database dump with sample rooms, rates and bookings',
                    'Printable booking confirmations and invoices',
                ],
                'stack' => ['PHP 8', 'MySQL', 'Bootstrap 5', 'JavaScript'],
                'requirements' => [
                    'PHP 8.0 or newer with mysqli and mbstring',
                    'MySQL 5.7 or newer, or MariaDB',
                    'Apache or Nginx, XAMPP, MAMP and shared hosting all work',
                ],
                'license_terms' => 'One developer, unlimited client projects. Resale of the source itself is not permitted.',
                'install_guide' => $this->plainPhpGuide('HotelPro', 'hotelpro'),
            ],

            [
                'slug' => 'online-shop-php-mysql',
                'name' => 'Online Shop, PHP & MySQL',
                'type' => 'template',
                'category' => 'E-commerce',
                'price' => '220000.00',
                'version' => '1.1',
                'summary' => 'Classic e-commerce built by hand: accounts, catalogue, image galleries, a real '
                    .'cart, shipping details and orders that persist in the database.',
                'description' => 'An e-commerce site with nothing hidden. Customer accounts with proper '
                    .'password hashing, a product catalogue with categories and image galleries, a cart that '
                    .'survives a closed browser because it lives in the database rather than a session, '
                    ."shipping details, and orders you can actually fulfil.\n\n"
                    .'If you have followed cart tutorials that lose everything on refresh, this is the one '
                    .'that does it properly.',
                'whats_inside' => [
                    'Customer accounts, register, sign in, password reset, order history',
                    'Product catalogue with categories, galleries and zoom',
                    'Database-backed cart that survives a closed browser',
                    'Checkout with shipping details and order records',
                    'Admin side, products, categories, orders, stock',
                    'A .sql dump with sample products and images',
                ],
                'stack' => ['PHP 8', 'MySQL', 'Bootstrap 5', 'jQuery'],
                'requirements' => [
                    'PHP 8.0 or newer with mysqli, gd and mbstring',
                    'MySQL 5.7 or newer, or MariaDB',
                    'Apache or Nginx, XAMPP, MAMP and shared hosting all work',
                ],
                'license_terms' => 'One developer, unlimited client projects. Resale of the source itself is not permitted.',
                'install_guide' => $this->plainPhpGuide('Online Shop', 'online-shop'),
            ],

            [
                'slug' => 'android-ecommerce-firebase',
                'name' => 'Android E-Commerce App, Firebase',
                'type' => 'template',
                'category' => 'Mobile',
                'price' => '260000.00',
                'version' => '1.0',
                'summary' => 'A complete Android shopping app with no backend server at all, Firestore for '
                    .'data, Firebase Auth for accounts, and it syncs across devices for free.',
                'description' => 'Everything a shop needs, with no server to rent or maintain. Firestore '
                    .'holds the catalogue, the carts and the orders; Firebase Auth handles accounts; '
                    ."storage holds the images. Sign in on a second device and the cart is already there.\n\n"
                    .'Built in Java with Android Studio, structured so the Firebase calls are behind a '
                    .'repository layer rather than scattered through the activities, which is what makes '
                    .'it worth reading rather than only running.',
                'whats_inside' => [
                    'Full Android app, catalogue, search, product detail, cart, checkout, orders',
                    'Firebase Auth sign-up and sign-in, including Google sign-in',
                    'Firestore data layer behind a repository, not sprayed through activities',
                    'Firebase Storage for product images',
                    'Firestore security rules that actually restrict access',
                    'An admin app flavour for managing the catalogue from a phone',
                ],
                'stack' => ['Android', 'Java', 'Firebase Auth', 'Cloud Firestore', 'Firebase Storage'],
                'requirements' => [
                    'Android Studio Hedgehog or newer',
                    'A free Firebase project. The setup steps are in the guide',
                    'JDK 17',
                    'An Android device or emulator on API 24 or above',
                ],
                'license_terms' => 'One developer, unlimited client projects. Resale of the source itself is not permitted.',
                'install_guide' => $this->firebaseGuide(),
            ],

            [
                'slug' => 'laravel-admin-panel-starter',
                'name' => 'Laravel Admin Panel Starter',
                'type' => 'template',
                'category' => 'Starters',
                'price' => '150000.00',
                'version' => '3.0',
                'summary' => 'The back office skeleton every system needs, users, roles, permissions, data '
                    .'grids, forms and dashboards, wired up and ready to build on.',
                'description' => 'The project I start every Laravel build from. It is not a demo: it is the '
                    .'boring 30% that every system needs before it can do anything interesting, done once '
                    ."and done properly.\n\n"
                    .'Users, roles and permissions with a real policy layer. Data grids with search, filters '
                    .'and export. Forms with validation and file uploads. An activity log so every change is '
                    .'attributable. A service layer, so the business logic is not in the controllers.',
                'whats_inside' => [
                    'Users, roles and permissions with policies, not just middleware',
                    'Data grids, search, filter, sort, paginate, export to CSV',
                    'Forms with validation, file uploads and image handling',
                    'Activity log recording who changed what and when',
                    'A service layer and a repository pattern already in place',
                    'Dashboard widgets you can copy for your own metrics',
                ],
                'stack' => ['Laravel 11', 'PHP 8.2', 'MySQL 8', 'Blade', 'Alpine.js'],
                'requirements' => [
                    'PHP 8.2 or newer, Composer 2',
                    'MySQL 8 or MariaDB 10.6+',
                    'Node 18+ for the asset build',
                ],
                'license_terms' => 'One developer, unlimited client projects. Resale of the source itself is not permitted.',
                'install_guide' => $this->laravelGuide('Laravel Admin Panel Starter', 'admin-starter'),
            ],

        ];
    }

    // the guides
    // Written per stack rather than per product: the steps that differ are the
    // ones worth writing, and the ones that do not should not drift apart.

    private function laravelGuide(string $name, string $folder): string
    {
        return <<<MD
        ## Before you start

        You need PHP 8.2+, Composer 2, MySQL 8 and Node 18+. On Windows, XAMPP or Laragon
        gives you PHP and MySQL together; on a Mac, MAMP or Herd does the same.

        Check what you have:

        ```bash
        php -v && composer -V && mysql --version && node -v
        ```

        ## 1. Unzip it

        Unzip the archive into your web root and open a terminal inside the folder:

        ```bash
        cd {$folder}
        ```

        ## 2. Install the dependencies

        ```bash
        composer install
        npm install && npm run build
        ```

        If `composer install` complains about a missing extension, install that extension
        and run it again. It is telling you exactly what is missing.

        ## 3. Make your .env

        ```bash
        cp .env.example .env
        php artisan key:generate
        ```

        Open `.env` and set your database:

        ```
        DB_DATABASE={$folder}
        DB_USERNAME=root
        DB_PASSWORD=
        ```

        ## 4. Create the database and fill it

        Create an empty database with the name you just used, then:

        ```bash
        php artisan migrate --seed
        ```

        The seeders create sample data and the first administrator.

        ## 5. Run it

        ```bash
        php artisan serve
        ```

        Open <http://localhost:8000>. Sign in with the administrator the seeder printed
        in your terminal, and **change that password before you put this anywhere public**.

        ## Putting it on a real server

        Point the web root at `public/`, not at the project folder. Then:

        ```bash
        composer install --optimize-autoloader --no-dev
        php artisan config:cache && php artisan route:cache && php artisan view:cache
        php artisan storage:link
        ```

        Set `APP_ENV=production` and `APP_DEBUG=false`. Make `storage/` and
        `bootstrap/cache/` writable by the web server user.

        ## If something goes wrong

        - **500 with no message**: `APP_DEBUG=true` in `.env`, then read `storage/logs/laravel.log`.
        - **"No application encryption key"**: you skipped `php artisan key:generate`.
        - **Blank page after deploying**: the web root is pointing at the project folder instead of `public/`.
        - **Permission denied writing to storage**: `chmod -R 775 storage bootstrap/cache`.

        Still stuck? Reply to your receipt email with what you tried and the exact error.
        MD;
    }

    private function laravelPlusFlutterGuide(string $name, string $folder): string
    {
        return $this->laravelGuide($name, $folder)."\n\n".<<<'MD'
        ## The mobile app

        The Flutter app is in `mobile/`. It talks to the API you just started.

        ```bash
        cd mobile
        flutter pub get
        ```

        Open `lib/config/api.dart` and point it at your API. On an Android emulator,
        `localhost` on your machine is `10.0.2.2` to the emulator:

        ```dart
        const baseUrl = 'http://10.0.2.2:8000/api';
        ```

        On a real phone, use your computer's LAN address instead, and make sure both are
        on the same network.

        ```bash
        flutter run
        ```

        ### Building a release APK

        ```bash
        flutter build apk --release
        ```

        The APK lands in `build/app/outputs/flutter-apk/`.

        ### If the app cannot reach the API

        - Android blocks plain HTTP by default. For local testing the project already sets
          `usesCleartextTraffic`, for production, use HTTPS and take that flag out.
        - `10.0.2.2` is only for the emulator. A real device needs your LAN IP.
        - If the API returns 401, your token is stale: sign out in the app and sign in again.
        MD;
    }

    private function plainPhpGuide(string $name, string $folder): string
    {
        return <<<MD
        ## Before you start

        You need PHP 8 and MySQL. XAMPP on Windows, MAMP on a Mac, or any shared host
        with cPanel. All of them work, and none of them need a terminal.

        ## 1. Unzip it into your web root

        - **XAMPP**: `C:\\xampp\\htdocs\\{$folder}`
        - **MAMP**: `/Applications/MAMP/htdocs/{$folder}`
        - **Shared hosting**: upload the folder into `public_html`

        ## 2. Create the database

        Open phpMyAdmin at <http://localhost/phpmyadmin>, create a database called
        `{$folder}`, select it, click **Import**, and choose `database/{$folder}.sql`
        from the archive.

        That file contains the tables and the sample data, so there is nothing to run
        by hand afterwards.

        ## 3. Point the code at it

        Open `config/database.php` and set your credentials:

        ```php
        \$host = 'localhost';
        \$user = 'root';      // your database user
        \$pass = '';          // your password, often blank on XAMPP, 'root' on MAMP
        \$name = '{$folder}';
        ```

        Also set the site address in `config/config.php`:

        ```php
        define('BASE_URL', 'http://localhost/{$folder}/');
        ```

        The trailing slash matters.

        ## 4. Open it

        <http://localhost/{$folder}/>

        The admin side is at `/admin`. The seed data includes one administrator; the
        username and password are printed in `README.md` inside the archive.
        **Change that password immediately.**

        ## 5. Folder permissions

        Uploads are written to `uploads/`. On Linux hosting:

        ```bash
        chmod -R 775 uploads
        ```

        ## If something goes wrong

        - **Blank white page**: PHP hit a fatal error with display off. Add
          `error_reporting(E_ALL); ini_set('display_errors', 1);` at the top of `index.php`
          and reload.
        - **"Access denied for user"**: the credentials in `config/database.php` are wrong.
          On MAMP the password is usually `root`, not blank.
        - **Images and links broken**: `BASE_URL` is wrong, or it is missing its trailing slash.
        - **"Table doesn't exist"**: the .sql import did not finish. Re-import and watch for
          an error at the bottom of phpMyAdmin.

        Still stuck? Reply to your receipt email with what you tried and the exact error.
        MD;
    }

    private function firebaseGuide(): string
    {
        return <<<'MD'
        ## Before you start

        Android Studio, JDK 17, and a free Firebase account. No server, no hosting bill.

        ## 1. Open the project

        Unzip the archive and open the folder in Android Studio. Let Gradle finish syncing
        before you touch anything. The first sync downloads a lot and can take a few minutes.

        ## 2. Make your own Firebase project

        1. Go to <https://console.firebase.google.com> and create a project.
        2. Add an **Android** app to it. The package name must match exactly:
           `com.mubahood.shop`. You will find it in `app/build.gradle`.
        3. Download `google-services.json`.
        4. Drop that file into the `app/` folder, replacing the placeholder that ships
           with the archive.

        Nothing will run until you do step 4: the placeholder points at a project you
        do not have access to.

        ## 3. Switch on the services

        In the Firebase console:

        - **Authentication** → Sign-in method → enable **Email/Password** (and **Google**
          if you want that button to work).
        - **Firestore Database** → Create database → start in **test mode** while you are
          developing.
        - **Storage** → Get started, so product images have somewhere to live.

        ## 4. Lock it down before you ship

        Test mode leaves your database open to the world. Replace the rules with the ones
        in `firestore.rules` from the archive:

        ```
        Firestore → Rules → paste → Publish
        ```

        Those rules let anybody read the catalogue, but only let a signed-in person read
        and write their own cart and orders.

        ## 5. Seed the catalogue

        Run the app, sign in with any account, and open the hidden admin screen:
        long-press the logo on the home screen. Add a few products, or import
        `sample-data.json` through the Firebase console.

        ## 6. Run it

        Pick a device or emulator on API 24 or above and press Run.

        ## If something goes wrong

        - **"Default FirebaseApp is not initialized"**: `google-services.json` is missing
          or in the wrong folder. It goes in `app/`, not the project root.
        - **Sign-in fails silently**: the sign-in method is not enabled in the console.
        - **PERMISSION_DENIED reading products**: your Firestore rules are stricter than
          your app expects. Compare against `firestore.rules`.
        - **Gradle sync fails**: File → Invalidate Caches and Restart, then sync again.

        Still stuck? Reply to your receipt email with what you tried and the exact error.
        MD;
    }
}
