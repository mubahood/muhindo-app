<?php

namespace App\Console\Commands;

use App\Http\Controllers\SolaviaController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Writes public/.well-known/company.json.
 *
 * It has to be a real file rather than a route. The server's own .htaccess
 * passes anything under .well-known/ straight to the filesystem so that SSL
 * validation works, which means Laravel never sees the request and the route
 * alone answers 403 in production.
 *
 * The content still comes from the controller, so the file and the route
 * cannot say different things, and a test asserts they match.
 *
 * Run it AFTER `optimize:clear` and BEFORE `config:cache`. It reads the
 * product list out of config, so running it against a cache built from the
 * previous deploy writes a manifest describing the old catalogue while the
 * pages show the new one. That happened once: the page listed six products
 * and the manifest still listed ten.
 */
class WriteCompanyManifest extends Command
{
    protected $signature = 'company:manifest';

    protected $description = 'Write the machine-readable company details to public/.well-known/company.json';

    public function handle(SolaviaController $controller): int
    {
        $json = $controller->companyJson()->getContent()."\n";

        /*
         * Written to two places on purpose.
         *
         * public/ is where the file belongs and where a normal deployment
         * serves it from. This host's document root is the application root,
         * and its .htaccess stops rewriting for anything under .well-known/ so
         * that SSL validation reaches the filesystem, which means Apache looks
         * for the file beside that .htaccess and not in public/. Writing only
         * one of the two gives a 403 on one setup or the other.
         */
        foreach ([public_path('.well-known/company.json'), base_path('.well-known/company.json')] as $path) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $json);
            $this->info('Wrote '.$path);
        }

        return self::SUCCESS;
    }
}
