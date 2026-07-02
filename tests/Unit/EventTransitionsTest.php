<?php

use PHPUnit\Framework\TestCase;

/**
 * Guards the event status state machine (Event::canTransition). This is the
 * one piece of business logic the codebase already centralized correctly —
 * these tests exist to keep it that way as statuses evolve.
 */
final class EventTransitionsTest extends TestCase
{
    public function testDraftCanOpenOrCancel(): void
    {
        $this->assertTrue(Event::canTransition('draft', 'open'));
        $this->assertTrue(Event::canTransition('draft', 'cancelled'));
    }

    public function testDraftCannotSkipToActive(): void
    {
        $this->assertFalse(Event::canTransition('draft', 'active'));
    }

    public function testActiveCanCloseOrComplete(): void
    {
        $this->assertTrue(Event::canTransition('active', 'closed'));
        $this->assertTrue(Event::canTransition('active', 'completed'));
    }

    public function testActiveCannotGoBackward(): void
    {
        $this->assertFalse(Event::canTransition('active', 'draft'));
        $this->assertFalse(Event::canTransition('active', 'open'));
    }

    public function testCancelledIsTerminal(): void
    {
        foreach (Event::VALID_STATUSES as $status) {
            $this->assertFalse(Event::canTransition('cancelled', $status), "cancelled -> $status should be blocked");
        }
    }

    public function testCompletedCanStillBeCancelled(): void
    {
        // Municipalities need to correct a mistakenly-completed event.
        $this->assertTrue(Event::canTransition('completed', 'cancelled'));
    }

    public function testUnknownFromStatusHasNoTransitions(): void
    {
        $this->assertFalse(Event::canTransition('not_a_real_status', 'open'));
    }
}
