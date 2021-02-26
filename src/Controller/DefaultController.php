<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Psr\Log\LoggerInterface;
use App\Pdftk;

class DefaultController extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function indexAction(Request $request, SessionInterface $session, LoggerInterface $logger, TranslatorInterface $translator)
    {
        $pdftk = new Pdftk($session, $logger);
        $errors = $pdftk->checkRequirements();

        $form = $this->createFormBuilder()
            ->add('pdf', FileType::class, array('label' => 'PDF file:'))
            ->add('save', SubmitType::class, array('label' => 'Upload'))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $check = false;

            foreach ($request->files AS $file) {

                foreach ($file AS $f) {
                    $check = $pdftk->prepareUploadedFile($f);
                    break;
                }
                break;
            }

            if ($check) {
                return $this->redirectToRoute('process');
            } else {
                return $this->redirectToRoute('index');
            }
        }
        return $this->render('default/index.html.twig', array(
            'form' => $form->createView(),
            'errors' => $errors
        ));
    }

    /**
     * @Route("/show/", name="show")
     */
    public function showAction(SessionInterface $session)
    {
        return $this->render('default/show.html.twig', array(
            'pdf_unique_id' => $session->get('pdf_unique_id'),
            'pdf_original_filename' => $session->get('pdf_original_filename'),
            'pdf_shorten_filename' => $session->get('pdf_shorten_filename'),
            'pdf_filesize' => $session->get('pdf_filesize'),
            'pdf_pages' => $session->get('pdf_pages'),
        ));
    }

    /**
     * Split PDF into Pages
     *
     * @Route("/process/", name="process")
     */
    public function processAction(SessionInterface $session, LoggerInterface $logger)
    {
        $pdftk = new Pdftk($session, $logger);
        $pdftk->processFile();

        return $this->redirectToRoute('show');
    }

    /**
     * Download single page as PDF
     *
     * @Route("/extract/{page}", name="extract")
     */
    public function extractAction(int $page, SessionInterface $session, LoggerInterface $logger, TranslatorInterface $translator)
    {
        $page = intval($page);

        if ($page > 0 AND $page <= count($session->get('pdf_pages'))) {
            $pdftk = new Pdftk($session, $logger);
            $pdftk->extractPage($page);
        }
        $session->getFlashBag()->add(
            'error', $translator->trans('Error: Page could not be extracted!'));
        return $this->redirectToRoute('show');
    }

    /**
     * Download single page as JPG-Image
     *
     * @Route("/screenshot/{page}", name="screenshot")
     */
    public function screenshotAction(int $page, SessionInterface $session, LoggerInterface $logger, TranslatorInterface $translator)
    {
        $page = intval($page);

        if ($page > 0 AND $page <= count($session->get('pdf_pages'))) {
            $pdftk = new Pdftk($session, $logger);
            $pdftk->getScreenshot($page);
        }
        $session->getFlashBag()->add(
                'error', $translator->trans('Error: Screenshot could not be created!'));
        return $this->redirectToRoute('show');
    }

    /**
     * @Route("/move{direction}/{page}", name="move")
     */
    public function moveAction($direction, int $page, SessionInterface $session, LoggerInterface $logger, TranslatorInterface $translator)
    {
        $page = intval($page);

        if ($page > 0 AND $page <= count($session->get('pdf_pages'))) {

            $pdftk = new Pdftk($session, $logger);
            $pdftk->move($direction, $page);

            $session->getFlashBag()->add(
                'success',
                $translator->trans('OK! Page was successfully moved.'));
        }

        return $this->redirectToRoute('show');
    }

    /**
     * @Route("/rotate/{direction}/{page}", name="rotate")
     */
    public function rotateAction($direction, int $page, SessionInterface $session, LoggerInterface $logger, TranslatorInterface $translator)
    {
        $page = intval($page);

        if ($page > 0 AND $page <= count($session->get('pdf_pages'))) {

            $pdftk = new Pdftk($session, $logger);
            $pdftk->rotate($direction, $page);

            $session->getFlashBag()->add(
                'success',
                $translator->trans('OK! Page was successfully rotated.'));
        }

        return $this->redirectToRoute('show');
    }

    /**
     * @Route("/delete/{page}", name="delete")
     */
    public function deleteAction($page, SessionInterface $session, LoggerInterface $logger, TranslatorInterface $translator)
    {
        $page = intval($page);

        if ($page > 0 AND $page <= count($session->get('pdf_pages'))) {
            $pdftk = new Pdftk($session, $logger);

            if ($pdftk->delete($page)) {
                $session->getFlashBag()->add(
                    'success',
                    $translator->trans('OK! Page %number% was successfully deleted.', ['number' => $page]));
            } else {
                $session->getFlashBag()->add(
                    'error',
                    $translator->trans('Error: Page %number% could not be deleted!', ['number' => $page]));
            }
        } else {
            $session->getFlashBag()->add(
                'error', $translator->trans('Error: Page id not valid!'));
        }

        return $this->redirectToRoute('show');
    }

    /**
     * @Route("/download/", name="download")
     */
    public function downloadAction(SessionInterface $session, LoggerInterface $logger, TranslatorInterface $translator)
    {
        $pdftk = new Pdftk($session, $logger);
        if (!$pdftk->download()) {
            $session->getFlashBag()->add(
                'error', $translator->trans('Error: Download could not be started!'));
        }
        return $this->redirectToRoute('show');
    }

    /**
     * @Route("/restart/", name="restart")
     * @param SessionInterface $session
     */
    public function restart(SessionInterface $session)
    {
        $session->clear();
        return $this->redirectToRoute('index');
    }


}