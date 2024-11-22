<?php

namespace SheavesCapital\RingCentral\Tests;

use SheavesCapital\RingCentral\RingCentral;

class RingCentralServiceProviderTest extends TestCase {
    /** @test */
    public function it_resolves_from_the_service_container() {
        $ringCentral = app('ringcentral');

        $this->assertInstanceOf(RingCentral::class, $ringCentral);
    }
}
