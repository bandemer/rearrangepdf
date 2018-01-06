<?php

namespace AppBundle;

use Symfony\Component\Filesystem\Filesystem;
use \AppBundle\Testablesession;

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

    public function __construct()
    {
        $basePath = str_replace('src/AppBundle/Pdftk.php', '', __FILE__);
        $this->_dirPdf = $basePath.'var/pdf/';
        $this->_dirScr = $basePath.'web/screenshots/';
    }

    /**
     * Prepare uploaded file
     *
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file
     * @return bool
     */
    public function prepareUploadedFile($file)
    {
        $session = new Testablesession();
        $session->set('pdf_original_filename', $file->getClientOriginalName());
        $session->set('pdf_shorten_filename',
            $this->_shortenFileName($file->getClientOriginalName()));

        $uniqueId = date('Ymdhis').'_'.uniqid();

        $session->set('pdf_unique_id', $uniqueId);

        $fs = new Filesystem();

        try {
            $fs->mkdir($this->_dirScr.$uniqueId);
            $fs->mkdir($this->_dirPdf.$uniqueId);

            $file->move($this->_dirPdf.$uniqueId, 'file.pdf');

        } catch (\Exception $e) {
            $session->getFlashBag()
                ->add('error', 'Fehler: Verzeichnisse konnten nicht '.
                    'angelegt werden!');
            return false;
        }
        return true;
    }

    /**
     * process a pdf file
     * @return boolean
     */
    public function processFile()
    {
        $session = new Testablesession();

        $pdfFile = $this->_dirPdf.
            $session->get('pdf_unique_id').'/file.pdf';

        $dataFile = $this->_dirPdf.
            $session->get('pdf_unique_id').'/file_data.txt';

        //Check if PDF
        if (substr(file_get_contents($pdfFile), 0, 4) != '%PDF') {
            $session->getFlashBag()
                ->add('error', 'Fehler: Kein gültiges PDF-Dokument!');
            return false;
        }

        //delete screenshots and page pdf, if pages exist
        if (is_array($session->get('pdf_pages'))) {
            $pages = $session->get('pdf_pages');
            $fs = new Filesystem();

            foreach ($pages AS $pk => $pv) {

                //delete Screenshot
                if ($fs->exists($pv)) {
                    $fs->remove($pv);
                }

                //delete PDF page
                $pageFile = $this->_dirPdf.$session->get('pdf_unique_id').
                    '/file_page_'.str_pad($pk, 4, '0', STR_PAD_LEFT).'.pdf';
                if ($fs->exists($pageFile)) {
                    $fs->remove($pageFile);
                }
            }
        }

        //Dateigröße ermitteln
        $fileSize = $this->_readableFileSize(filesize($pdfFile));
        $session->set('pdf_filesize', $fileSize);

        //Anzahl der Seiten ermitteln
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

        //Screenshots erzeugen
        for ($i = 1; $i <= $pageCount; $i++) {

            $screenshot = $session->get('pdf_unique_id').
                '/page_'.str_pad($i, 4, '0', STR_PAD_LEFT).'.jpg';

            shell_exec('convert -thumbnail 400x -colorspace srgb '.
                '-background white -flatten '.$pdfFile.'['.($i-1).'] '.
                $this->_dirScr.$screenshot);

            $pages[$i] = $this->urlScr.$screenshot;
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
        $session = new Testablesession();

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
        $session = new Testablesession();

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
        $session = new Testablesession();
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
     * Delete a page
     *
     * @param int $page
     */
    public function delete($page)
    {
        $session = new Testablesession();

        $pdfFileName = $this->_dirPdf.
            $session->get('pdf_unique_id').'/file.pdf';

        //make a backup of file
        $fs = new Filesystem();
        $fs->copy($pdfFileName, str_replace('.pdf', '.bac.pdf', $pdfFileName));

        //delete page
        $pages = $session->get('pdf_pages');
        $option = '';
        if ($page == 1) {
            $option = '2-end';
        } elseif ($page == count($pages)) {
            $option = '1-'.(count($pages)-1);
        } else {
            $option = '1-'.($page-1).' '.($page+1).'-end';
        }
        shell_exec('pdftk '.$pdfFileName.' cat '.$option.' output '.
            str_replace('.pdf', '.new.pdf', $pdfFileName));

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
    public function move($direction, $page)
    {
        $session = new Testablesession();

        $pdfFileName = $this->_dirPdf.
            $session->get('pdf_unique_id').'/file.pdf';

        //make a backup of file
        $fs = new Filesystem();
        $fs->copy($pdfFileName, str_replace('.pdf', '.bac.pdf', $pdfFileName));

        //Burst, if pdf-pages not yet exist
        $doBurst = false;
        if (is_array($session->get('pdf_pages'))) {

            foreach ($session->get('pdf_pages') AS $pk => $pv) {
                if ($fs->exists($this->_dirPdf.$session->get('pdf_unique_id').'file_page_'.
                    str_pad($pk, 4, '0', STR_PAD_LEFT).'.pdf') == false) {
                    $doBurst = true;
                    break;
                }
            }
        } else {
            $doBurst = true;
        }
        if ($doBurst) {
           $shell_output = shell_exec('pdftk '.$pdfFileName.' burst output '.
                $this->_dirPdf.$session->get('pdf_unique_id').
                '/file_page_%04d.pdf verbose');
        }
        $pages = $session->get('pdf_pages');

        //Rename
        if ($direction == 'up' AND $page > 1) {

            $fs->rename($this->_dirPdf.$session->get('pdf_unique_id').
                '/file_page_'.str_pad($page-1, 4, '0', STR_PAD_LEFT).'.pdf',
                $this->_dirPdf.$session->get('pdf_unique_id').
                '/file_page_'.str_pad($page-1, 4, '0', STR_PAD_LEFT).'.pdf.bac');
            $fs->rename($this->_dirPdf.$session->get('pdf_unique_id').
                '/file_page_'.str_pad($page, 4, '0', STR_PAD_LEFT).'.pdf',
                $this->_dirPdf.$session->get('pdf_unique_id').
                '/file_page_'.str_pad($page-1, 4, '0', STR_PAD_LEFT).'.pdf');
            $fs->rename($this->_dirPdf.$session->get('pdf_unique_id').
                '/file_page_'.str_pad($page-1, 4, '0', STR_PAD_LEFT).'.pdf.bac',
                $this->_dirPdf.$session->get('pdf_unique_id').
                '/file_page_'.str_pad($page, 4, '0', STR_PAD_LEFT).'.pdf');

        } elseif ($direction == 'down' AND $page < (count($pages))) {

            $fs->rename($this->_dirPdf.$session->get('pdf_unique_id').
                '/file_page_'.str_pad($page+1, 4, '0', STR_PAD_LEFT).'.pdf',
                $this->_dirPdf.$session->get('pdf_unique_id').
                '/file_page_'.str_pad($page+1, 4, '0', STR_PAD_LEFT).'.pdf.bac');
            $fs->rename($this->_dirPdf.$session->get('pdf_unique_id').
                '/file_page_'.str_pad($page, 4, '0', STR_PAD_LEFT).'.pdf',
                $this->_dirPdf.$session->get('pdf_unique_id').
                '/file_page_'.str_pad($page+1, 4, '0', STR_PAD_LEFT).'.pdf');
            $fs->rename($this->_dirPdf.$session->get('pdf_unique_id').
                '/file_page_'.str_pad($page+1, 4, '0', STR_PAD_LEFT).'.pdf.bac',
                $this->_dirPdf.$session->get('pdf_unique_id').
                '/file_page_'.str_pad($page, 4, '0', STR_PAD_LEFT).'.pdf');

        }

        //Join
        $command = 'pdftk ';
        foreach ($session->get('pdf_pages') AS $pk => $pv) {
            $command .= $this->_dirPdf.$session->get('pdf_unique_id').'/file_page_'.
                str_pad($pk, 4, '0', STR_PAD_LEFT).'.pdf ';
        }
        $command .= 'cat output '.
            $this->_dirPdf.$session->get('pdf_unique_id').
            '/file.pdf verbose';

        $shell_output = shell_exec($command);

        $fs->remove($this->_dirPdf.$session->get('pdf_unique_id').
            '/file.bac.pdf');

        return $this->processFile();
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