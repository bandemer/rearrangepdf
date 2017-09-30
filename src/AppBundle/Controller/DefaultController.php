<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use AppBundle\Pdftk;
use AppBundle\Testablesession;

class DefaultController extends Controller
{

	/**
	 * @Route("/", name="index")
	 */
	public function indexAction(Request $request)
	{		
		$form = $this->createFormBuilder()
			->add('pdf', FileType::class, array('label' => 'Datei'))
			->add('save', SubmitType::class, array('label' => 'Hochladen'))
			->getForm();

		$form->handleRequest($request);
			
		if ($form->isSubmitted() && $form->isValid()) {
			
			$session = new Testablesession();
			$pdftk = new Pdftk();
			
			$check = false;
			
			foreach ($request->files AS $file) {
				
				foreach ($file AS $f) {
					
					$check = $pdftk->prepareUploadedFile($f);
					break;		
				}
				break;
			}
			
			if ($check) {
				return $this->redirectToRoute('show');
			} else {
				return $this->redirectToRoute('index');				
			}
		} 
			
		return $this->render('default/index.html.twig', array(
			'form' => $form->createView(),
		));
	}
	
	/**
	 * @Route("/show/", name="show")
	 */
	public function showAction()
	{
	    $session = new Testablesession();
		
		return $this->render('default/show.html.twig', array(
			'pdf_unique_id' => $session->get('pdf_unique_id'),
			'pdf_original_filename' => $session->get('pdf_original_filename'),
			'pdf_shorten_filename' => $session->get('pdf_shorten_filename'),
			'pdf_filesize' => $session->get('pdf_filesize'),
			'pdf_pages' => $session->get('pdf_pages'),
		));
	}
	
	/**
	 * @Route("/extract/{page}", name="extract")
	 */
	public function extractAction($page)
	{
	    $session = new Testablesession();
		$page = intval($page);
		
		if ($page > 0 AND $page <= count($session->get('pdf_pages'))) {
			$pdftk = new Pdftk();
			$pdftk->extractPage($page);			
		} 		
		$session->getFlashBag()->add(
			'error', 'Fehler: Seite konnte nicht extrahiert werden!');
		return $this->redirectToRoute('show');
	}
	
	/**
	 * @Route("/screenshot/{page}", name="screenshot")
	 */
	public function screenshotAction($page)
	{
	    $session = new Testablesession();
		$page = intval($page);
		
		if ($page > 0 AND $page <= count($session->get('pdf_pages'))) {
			$pdftk = new Pdftk();
			$pdftk->getScreenshot($page);
		}
		$session->getFlashBag()->add(
				'error', 'Fehler: Screenshot konnte nicht erstellt werden!');
		return $this->redirectToRoute('show');
	}
	
	/**
	 * @Route("/move{direction}/{page}", name="move")
	 */
	public function moveAction($direction, $page)
	{
	    $session = new Testablesession();
	    $page = intval($page);
	    
	    if ($page > 0 AND $page <= count($session->get('pdf_pages'))) {
	        
	        $pdftk = new Pdftk();
	        $pdftk->move($direction, $page);
	        
	        $session->getFlashBag()->add(
	            'success',
	            'OK! Die Seite wurde erfolgreich verschoben!');
	    }	    
	    
        return $this->redirectToRoute('show');
	}
	
	
	/**
	 * @Route("/delete/{page}", name="delete")
	 */
	public function deleteAction($page)
	{
	    $session = new Testablesession();
		$page = intval($page);
	
		if ($page > 0 AND $page <= count($session->get('pdf_pages'))) {
			$pdftk = new Pdftk();
			
			if ($pdftk->delete($page)) {
				$session->getFlashBag()->add(
					'success', 
					'OK! Seite '.$page.' wurde erfolgreich gelöscht!');
			} else {
				$session->getFlashBag()->add(
					'error', 
					'Fehler: Seite '.$page.' konnte nicht gelöscht werden!');
			}
		} else {
			$session->getFlashBag()->add(
				'error', 'Fehler: Ungültige Seitenzahl');
		}
		
		return $this->redirectToRoute('show');
	}
	
	/**
	 * @Route("/download/", name="download")
	 */
	public function downloadAction()
	{
	    $pdftk = new Pdftk();
	    if (!$pdftk->download()) {
	        $session->getFlashBag()->add(
	            'error', 'Fehler: Download konnte nicht gestartet werden!');
	    }
	    return $this->redirectToRoute('show');
	}
	
}