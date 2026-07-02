<?php

use PHPUnit\Framework\TestCase;

/** Covers the pure, side-effect-free helpers in app/Helpers/functions.php. */
final class HelpersTest extends TestCase
{
    public function testEscapesHtmlAndQuotes(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            e('<script>alert(1)</script>')
        );
        $this->assertSame('O&#039;Brien &quot;test&quot;', e('O\'Brien "test"'));
    }

    public function testPasswordErrorEnforcesMinLength(): void
    {
        $this->assertNotNull(password_error('short1'));
        $this->assertNull(password_error('longenough1'));
    }

    public function testPasswordErrorChecksConfirmationMatch(): void
    {
        $this->assertNotNull(password_error('longenough1', 'longenough2'));
        $this->assertNull(password_error('longenough1', 'longenough1'));
    }

    public function testPasswordErrorSkipsConfirmationCheckWhenNull(): void
    {
        $this->assertNull(password_error('longenough1', null));
    }

    public function testGreekStatusTranslatesKnownStatuses(): void
    {
        $this->assertSame('Ενεργή', greek_status('active'));
        $this->assertSame('Ακυρωμένη', greek_status('cancelled'));
    }

    public function testGreekStatusFallsBackToRawValue(): void
    {
        $this->assertSame('not_a_status', greek_status('not_a_status'));
    }

    public function testStatusColorFallsBackToSecondary(): void
    {
        $this->assertSame('danger', status_color('cancelled'));
        $this->assertSame('secondary', status_color('not_a_status'));
    }

    public function testGrDateFormatsOrFallsBackToDash(): void
    {
        $this->assertSame('02/07/2026', gr_date('2026-07-02'));
        $this->assertSame('—', gr_date(null));
        $this->assertSame('—', gr_date(''));
        $this->assertSame('—', gr_date('not-a-real-date'));
    }

    public function testGrDatetimeAndTime(): void
    {
        $this->assertSame('02/07/2026 14:30', gr_datetime('2026-07-02 14:30:00'));
        $this->assertSame('14:30', gr_time('2026-07-02 14:30:00'));
    }

    public function testGrNumberUsesGreekThousandsAndDecimalSeparators(): void
    {
        $this->assertSame('1.234.567,89', gr_number(1234567.891, 2));
        $this->assertSame('0', gr_number(0));
    }
}
