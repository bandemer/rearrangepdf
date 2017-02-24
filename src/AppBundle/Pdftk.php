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
			$session->getFlashBag()
				->add('error', 'Fehler: Verzeichnisse konnten nicht '.
					'angelegt werden!');
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
		
		$fileSize = $this->_readableFileSize(filesize($pdfFile));
		
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
				'-background white -flatten '.$pdfFile.'['.($i-1).'] '.
				$screenshot);
		
			$pages[$i] = $screenshot;
		}
		
		$session->set('pdf_pages', $pages);
		
		return true;
	}	
	
	/**
	 * Extract and output a single page as PDF
	 * 
	 * @param int $page
	 */
	public function extractPage($page)
	{
		$fs = new Filesystem();
		$session = new Session();
		
		$pdfFileName = $this->_dirPdf.
			$session->get('pdf_unique_id').'/file.pdf';
		
		$pageFileName = $this->_dirPdf.$session->get('pdf_unique_id').
			'/file_page_'.str_pad($page, 4, '0', STR_PAD_LEFT).'.pdf';
			
		if (!$fs->exists($pageFileName)) {
		
			shell_exec('pdftk '.$pdfFileName.' burst output '.
				$this->_dirPdf.$session->get('pdf_unique_id').
				'/file_page_%04d.pdf');
		}
			
		$downloadFileName = str_replace('.pdf', '_seite_'.
			str_pad($page, 4, '0', STR_PAD_LEFT).'.pdf',
			$session->get('pdf_original_filename'));
			
		$output = file_get_contents($pageFileName);
			
		header('Content-type: application/octet-stream');
		header('Content-Disposition: attachment; filename="'.
				$downloadFileName.'"');
		echo($output);
		exit;
	}
	
	/**
	 * Create and download a screenshot
	 *
	 * @param int $page
	 */
	public function getScreenshot($page)
	{
		$session = new Session();
	
		$scrFileName = $this->_dirScr.$session->get('pdf_unique_id').
			'/page_'.str_pad($page, 4, '0', STR_PAD_LEFT).'.jpg';
	
		$fs = new Filesystem();
		
		if ($fs->exists($scrFileName)) {
	
			$downloadFileName = str_replace('.pdf', '_seite_'.
				str_pad($page, 4, '0', STR_PAD_LEFT).'.jpg',
				$session->get('pdf_original_filename'));
			
			$output = file_get_contents($scrFileName);
			
			header('Content-type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.
				$downloadFileName.'"');
			echo($output);
			exit;
		}
	}
	
	/**
	 * Download complete PDF
	 *
	 */
	public function download()
	{
		$session = new Session();
		$fs = new Filesystem();
		
		$pdfFileName = $this->_dirPdf.
			$session->get('pdf_unique_id').'/file.pdf';
	
		if ($fs->exists($pdfFileName)) {
	
			$output = file_get_contents($pdfFileName);
				
			header('Content-type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.
					$session->get('pdf_original_filename').'"');
			echo($output);
			exit;
		} 
		return false;		
	}
	
	/**
	 * Format file size
	 * 
	 * @param int $bytes
	 * @return string
	 */
	private function _readableFileSize($bytes)
	{
		$sizes = array('Bytes', 'KBytes', 'MBytes', 'GBytes');		
		$count = 0;
		$temp = $bytes;
		while ($temp >= 1024) {
			$temp = floor($temp / 1024);
			++$count;
		}
		if ($count > 0) {
			$fileSize = round($bytes / pow(1024, $count), 2);
			return number_format($fileSize, 2, ',', '.').' '.$sizes[$count];
		} else {
			return $bytes.' '.$sizes[$count];
		}
	}
	
	/**
	 * Shorten file name
	 * 
	 * @param string $name
	 * @return string $formatted
	 */
	private function _shortenFileName($fileName) 
	{
		$returnString = $fileName;
		if (strlen($fileName) > 30) {
			$returnString = mb_substr($fileName,0, 14).'..'.
				mb_substr($fileName, strlen($fileName)-14);
		}
		return $returnString;
	}
	
}