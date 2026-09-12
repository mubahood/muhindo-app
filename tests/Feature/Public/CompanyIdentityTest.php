<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The business behind the site, and the accounts that belong to it.
 *
 * This exists because of a live consequence: a payment provider froze the
 * account after its fraud desk could not confirm the site belonged to the
 * registered company. Every assertion here is something that reviewer checks.
 */
class CompanyIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_public_page_names_the_operating_company(): void
    {
        foreach (['/', '/about', '/e-learning', '/work', '/source-code'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('SOLAVIA GROUP LIMITED')
                ->assertSee('80048169153974');
        }
    }

    public function test_the_social_accounts_are_linked_from_every_page(): void
    {
        foreach (['/', '/about', '/e-learning'] as $path) {
            $page = $this->get($path)->assertOk();

            foreach (config('company.social') as $account) {
                $page->assertSee($account['url'], false);
            }
        }
    }

    public function test_the_social_links_open_safely_and_are_labelled(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        preg_match('#<div class="foot-social">(.*?)</div>#s', $html, $m);
        $row = $m[1] ?? '';

        $this->assertNotEmpty($row, 'there is no social row in the footer');

        foreach (config('company.social') as $account) {
            $this->assertStringContainsString($account['url'], $row);
            // A screen reader gets a name, not "link, icon".
            $this->assertStringContainsString('aria-label="'.$account['label'].'"', $row);
        }

        // rel=me is what ties a profile back to this site; noopener is hygiene.
        $this->assertSame(3, substr_count($row, 'rel="noopener me"'));
        $this->assertSame(3, substr_count($row, 'target="_blank"'));
    }

    /**
     * The exact handles. A typo here is a dead link on every page of the site,
     * and it is the sort of thing nobody notices because the icon still draws.
     */
    public function test_the_handles_are_the_right_ones(): void
    {
        $urls = array_column(config('company.social'), 'url');

        $this->assertSame([
            'https://www.facebook.com/mubahood2/',
            'https://www.instagram.com/ugnewz24/',
            'https://x.com/mubahood360',
        ], $urls);
    }

    /* Structured data ------------------------------------------------------ */

    /**
     * The Organization node used to carry a personal name, so the site said it
     * was run by an individual while the payments were taken by a limited
     * company. Anything checking the two against each other found them
     * disagreeing, which is the whole problem this fixes.
     */
    public function test_the_organization_in_the_structured_data_is_the_company(): void
    {
        $org = $this->schemaNode('Organization');

        $this->assertSame('SOLAVIA GROUP LIMITED', $org['name']);
        $this->assertSame('SOLAVIA GROUP LIMITED', $org['legalName']);
        $this->assertSame('80048169153974', $org['identifier']);
        $this->assertSame('solaviaug@gmail.com', $org['email']);
        $this->assertSame('+256783204665', $org['telephone']);
        $this->assertSame('Nansana Municipality', $org['address']['addressLocality']);
        $this->assertSame('UG', $org['address']['addressCountry']);
    }

    public function test_the_social_profiles_are_declared_as_same_as(): void
    {
        foreach (['Organization', 'Person'] as $type) {
            $node = $this->schemaNode($type);

            foreach (array_column(config('company.social'), 'url') as $url) {
                $this->assertContains($url, $node['sameAs'], "{$type} is missing {$url}");
            }
        }
    }

    public function test_the_person_is_tied_to_the_company(): void
    {
        $this->assertSame('SOLAVIA GROUP LIMITED', $this->schemaNode('Person')['worksFor']['name']);
    }

    public function test_the_structured_data_is_valid_json(): void
    {
        foreach ($this->schemaNodes() as $node) {
            $this->assertArrayHasKey('@type', $node);
            $this->assertSame('https://schema.org', $node['@context']);
        }
    }

    /* The three legal pages a reviewer opens ------------------------------- */

    public function test_the_legal_pages_all_load(): void
    {
        foreach (['/solavia/refund-policy', '/solavia/terms', '/solavia/privacy',
            '/terms', '/privacy'] as $path) {
            $this->get($path)->assertOk();
        }

        // The URL already in circulation with the payment providers.
        $this->get('/refund-policy')->assertRedirect('/solavia/refund-policy');
    }

    /** @return array<int,array<string,mixed>> */
    private function schemaNodes(): array
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return array_map(
            fn (string $json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR),
            $m[1],
        );
    }

    /** @return array<string,mixed> */
    private function schemaNode(string $type): array
    {
        foreach ($this->schemaNodes() as $node) {
            if (($node['@type'] ?? null) === $type) {
                return $node;
            }
        }

        $this->fail("no {$type} node in the structured data");
    }
}
