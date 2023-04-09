<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use App\Service\Pdftk;

class DefaultController extends AbstractController
{
    #[Route(path: '/', name: 'index')]
    public function indexAction(Request $request, Pdftk $pdftk)
    {
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

    #[Route(path: '/show/', name: 'show')]
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
     */
    #[Route(path: '/process/', name: 'process')]
    public function processAction(Pdftk $pdftk)
    {
        $pdftk->processFile();

        return $this->redirectToRoute('show');
    }

    /**
     * Download single page as PDF
     */
    #[Route(path: '/extract/{page}', name: 'extract')]
    public function extractAction(int $page, Pdftk $pdftk, SessionInterface $session, TranslatorInterface $translator)
    {
        $page = intval($page);

        if ($page > 0 AND $page <= count($session->get('pdf_pages'))) {
            $pdftk->extractPage($page);
        }
        $session->getFlashBag()->add(
            'error', $translator->trans('Error: Page could not be extracted!'));
        return $this->redirectToRoute('show');
    }

    /**
     * Download single page as JPG-Image
     */
    #[Route(path: '/screenshot/{page}', name: 'screenshot')]
    public function screenshotAction(int $page, SessionInterface $session, Pdftk $pdftk, TranslatorInterface $translator)
    {
        $page = intval($page);

        if ($page > 0 AND $page <= count($session->get('pdf_pages'))) {

            $pdftk->getScreenshot($page);
        }
        $session->getFlashBag()->add(
                'error', $translator->trans('Error: Screenshot could not be created!'));
        return $this->redirectToRoute('show');
    }

    #[Route(path: '/move{direction}/{page}', name: 'move')]
    public function moveAction($direction, int $page, SessionInterface $session, Pdftk $pdftk, TranslatorInterface $translator)
    {
        $page = intval($page);

        if ($page > 0 AND $page <= count($session->get('pdf_pages'))) {

            $pdftk->move($direction, $page);

            $session->getFlashBag()->add(
                'success',
                $translator->trans('OK! Page was successfully moved.'));
        }

        return $this->redirectToRoute('show');
    }

    #[Route(path: '/rotate/{direction}/{page}', name: 'rotate')]
    public function rotateAction($direction, int $page, SessionInterface $session, Pdftk $pdftk, TranslatorInterface $translator)
    {
        $page = intval($page);

        if ($page > 0 AND $page <= count($session->get('pdf_pages'))) {

            $pdftk->rotate($direction, $page);

            $session->getFlashBag()->add(
                'success',
                $translator->trans('OK! Page was successfully rotated.'));
        }

        return $this->redirectToRoute('show');
    }

    #[Route(path: '/delete/{page}', name: 'delete')]
    public function deleteAction($page, SessionInterface $session, Pdftk $pdftk, TranslatorInterface $translator)
    {
        $page = intval($page);

        if ($page > 0 AND $page <= count($session->get('pdf_pages'))) {

            if ($pdftk->delete($page)) {
                $session->getFlashBag()->add(
                    'success',
                    $translator->trans('OK! Page %number% was successfully deleted.', ['%number%' => $page]));
            } else {
                $session->getFlashBag()->add(
                    'error',
                    $translator->trans('Error: Page %number% could not be deleted!', ['%number%' => $page]));
            }
        } else {
            $session->getFlashBag()->add(
                'error', $translator->trans('Error: Page id not valid!'));
        }

        return $this->redirectToRoute('show');
    }

    #[Route(path: '/download/', name: 'download')]
    public function downloadAction(SessionInterface $session, Pdftk $pdftk, TranslatorInterface $translator)
    {
        if (!$pdftk->download()) {
            $session->getFlashBag()->add(
                'error', $translator->trans('Error: Download could not be started!'));
        }
        return $this->redirectToRoute('show');
    }

    #[Route(path: '/restart/', name: 'restart')]
    public function restart(SessionInterface $session)
    {
        $session->clear();
        return $this->redirectToRoute('index');
    }

    #[Route(path: '/add/', name: 'add')]
    public function add(Request $request, Pdftk $pdftk)
    {
        $file = $request->files->get('appendfile');
        $check = $pdftk->appendFile($file);

        if ($check) {
            return $this->redirectToRoute('process');
        }

        return $this->redirectToRoute('show');
    }


}