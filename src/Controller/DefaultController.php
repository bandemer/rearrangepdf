<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        return $this->render('default/index.html.twig', ['errors' => $errors]);
    }

    #[Route(path: '/upload/', name: 'upload')]
    public function upload(Request $request, Pdftk $pdftk) : JsonResponse
    {

        $check = false;
        $responseMessage = '';

        foreach ($request->files AS $file) {
            $check = $pdftk->prepareUploadedFile($file);
        }

        $responseCode = 200;

        if (!$check) {
            $responseCode = 400;
        }

        return new JsonResponse(['data' => ['message' => $responseMessage]], $responseCode);
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
    public function processAction(Pdftk $pdftk) : JsonResponse
    {
        $responseCode = 400;
        $responseMessage = 'Error!';

        if ($pdftk->processFile()) {
            $responseCode = 200;
            $responseMessage = 'OK!';
        }

        return new JsonResponse(['data' => ['message' => $responseMessage]], $responseCode);
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

    #[Route(path: '/privacy/', name: 'privacy')]
    public function privacy() : Response
    {
        return $this->render('default/privacy.html.twig');
    }

    #[Route(path: '/imprint/', name: 'imprint')]
    public function imprint() : Response
    {
        return $this->render('default/imprint.html.twig');
    }
}