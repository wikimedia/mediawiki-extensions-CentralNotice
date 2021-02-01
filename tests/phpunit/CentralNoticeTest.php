<?php

/**
 * @group Fundraising
 * @group Database
 * @group CentralNotice
 * @covers CentralNotice
 */
class CentralNoticeTest extends PHPUnit\Framework\TestCase {
	public function testDropDownList() {
		$text = 'Weight';
		$values = range( 0, 50, 10 );
		$this->assertEquals(
			"*Weight\n**0\n**10\n**20\n**30\n**40\n**50\n",
			CentralNotice::dropDownList( $text, $values ) );
	}
}
