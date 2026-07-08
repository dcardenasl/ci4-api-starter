<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Traits\LocalizedEmailSubjectResolver;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * LocalizedEmailSubjectResolver Trait Tests
 *
 * Covers normalizeLocale() fallback rules and subjectForLocale()'s
 * temporary-swap/restore behavior, shared by PasswordResetService,
 * VerificationService, UserInvitationService, ApproveUserAction, and
 * RegisterUserAction.
 */
class LocalizedEmailSubjectResolverTest extends CIUnitTestCase
{
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new class () {
            use LocalizedEmailSubjectResolver;

            public function normalizeLocalePublic(?string $locale): string
            {
                return $this->normalizeLocale($locale);
            }

            public function subjectForLocalePublic(string $line, string $locale): string
            {
                return $this->subjectForLocale($line, $locale);
            }
        };
    }

    // ==================== normalizeLocale() ====================

    public function testNormalizeLocaleAcceptsSupportedLocale(): void
    {
        $this->assertSame('es', $this->subject->normalizeLocalePublic('es'));
        $this->assertSame('en', $this->subject->normalizeLocalePublic('en'));
    }

    public function testNormalizeLocaleIsCaseInsensitiveAndTrimsWhitespace(): void
    {
        $this->assertSame('es', $this->subject->normalizeLocalePublic(' ES '));
        $this->assertSame('en', $this->subject->normalizeLocalePublic("EN\n"));
    }

    public function testNormalizeLocaleFallsBackToDefaultForUnsupportedLocale(): void
    {
        $default = (string) config('App')->defaultLocale;

        $this->assertSame($default, $this->subject->normalizeLocalePublic('fr'));
        $this->assertSame($default, $this->subject->normalizeLocalePublic('xx-not-a-locale'));
    }

    public function testNormalizeLocaleFallsBackToDefaultForNullOrEmpty(): void
    {
        $supported = config('App')->supportedLocales;

        $resultForNull = $this->subject->normalizeLocalePublic(null);
        $resultForEmpty = $this->subject->normalizeLocalePublic('');
        $resultForBlank = $this->subject->normalizeLocalePublic('   ');

        // Falls back to the current request locale if supported, else the app default —
        // either way it must land on a configured, supported locale.
        $this->assertContains($resultForNull, $supported);
        $this->assertContains($resultForEmpty, $supported);
        $this->assertContains($resultForBlank, $supported);
    }

    // ==================== subjectForLocale() ====================

    public function testSubjectForLocaleRendersLineInRequestedLocale(): void
    {
        $subjectEn = $this->subject->subjectForLocalePublic('Email.passwordReset.subject', 'en');
        $subjectEs = $this->subject->subjectForLocalePublic('Email.passwordReset.subject', 'es');

        $this->assertSame('Reset Your Password', $subjectEn);
        $this->assertSame('Restablecer tu Contraseña', $subjectEs);
        $this->assertNotSame($subjectEn, $subjectEs);
    }

    public function testSubjectForLocaleRestoresPreviousLocaleAfterwards(): void
    {
        service('request')->setLocale('en');
        service('language')->setLocale('en');

        $this->subject->subjectForLocalePublic('Email.passwordReset.subject', 'es');

        $this->assertSame('en', service('request')->getLocale());
        $this->assertSame('en', service('language')->getLocale());
    }

    public function testSubjectForLocaleRestoresPreviousLocaleEvenAfterMultipleCalls(): void
    {
        service('request')->setLocale('es');
        service('language')->setLocale('es');

        $this->subject->subjectForLocalePublic('Email.verification.subject', 'en');
        $this->subject->subjectForLocalePublic('Email.invitation.subject', 'en');

        $this->assertSame('es', service('request')->getLocale());
        $this->assertSame('es', service('language')->getLocale());
    }
}
