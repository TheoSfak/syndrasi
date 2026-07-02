<?php

use PHPUnit\Framework\TestCase;

/**
 * SynDrasi is white-labelled per municipality/authority type (Δήμος,
 * Πολιτική Προστασία, Πυροσβεστική, Λιμενικό). These helpers pick the
 * right terminology set and must never let an unrecognized type through.
 */
final class AuthorityContextTest extends TestCase
{
    public function testNormalizeAcceptsKnownTypes(): void
    {
        $this->assertSame('municipality', normalize_authority_type('municipality'));
        $this->assertSame('fire_service', normalize_authority_type('fire_service'));
    }

    public function testNormalizeFallsBackToMunicipalityForUnknownTypes(): void
    {
        $this->assertSame('municipality', normalize_authority_type('bogus_type'));
        $this->assertSame('municipality', normalize_authority_type(''));
    }

    public function testAuthorityDefaultsMatchesTerminology(): void
    {
        $this->assertSame('Αποστολή', authority_defaults('fire_service')['event_singular']);
        $this->assertSame('Δράση', authority_defaults('municipality')['event_singular']);
    }

    public function testAuthorityDefaultsFallsBackForUnknownType(): void
    {
        $this->assertSame(authority_defaults('municipality'), authority_defaults('does_not_exist'));
    }

    public function testAllAuthorityOptionsHaveTheSameShape(): void
    {
        $requiredKeys = ['label', 'prefix', 'short', 'icon', 'event_singular', 'event_plural',
            'event_plural_lc', 'event_new', 'team_plural', 'team_singular', 'admin_role'];

        foreach (authority_options() as $type => $option) {
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $option, "authority_options()['$type'] is missing '$key'");
            }
        }
    }
}
