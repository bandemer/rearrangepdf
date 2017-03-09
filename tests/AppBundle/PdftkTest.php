<?php

namespace Tests\AppBundle;

use AppBundle\Pdftk;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PdftkTest extends \PHPUnit_Framework_TestCase
{
	
	/**
	 * Test for private function readableFileSize
	 */
	public function testReadableFileSize()
	{
		$pdftk = new Pdftk();
		
		$reflection = new \ReflectionClass(get_class($pdftk));
		$method = $reflection->getMethod('_readableFileSize');
		$method->setAccessible(true);
		
		$inAndOut = array(
				'100'			=> '100 Bytes',
				'1023'			=> '1023 Bytes',
				'1024' 			=> '1,00 KBytes',
				'1536' 			=> '1,50 KBytes',
				'2048' 			=> '2,00 KBytes',
				'1048576'		=> '1,00 MBytes',
				'1572864'		=> '1,50 MBytes',
				'1073741824' 	=> '1,00 GBytes',				
				'1610612736' 	=> '1,50 GBytes',
		);

		foreach ($inAndOut AS $k => $v) {
			$this->assertEquals($v, $method->invokeArgs($pdftk, array($k)));
		}
	}
	
	/**
	 * Test for private function shortenFileName
	 */
	public function testShortenFileName()
	{
		$pdftk = new Pdftk();
	
		$reflection = new \ReflectionClass(get_class($pdftk));
		$method = $reflection->getMethod('_shortenFileName');
		$method->setAccessible(true);
	
		$inAndOut = array(
			'Test'			=> 'Test',
			'Test mit 30 Zeichen langem Nam'	
				=> 'Test mit 30 Zeichen langem Nam',
			'Test mit 32 Zeichen langem Namen' 	
				=> 'Test mit 32 Ze..n langem Namen',
		);
	
		foreach ($inAndOut AS $k => $v) {
			$this->assertEquals($v, $method->invokeArgs($pdftk, array($k)));
		}
	}
}
