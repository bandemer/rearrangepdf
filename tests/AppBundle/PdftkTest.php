<?php

namespace Tests\AppBundle;

use AppBundle\Pdftk;
use AppBundle\Testablesession;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PdftkTest extends \PHPUnit\Framework\TestCase
{

    public function testConstruct()
    {
        $pdftk = new Pdftk();

        $reflection = new \ReflectionClass(get_class($pdftk));
        $prop = $reflection->getProperty('_dirPdf');
        $prop->setAccessible(true);

        $this->assertTrue(file_exists($prop->getValue($pdftk)),
            'Verzeichnis für PDF-Dateien existiert nicht');

        $reflection = new \ReflectionClass(get_class($pdftk));
        $prop = $reflection->getProperty('_dirScr');
        $prop->setAccessible(true);

        $this->assertTrue(file_exists($prop->getValue($pdftk)),
            'Verzeichnis für Screenshots existiert nicht');
    }

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
                '100'            => '100 Bytes',
                '1023'            => '1023 Bytes',
                '1024'             => '1,00 KBytes',
                '1536'             => '1,50 KBytes',
                '2048'             => '2,00 KBytes',
                '1048576'        => '1,00 MBytes',
                '1572864'        => '1,50 MBytes',
                '1073741824'     => '1,00 GBytes',
                '1610612736'     => '1,50 GBytes',
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
            'Test'            => 'Test',
            'Test mit 30 Zeichen langem Nam'
                => 'Test mit 30 Zeichen langem Nam',
            'Test mit 32 Zeichen langem Namen'
                => 'Test mit 32 Ze..n langem Namen',
        );

        foreach ($inAndOut AS $k => $v) {
            $this->assertEquals($v, $method->invokeArgs($pdftk, array($k)));
        }
    }

    /*
     * Test for PrepareUploadedFile
     */
    public function testPrepareUploadedFile()
    {
        $ufile = $this->getMockBuilder('Symfony\Component\HttpFoundation\File\UploadedFile')
            ->enableOriginalConstructor()
            ->setConstructorArgs(['tests/test.pdf', 'test.pdf'])
            ->getMock();

        $pdftk = new Pdftk();

        $this->assertTrue($pdftk->prepareUploadedFile($ufile));

    }

    /**
     *
    public function testProcessFile()
    {
        $uniqueId = date('YmdHis').'_test';
        mkdir('var/pdf/'.$uniqueId);
        copy('tests/test.pdf', 'var/pdf/'.$uniqueId.'/file.pdf');

        $session = new Testablesession();
        $session->start();
        $session->set('pdf_unique_id', $uniqueId);
        $session->save();

        $pdftk = new Pdftk();
        $this->assertTrue($pdftk->processFile(), 'Fehler! Datei wurde nicht korrekt verarbeitet');
    }


  */



}
