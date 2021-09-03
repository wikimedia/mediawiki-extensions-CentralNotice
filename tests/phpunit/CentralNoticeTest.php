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

	public function provideSearchTerms() {
		return [
			'empty' => [ '', '' ],
			'whitespace normalization' => [ "\n\n   a\tb   \r", 'a b' ],
			'allowed characters' => [ 'a-to-z_A-to-Z_0-to-9', 'a-to-z_A-to-Z_0-to-9' ],
			'bad characters are trimmed' => [ '!("a")', 'a' ],
			'bad characters cut off terms' => [ 'what:happens (??and??here??)', 'what and' ],
		];
	}

	/**
	 * @dataProvider provideSearchTerms
	 */
	public function testSanitizeSearchTerms( string $terms, string $expected ) {
		$instance = new CentralNotice();
		$this->assertSame( $expected, $instance->sanitizeSearchTerms( $terms ) );
	}

}
