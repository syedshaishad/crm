<?php

namespace Tests\Unit;

use Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_invoice_status_key_falls_back_to_draft(): void
    {
        $this->assertSame('paid', invoice_status_key('PAID'));
        $this->assertSame('draft', invoice_status_key('not-a-status'));
        $this->assertSame('draft', invoice_status_key(null));
    }

    public function test_invoice_status_label_uses_the_canonical_names(): void
    {
        $this->assertSame('Unpaid', invoice_status_label('sent'));
        $this->assertSame('Partial', invoice_status_label('partial'));
        $this->assertSame('Cancelled', invoice_status_label('cancelled'));
    }

    public function test_invoice_status_tone_maps_to_the_shared_palette(): void
    {
        $this->assertSame('blue', invoice_status_tone('sent'));
        $this->assertSame('green', invoice_status_tone('paid'));
        $this->assertSame('neutral', invoice_status_tone('cancelled'));
    }

    public function test_status_tag_uses_one_pill_and_escapes_the_label(): void
    {
        $tag = status_tag('<b>Paid</b>', 'green');

        $this->assertStringContainsString('class="inv-status st-green"', $tag);
        $this->assertStringContainsString('<i></i>', $tag);
        $this->assertStringNotContainsString('<b>Paid</b>', $tag);
        $this->assertStringContainsString('&lt;b&gt;Paid&lt;/b&gt;', $tag);
    }

    public function test_unknown_tone_falls_back_to_grey(): void
    {
        $this->assertStringContainsString('st-grey', status_tag('Maybe', 'nope'));
    }
}
