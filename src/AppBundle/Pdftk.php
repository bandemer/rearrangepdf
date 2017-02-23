<?php

namespace AppBundle; 

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Filesystem\Filesystem;

class Pdftk {
	
	/**
	 * directory for storing pdf files
	 * @var string
	 */
	private $_dirPdf = '../var/pdf/';
	
	/**
	 * directory for storing screenshots
	 * @var string
	 */
	private $_dirScr = 'screenshots/';

	/**
	 * Parse uploaded file
	 * 
	 * @param Symfony\Component\HttpFoundation\File\UploadedFile $file
	 * @return bool
	 */
	public function prepareFile($file) 
	{
		$session = new Session();
		$session->set('pdf_original_filename', $file->getClientOriginalName());
			
		$uniqueId = date('Ymdhis').'_'.uniqid();
		
		$session->set('pdf_unique_id', $uniqueId);
			
		$fs = new Filesystem();
			
		try {
			$fs->mkdir($this->_dirScr. $uniqueId);
			$fs->mkdir($this->_dirPdf, $uniqueId);
			
		} catch (IOExceptionInterface $e) {
			return false;	
		}
			
		$file->move($this->_dirPdf.$uniqueId, 'file.pdf');
			
		$pdfFile = $this->_dirPdf.$uniqueId.'/file.pdf';
		$dataFile = $this->_dirPdf.$uniqueId.'/file_data.txt';
		
		//Check if PDF
		if (substr(file_get_contents($pdfFile), 0, 4) != '%PDF') {
			$session->getFlashBag()
				->add('error', 'Fehler: Kein gültiges PDF-Dokument!');
			return false;
		}
		
		$fileSize = filesize($pdfFile);
		
		$session->set('pdf_filesize', $fileSize);
			
		shell_exec('pdftk '.$pdfFile.' dump_data_utf8 output '.$dataFile);
							
		$data = explode("\n", file_get_contents($dataFile));
		$pageCount = 0;
		$pageCountPattern = "/^NumberOfPages: ([0-9]+)$/";
		foreach ($data AS $row) {
			if (preg_match($pageCountPattern, $row)) {
				$pageCount = (int) preg_replace($pageCountPattern, "\\1", $row);
				break;
			}
		}
							
		$pages = array();
							
		for ($i = 1; $i <= $pageCount; $i++) {
								
			$screenshot = $this->_dirScr.$uniqueId.'/page_'.
				str_pad($i, 4, '0', STR_PAD_LEFT).'.jpg';
										
			shell_exec('convert -thumbnail 400x -colorspace srgb '.
				'-background white -flatten '.$pdfFile.'['.($i-1).'] '.$screenshot);
		
			$pages[$i] = $screenshot;
		}
		
		$session->set('pdf_pages', $pages);
		
		return true;
	}	
}