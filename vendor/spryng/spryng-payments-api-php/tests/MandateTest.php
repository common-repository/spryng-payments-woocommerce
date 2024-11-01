<?php

require_once('BaseTest.php');

class MandateTest extends BaseTest
{
    const MANDATE_CUSTOMER_ID = '';

    public function testGetAllMandates()
    {
        $mandates = $this->client->mandate->list();
        foreach ($mandates as $mandate)
        {
            $this->assertInstanceOf('SpryngPaymentsApiPhp\Object\Mandate', $mandate);
        }

        $mandates = $this->client->mandate->list(self::MANDATE_CUSTOMER_ID);
        foreach ($mandates as $mandate)
        {
            $this->assertEquals(self::MANDATE_CUSTOMER_ID, $mandate->customer);
        }
    }
}