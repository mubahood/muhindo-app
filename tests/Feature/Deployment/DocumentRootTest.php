<?php

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * The .htaccess at the repository root, which is also the document root live.
 *
 * This is the one file in the project that PHPUnit cannot exercise — Apache
 * reads it, not Laravel — and it is also the file whose failure is the most
 * total. When it was wrong, every stylesheet, font and image on
 * muhindomubaraka.com returned Laravel's 404 page while the site itself
 * answered 200, so nothing looked broken from the application's side and no
 * test in this suite had anything to say about it. The same file, in the same
 * afternoon, was serving /artisan and storage/logs/laravel.log to anyone who
 * asked for them.
 *
 * So these are tripwires rather than tests: they read the deployed file and
 * assert the handful of properties that, if quietly lost to a merge or a
 * "tidy-up", take the live site down or open it up. Each one names the failure
 * it is standing in front of, because the point of a tripwire nobody can run
 * against Apache is that the message explains itself.
 */
class DocumentRootTest extends TestCase
{
    private function rootHtaccess(): string
    {
        $path = base_path('.htaccess');
        $this->assertFileExists($path, 'The document-root .htaccess is gone. On cPanel the document '
            .'root IS the app root, so without it Apache lists the directory and serves .env.');

        return (string) file_get_contents($path);
    }

    /**
     * The same file with its commentary removed.
     *
     * The comments explain why certain forms are wrong, which means they quote
     * those forms — an assertion that a rule is absent will otherwise match the
     * paragraph warning against it.
     */
    private function rulesOnly(): string
    {
        return implode("\n", array_filter(
            explode("\n", $this->rootHtaccess()),
            fn ($line) => ! str_starts_with(ltrim($line), '#')
        ));
    }

    public function test_public_is_resolved_before_anything_else_is_decided(): void
    {
        $rules = $this->rulesOnly();

        // Two names mean different things on either side of public/: /vendor is
        // Composer's packages OR Font Awesome, /storage is the private disk OR
        // uploaded files. Resolving public/ first is what tells them apart
        // without a special case for each. Any rule placed above this one is
        // deciding about a path before it knows which of the two it is.
        $publicLookup = strpos($rules, 'public/$1 -f');
        $frontController = strpos($rules, 'RewriteRule ^ index.php');

        $this->assertNotFalse($publicLookup, 'The public/ existence test is missing — this is the '
            .'rule that lets /images/portrait.jpg and /vendor/fa/css/all.min.css be found at all.');
        $this->assertNotFalse($frontController, 'The front-controller rule is missing.');
        $this->assertLessThan($frontController, $publicLookup,
            'The front controller now runs before public/ is consulted, so every asset on the '
            .'live site will answer with Laravel\'s 404 page.');
    }

    public function test_the_public_lookup_does_not_assume_the_app_root_is_the_document_root(): void
    {
        // %{DOCUMENT_ROOT}/public%{REQUEST_URI} is the form everyone reaches
        // for, and it works live and only live. In the development install the
        // app is a subdirectory, so it resolves to htdocs/public/muhindo-app/…
        // and matches nothing — every asset 404s locally while production
        // looks perfect, which is the most expensive way to be wrong.
        $this->assertStringNotContainsString('DOCUMENT_ROOT}/public%{REQUEST_URI}', $this->rulesOnly(),
            'The public/ lookup has been hard-coded to a root install and will not find assets '
            .'when the app is mounted in a subdirectory.');
    }

    public function test_the_front_controller_is_not_guarded_by_a_file_existence_test(): void
    {
        // The stock Laravel front-controller rule is guarded with !-f/!-d,
        // which is right when public/ is the document root and catastrophic
        // when the app root is: artisan, composer.lock and a stray
        // seed_remote.php all exist, so the guard fails and Apache hands them
        // over. Nothing outside public/ is web content here, so the guard has
        // nothing left to protect and everything to leak.
        $rules = $this->rulesOnly();
        $tail = substr($rules, (int) strpos($rules, 'public/$1 -f'));

        $this->assertStringNotContainsString('REQUEST_FILENAME} !-f', $tail,
            'A !-f guard is back in front of the front controller. Every real file at the app '
            .'root — artisan, composer.lock, .env, any deploy script left behind — becomes '
            .'directly downloadable again.');
    }

    public function test_the_front_controller_keeps_script_name_at_the_mount_point(): void
    {
        // index.php at the app root require()s public/index.php, which leaves
        // SCRIPT_NAME as /muhindo-app/index.php locally and /index.php live.
        // Rewriting straight to public/index.php instead makes SCRIPT_NAME
        // disagree with REQUEST_URI, and Laravel then fails to strip the
        // subdirectory prefix and 404s every single page.
        $this->assertFileExists(base_path('index.php'),
            'The app-root bootstrap is gone; the front-controller rewrite target no longer exists.');
        $this->assertStringContainsString('public/index.php', (string) file_get_contents(base_path('index.php')));

        $this->assertMatchesRegularExpression('/RewriteRule \^ index\.php \[L\]/', $this->rulesOnly(),
            'The front controller no longer targets the app-root bootstrap.');
    }

    public function test_the_authorization_header_is_still_passed_through(): void
    {
        // Apache does not expose it to PHP by default. Without this rule every
        // Sanctum bearer-token request 401s as though the token were wrong,
        // and nothing in the response points at the real cause.
        $this->assertStringContainsString('E=HTTP_AUTHORIZATION:%{HTTP:Authorization}', $this->rulesOnly(),
            'The Authorization pass-through is gone — the whole token API will answer 401.');
    }

    public function test_https_is_forced_everywhere_except_a_development_machine(): void
    {
        $rules = $this->rulesOnly();

        $this->assertStringContainsString('https://%{HTTP_HOST}%{REQUEST_URI}', $rules);
        $this->assertStringContainsString('localhost', $rules,
            'The HTTPS redirect no longer exempts localhost, which has no certificate — the '
            .'local site will redirect to https://localhost and stop loading.');
    }

    public function test_the_secrets_stay_denied_even_if_mod_rewrite_is_not_loaded(): void
    {
        // Every rule above lives inside <IfModule mod_rewrite.c>. If the module
        // is ever absent the whole block evaporates and the app root — .env
        // included — becomes a plain, browsable directory. These two do not
        // depend on it.
        $rules = $this->rulesOnly();

        $this->assertStringContainsString('Options -Indexes', $rules);
        $this->assertMatchesRegularExpression('/<FilesMatch.*\n.*Require all denied/', $rules,
            'The mod_rewrite-independent deny block is gone; .env is one disabled module away '
            .'from being public.');
    }

    public function test_no_deploy_script_is_sitting_in_the_document_root(): void
    {
        // A copy of seed_remote.php was left at the app root of the live server
        // and answered 200 at https://muhindomubaraka.com/seed_remote.php,
        // re-running a database seeder for anybody who found it. The rules
        // above now hide files like it, but a file that is not there cannot be
        // exposed by the next person to change them.
        $strays = array_values(array_filter(
            glob(base_path('*.php')) ?: [],
            fn ($p) => basename($p) !== 'index.php'
        ));

        $this->assertSame([], array_map('basename', $strays),
            'Loose PHP scripts are in the document root. Move them to _deploy-oneoffs/, which '
            .'is git-ignored, so they cannot be committed or copied to the server.');
    }
}
