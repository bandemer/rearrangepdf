<?php

namespace App;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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
     * Base URL for page screenshots
     * @var string
     */
    private $urlScr = 'screenshots/';

    /**
     * Session
     * @var SessionInterface
     */
    private $session;

    /**
     * Logger
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Pdftk constructor
     *
     * @param SessionInterface $session
     */
    public function __construct(SessionInterface $session, LoggerInterface $logger)
    {
        $this->session = $session;

        $this->logger = $logger;

        $basePath = __DIR__.'/../';

        $this->_dirPdf = realpath($basePath.'var/pdf/').'/';
        $this->_dirScr = realpath($basePath.'public/screenshots/').'/';
    }

    /**
     * Log shell command and returned output
     *
     * @param string $command
     * @param string $output
     */
    private function logShellCommand(string $command, string $output)
    {
        $this->logger->info('Sent shell command: '.$command);
        $this->logger->info('Received output: '.$output);
    }

    /**
     * Check requirements on Server
     */
    public function checkRequirements() : array
    {
        $errors = [];

        //Check for ImageMagick Version 6
        $command = 'convert --version';
        $output = shell_exec($command);
        $this->logShellCommand($command, $output);
        if (!preg_match('/Version: ImageMagick 6\./', $output)) {
            $errors[] = 'ImageMagick Version 6 is required';
        }

        //Check for PDFTK Verision 2
        $command = 'pdftk --version';
        $this->logger->info('Send shell');
        $output = shell_exec($command);
        $this->logShellCommand($command, $output);
        if (!preg_match('/pdftk 2\./', $output)) {
            $errors[] = 'PDFTK Version 2 is required';
        }

        //check permissions for screenshots directory
        if(!is_dir($this->_dirScr) OR !is_writable($this->_dirScr)) {
            $errors[] = 'Directory public/screenshots does not exist or is not writable';
        }

        //check permissions for PDF directory
        if (!is_dir($this->_dirPdf) OR !is_writable($this->_dirPdf)) {
            $errors[] = 'Directory var/pdf does not exist or is not writable';
        }

        return $errors;
    }

    /**
     * Prepare uploaded file
     *
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file
     * @return bool
     */
    public function prepareUploadedFile(UploadedFile $file)
    {
        $this->session->set('pdf_original_filename', $file->getClientOriginalName());
        $this->session->set('pdf_shorten_filename',
            $this->_shortenFileName($file->getClientOriginalName()));

        $uniqueId = date('YmdHis').'_'.uniqid();

        $this->session->set('pdf_unique_id', $uniqueId);
        $this->session->save();

        $fs = new Filesystem();

        try {
            $fs->mkdir($this->_dirScr.$uniqueId);
            $fs->mkdir($this->_dirPdf.$uniqueId);

            $file->move($this->_dirPdf.$uniqueId, 'file.pdf');

        } catch (\Exception $e) {

            $message = 'Fehler: Verzeichnisse konnten nicht angelegt werden!';
            $this->session->getFlashBag()->add('error', $message);
            $this->logger->error($message);
            return false;
        }
        return true;
    }

    /**
     * process a pdf file
     * @return boolean
     */
    public function processFile() : bool
    {
        $pdfFile = $this->_dirPdf.
            $this->session->get('pdf_unique_id').'/file.pdf';

        $dataFile = $this->_dirPdf.
            $this->session->get('pdf_unique_id').'/file_data.txt';

        //Check if PDF
        if (substr(file_get_contents($pdfFile), 0, 4) != '%PDF') {
            $message = 'Fehler: Kein gültiges PDF-Dokument!';
            $this->session->getFlashBag()->add('error', $message);
            $this->logger->error($message);
            return false;
        }

        //delete screenshots and page pdf, if pages exist
        if (is_array($this->session->get('pdf_pages'))) {
            $pages = $this->session->get('pdf_pages');
            $fs = new Filesystem();

            foreach ($pages AS $pk => $pv) {

                //delete Screenshot
                if ($fs->exists($pv)) {
                    $fs->remove($pv);
                }

                //delete PDF page
                $pageFile = $this->_dirPdf.$this->session->get('pdf_unique_id').
                    '/file_page_'.str_pad($pk, 4, '0', STR_PAD_LEFT).'.pdf';
                if ($fs->exists($pageFile)) {
                    $fs->remove($pageFile);
                }
            }
        }

        //Dateigröße ermitteln
        $fileSize = $this->_readableFileSize(filesize($pdfFile));
        $this->session->set('pdf_filesize', $fileSize);
        $this->session->save();

        //Anzahl der Seiten ermitteln
        $command = 'pdftk '.$pdfFile.' dump_data_utf8 output '.$dataFile.' verbose dont_ask';
        $output = shell_exec($command);
        $this->logShellCommand($command, $output);

        $data = explode("\n", file_get_contents($dataFile));
        $pageCount = 0;
        $pageCountPattern = "/^NumberOfPages: ([0-9]+)$/";
        foreach ($data AS $row) {
            if (preg_match($pageCountPattern, $row)) {
                $pageCount = (int) preg_replace($pageCountPattern, "\\1", $row);
                break;
            }
        }
        if ($pageCount == 0) {
            return false;
        }

        $pages = array();

        //Screenshots erzeugen
        for ($i = 1; $i <= $pageCount; $i++) {

            $screenshot = $this->session->get('pdf_unique_id').
                '/page_'.str_pad($i, 4, '0', STR_PAD_LEFT).'.jpg';

            $command = 'convert -thumbnail 400x -colorspace srgb '.
                '-background white -flatten '.$pdfFile.'['.($i-1).'] '.
                $this->_dirScr.$screenshot;
            $output = shell_exec($command);
            if (is_null($output)) {
                $output = '';
            }
            $this->logShellCommand($command, $output);

            $pages[$i] = $this->urlScr.$screenshot;
        }

        $this->session->set('pdf_pages', $pages);
        $this->session->save();

        return true;
    }

    /**
     * Extract and output a single page as PDF
     *
     * @param int $page
     */
    public function extractPage(int $page)
    {
        $fs = new Filesystem();

        $pdfFileName = $this->_dirPdf.
            $this->session->get('pdf_unique_id').'/file.pdf';

        $pageFileName = $this->_dirPdf.$this->session->get('pdf_unique_id').
            '/file_page_'.str_pad($page, 4, '0', STR_PAD_LEFT).'.pdf';

        if (!$fs->exists($pageFileName)) {

            $command = 'pdftk '.$pdfFileName.' burst output '.
                $this->_dirPdf.$this->session->get('pdf_unique_id').
                '/file_page_%04d.pdf verbose dont_ask';
            $output = shell_exec($command);
            $this->logShellCommand($command, $output);
        }

        $downloadFileName = str_replace('.pdf', '_seite_'.
            str_pad($page, 4, '0', STR_PAD_LEFT).'.pdf',
            $this->session->get('pdf_original_filename'));

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
    public function getScreenshot(int $page)
    {
        $scrFileName = $this->_dirScr.$this->session->get('pdf_unique_id').
            '/page_'.str_pad($page, 4, '0', STR_PAD_LEFT).'.jpg';

        $fs = new Filesystem();

        if ($fs->exists($scrFileName)) {

            $downloadFileName = str_replace('.pdf', '_seite_'.
                str_pad($page, 4, '0', STR_PAD_LEFT).'.jpg',
                $this->session->get('pdf_original_filename'));

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
        $fs = new Filesystem();

        $pdfFileName = $this->_dirPdf.
            $this->session->get('pdf_unique_id').'/file.pdf';

        if ($fs->exists($pdfFileName)) {

            $output = file_get_contents($pdfFileName);

            header('Content-type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.
                    $this->session->get('pdf_original_filename').'"');
            echo($output);
            exit;
        }
        return false;
    }

    /**
     * Delete a page
     *
     * @param int $page
     */
    public function delete(int $page)
    {
        $pdfFileName = $this->_dirPdf.
            $this->session->get('pdf_unique_id').'/file.pdf';

        //make a backup of file
        $fs = new Filesystem();
        $fs->copy($pdfFileName, str_replace('.pdf', '.bac.pdf', $pdfFileName));

        //delete page
        $pages = $this->session->get('pdf_pages');
        $option = '';
        if ($page == 1) {
            $option = '2-end';
        } elseif ($page == count($pages)) {
            $option = '1-'.(count($pages)-1);
        } else {
            $option = '1-'.($page-1).' '.($page+1).'-end';
        }
        $command = 'pdftk '.$pdfFileName.' cat '.$option.' output '.
            str_replace('.pdf', '.new.pdf', $pdfFileName).' verbose dont_ask';
        $output = shell_exec($command);
        $this->logShellCommand($command, $output);

        //remove original file and rename new file
        $fs->remove($pdfFileName);
        $fs->rename(str_replace('.pdf', '.new.pdf', $pdfFileName),
            $pdfFileName);

        return $this->processFile();
    }

    /**
     * Move a page
     *
     * @param string $direction
     * @param int $page
     */
    public function move(string $direction, int $page)
    {
        $possibleDirections = ['up', 'down'];
        if(!in_array($direction, $possibleDirections)) {
            throw new \Exception('Direction must be one of "up" or "down"');
        }

        $pdfFileName = $this->_dirPdf.
            $this->session->get('pdf_unique_id').'/file.pdf';

        //make a backup of file
        $fs = new Filesystem();
        $fs->copy($pdfFileName, str_replace('.pdf', '.bac.pdf', $pdfFileName));

        //Burst, if pdf-pages not yet exist
        $doBurst = false;
        if (is_array($this->session->get('pdf_pages'))) {

            foreach ($this->session->get('pdf_pages') AS $pk => $pv) {
                if ($fs->exists($this->_dirPdf.$this->session->get('pdf_unique_id').'file_page_'.
                    str_pad($pk, 4, '0', STR_PAD_LEFT).'.pdf') == false) {
                    $doBurst = true;
                    break;
                }
            }
        } else {
            $doBurst = true;
        }
        if ($doBurst) {
            $command = 'pdftk '.$pdfFileName.' burst output '.
                $this->_dirPdf.$this->session->get('pdf_unique_id').
                '/file_page_%04d.pdf verbose dont_ask';
            $output = shell_exec($command);
            $this->logShellCommand($command, $output);
        }
        $pages = $this->session->get('pdf_pages');

        //Rename
        if ($direction == 'up' AND $page > 1) {

            $fs->rename($this->_dirPdf.$this->session->get('pdf_unique_id').
                '/file_page_'.str_pad($page-1, 4, '0', STR_PAD_LEFT).'.pdf',
                $this->_dirPdf.$this->session->get('pdf_unique_id').
                '/file_page_'.str_pad($page-1, 4, '0', STR_PAD_LEFT).'.pdf.bac');
            $fs->rename($this->_dirPdf.$this->session->get('pdf_unique_id').
                '/file_page_'.str_pad($page, 4, '0', STR_PAD_LEFT).'.pdf',
                $this->_dirPdf.$this->session->get('pdf_unique_id').
                '/file_page_'.str_pad($page-1, 4, '0', STR_PAD_LEFT).'.pdf');
            $fs->rename($this->_dirPdf.$this->session->get('pdf_unique_id').
                '/file_page_'.str_pad($page-1, 4, '0', STR_PAD_LEFT).'.pdf.bac',
                $this->_dirPdf.$this->session->get('pdf_unique_id').
                '/file_page_'.str_pad($page, 4, '0', STR_PAD_LEFT).'.pdf');

        } elseif ($direction == 'down' AND $page < (count($pages))) {

            $fs->rename($this->_dirPdf.$this->session->get('pdf_unique_id').
                '/file_page_'.str_pad($page+1, 4, '0', STR_PAD_LEFT).'.pdf',
                $this->_dirPdf.$this->session->get('pdf_unique_id').
                '/file_page_'.str_pad($page+1, 4, '0', STR_PAD_LEFT).'.pdf.bac');
            $fs->rename($this->_dirPdf.$this->session->get('pdf_unique_id').
                '/file_page_'.str_pad($page, 4, '0', STR_PAD_LEFT).'.pdf',
                $this->_dirPdf.$this->session->get('pdf_unique_id').
                '/file_page_'.str_pad($page+1, 4, '0', STR_PAD_LEFT).'.pdf');
            $fs->rename($this->_dirPdf.$this->session->get('pdf_unique_id').
                '/file_page_'.str_pad($page+1, 4, '0', STR_PAD_LEFT).'.pdf.bac',
                $this->_dirPdf.$this->session->get('pdf_unique_id').
                '/file_page_'.str_pad($page, 4, '0', STR_PAD_LEFT).'.pdf');
        }

        //Join
        $command = 'pdftk ';
        foreach ($this->session->get('pdf_pages') AS $pk => $pv) {
            $command .= $this->_dirPdf.$this->session->get('pdf_unique_id').'/file_page_'.
                str_pad($pk, 4, '0', STR_PAD_LEFT).'.pdf ';
        }
        $command .= 'cat output '.
            $this->_dirPdf.$this->session->get('pdf_unique_id').
            '/file.pdf verbose dont_ask';

        $output = shell_exec($command);
        $this->logShellCommand($command, $output);

        $fs->remove($this->_dirPdf.$this->session->get('pdf_unique_id').
            '/file.bac.pdf');

        return $this->processFile();
    }

    /**
     * Rotate a page
     *
     * @param string $direction
     * @param int $page
     */
    public function rotate(string $direction, int $page)
    {
        $possibleDirections = ['east', 'west'];
        if(!in_array($direction, $possibleDirections)) {
            throw new \Exception('Direction must be one of "left" or "right"');
        }

        $pdfFileName = $this->_dirPdf.
            $this->session->get('pdf_unique_id').'/file.pdf';

        //make a backup of file
        $fs = new Filesystem();
        $fs->copy($pdfFileName, str_replace('.pdf', '.bac.pdf', $pdfFileName));

        //Burst, if pdf-pages not yet exist
        $doBurst = false;
        if (is_array($this->session->get('pdf_pages'))) {

            foreach ($this->session->get('pdf_pages') AS $pk => $pv) {
                if ($fs->exists($this->_dirPdf.$this->session->get('pdf_unique_id').'file_page_'.
                        str_pad($pk, 4, '0', STR_PAD_LEFT).'.pdf') == false) {
                    $doBurst = true;
                    break;
                }
            }
        } else {
            $doBurst = true;
        }
        if ($doBurst) {
            $command = 'pdftk '.$pdfFileName.' burst output '.
                $this->_dirPdf.$this->session->get('pdf_unique_id').
                '/file_page_%04d.pdf verbose dont_ask';
            $output = shell_exec($command);
            $this->logShellCommand($command, $output);
        }


        //Rotate page
        $pageFile = $this->_dirPdf.$this->session->get('pdf_unique_id').
            '/file_page_'.str_pad($page+1, 4, '0', STR_PAD_LEFT).'.pdf';

        $fs->rename($pageFile, $pageFile.'.bac.pdf');

        $command = 'pdftk '.$pageFile.'bac.pdf cat 1'.$direction.' output '.$pageFile.' verbose dont_ask';
        $output = shell_exec($command);
        $this->logShellCommand($command, $output);

        $fs->remove($page.'.bac.pdf');

        //Refresh screenshot
        $command = 'convert -thumbnail 400x -colorspace srgb '.
            '-background white -flatten '.$pageFile.'[0] '.
            $this->_dirScr.$screenshot;

        //Join Pages
        $command = 'pdftk ';
        foreach ($this->session->get('pdf_pages') AS $pk => $pv) {
            $command .= $this->_dirPdf.$this->session->get('pdf_unique_id').'/file_page_'.
                str_pad($pk, 4, '0', STR_PAD_LEFT).'.pdf ';
        }
        $command .= 'cat output '.
            $this->_dirPdf.$this->session->get('pdf_unique_id').
            '/file.pdf verbose dont_ask';

        $output = shell_exec($command);
        $this->logShellCommand($command, $output);

        $fs->remove($this->_dirPdf.$this->session->get('pdf_unique_id').
            '/file.bac.pdf');

        return $this->processFile();
    }

    /**
     * Format file size
     *
     * @param int $bytes
     * @return string
     */
    private function _readableFileSize(int $bytes)
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
    private function _shortenFileName(string $fileName)
    {
        $returnString = $fileName;
        if (strlen($fileName) > 30) {
            $returnString = mb_substr($fileName,0, 14).'..'.
                mb_substr($fileName, strlen($fileName)-14);
        }
        return $returnString;
    }

}