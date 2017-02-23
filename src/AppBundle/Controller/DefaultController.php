<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Filesystem\Filesystem;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use AppBundle\Pdftk;

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
			
			$session = new Session();
			$pdftk = new Pdftk();
			
			$check = false;
			
			foreach ($request->files AS $file) {
				
				foreach ($file AS $f) {
					
					$check = $pdftk->prepareFile($f);
					break;		
				}
				break;
			}
			
			if ($check) {
				return $this->redirectToRoute('show');
			} else {
				
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
		$session = new Session();
		
		return $this->render('default/show.html.twig', array(
			'pdf_unique_id' => $session->get('pdf_unique_id'),
			'pdf_original_filename' => $session->get('pdf_original_filename'),
			'pdf_filesize' => $session->get('pdf_filesize'),
			'pdf_pages' => $session->get('pdf_pages'),
		));
	}
	
	/**
	 * @Route("/extract/{page}", name="extract")
	 */
	public function extractAction($page)
	{
		$session = new Session();
		
		if ($page > 0 AND $page <= count($session->get('pdf_pages'))) {
			
			$fs = new Filesystem();
			
			$pageFileName = '../var/pdf/'.$session->get('pdf_filename').
				'_'.str_pad($page, 4, '0', STR_PAD_LEFT).'.pdf';
			
			if (!$fs->exists($pageFileName)) {
				
				shell_exec('pdftk ../var/pdf/'.
					$session->get('pdf_filename').'.pdf burst output '.
					'../var/pdf/'.$session->get('pdf_filename').'_%04d.pdf');
			}
			
			$downloadFileName = str_replace('.pdf', '_seite_'.
				str_pad($page, 4, '0', STR_PAD_LEFT).'.pdf', 
				$session->get('pdf_original_filename'));
			
			$output = file_get_contents($pageFileName);
			
			header('Content-type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.
				$downloadFileName.'"');
			die($output);
			
		} 		
		die('Fehler!');
		
	}
	
	
	
}