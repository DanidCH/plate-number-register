<?php

namespace App\Controller;

use App\Entity\NumberPlate;
use App\Form\NumberPlateType;
use Doctrine\Persistence\ManagerRegistry;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;

class NumberPlateController extends AbstractController
{
    private const MAX_SIZE = 2000;

    private Imagine $imagine;
    private MailerInterface $mailer;
    private ManagerRegistry $doctrine;

    public function __construct(MailerInterface $mailer, ManagerRegistry $doctrine)
    {
        $this->imagine = new Imagine();
        $this->mailer = $mailer;
        $this->doctrine = $doctrine;
    }

    #[Route('/send-email/{key}')]
    public function sendEmail(string $key)
    {
        if ($key !== $this->getParameter('test_key')) {
            $this->createNotFoundException();
        }

        $numberplate = $this->doctrine->getRepository(NumberPlate::class)->findOneById(48);
        $registrations = $this->doctrine->getRepository(NumberPlate::class)->findAll();
        $email = new TemplatedEmail();
        $email->subject('Recidive de parking')
            ->htmlTemplate('email/recidiving_number_plate.html.twig')
            ->textTemplate('email/recidiving_number_plate.txt.twig')
            ->context([
                'registrations' => $registrations,
                'number_plate' => $numberplate
            ])
            ->from($this->getParameter('e_mail.from'))
            ->to($this->getParameter('e_mail.to'))
        ;

        foreach ($registrations as $registration) {
            $email->attachFromPath($this->getParameter('number_plate_folder').'/'.$registration->getFile());
        }

        $returned = $this->mailer->send($email);

        return $this->render('base.html.twig');
    }

    #[Route('/old/{key}', name: 'app_number_plate_old')]
    public function old(Request $request, string $key): Response
    {
        if ($key !== $this->getParameter('test_key')) {
            $this->createNotFoundException();
        }

        $numberPlate = new NumberPlate();
        $form = $this->createForm(NumberPlateType::class, $numberPlate, ['old' => true, 'initialOptions' => $this->getParameter('allowed_initials')]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (in_array($form->get('initials')->getData(), $this->getParameter('allowed_initials'))) {
                $image = $form->get('file')->getData();

                try {
                    $newFilename = $form->get('initials')->getData().'-'.time().'-'.uniqid().'.'.$image->guessExtension();

                    $image->move(
                        $this->getParameter('number_plate_folder'),
                        $newFilename
                    );

                    $numberPlate->setFile($newFilename);
                    try {
                        $exifData = exif_read_data($this->getParameter('number_plate_folder').'/'.$newFilename);
                    } catch (\Exception $exception) {
                        $exifData = [];
                    }

                    if ($exifData !== false) {
                        if (array_key_exists('DateTimeOriginal', $exifData) ) {
                            $pictureTakenOn = new \DateTime($exifData['DateTimeOriginal']);
                            $numberPlate->setCreatedAt($pictureTakenOn);
                        }
                    }

                    $this->doctrine->getManager()->persist($numberPlate);
                    $this->doctrine->getManager()->flush();

                    $this->resize($newFilename);
                    $this->addFlash('success', 'Plate number saved');
                } catch (FileException $e) {
                    $this->addFlash('error', $e->getMessage());
                }

                return $this->redirectToRoute('app_number_plate_old', ['key' => $key]);
            } else {
                $this->addFlash('warning', 'Initials aren\'t accepted');
            }
        }

        return $this->render('number_plate/old.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    #[Route('/review/{key}', name: 'app_number_plate_review')]
    public function review(Request $request, string $key): Response
    {
        if ($key !== $this->getParameter('test_key')) {
            $this->createNotFoundException();
        }

        $em = $this->doctrine->getRepository(NumberPlate::class);
        $numberPlates = $em->findAllDistinctNumberPlates();

        $recedivists = [];
        foreach ($numberPlates as $numberPlate) {
            $registrations = $em->findByNumberPlate($numberPlate, ['createdAt' => 'DESC']);
            if (count($registrations) > 1) {
                $recedivists[] = $registrations;
            }
        }

        if ($resendNumberPlate = $request->request->get('number_plate')) {
            $numberPlateToResend = $em->findOneByNumberPlate($resendNumberPlate);
            $this->checkForRecidivist($numberPlateToResend, true);
            $this->addFlash('success', sprintf('Review sent for number plate: %s', $resendNumberPlate));
        }

        return $this->render('number_plate/recap.html.twig', [
            'recedivists' => $recedivists,
            'folder' => $this->getParameter('number_plate_folder'),
        ]);
    }

    #[Route('/{initials}', name: 'app_number_plate', requirements: ['initials' => '[A-Z]{2,3}'])]
    public function index(Request $request, string $initials): Response
    {
        if (!in_array($initials, $this->getParameter('allowed_initials'))) {
            throw $this->createNotFoundException();
        }
        $numberPlate = new NumberPlate();
        $numberPlate->setInitials($initials);
        $form = $this->createForm(NumberPlateType::class, $numberPlate);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if someone already inserted the number place
            $datetime = new \DateTime('today midnight');

            if ($this->doctrine->getRepository(NumberPlate::class)->findWithinTime($numberPlate->getNumberPlate(), $datetime) === 0){
                $image = $form->get('file')->getData();

                try {
                    $newFilename = $initials.'-'.time().'-'.uniqid().'.'.$image->guessExtension();

                    $image->move(
                        $this->getParameter('number_plate_folder'),
                        $newFilename
                    );

                    $numberPlate->setFile($newFilename);
                    try {
                        $exifData = exif_read_data($this->getParameter('number_plate_folder').'/'.$newFilename);
                    } catch (\Exception $exception) {
                        $exifData = [];
                    }

                    if ($exifData !== false) {
                        if (array_key_exists('DateTimeOriginal', $exifData) ) {
                            $pictureTakenOn = new \DateTime($exifData['DateTimeOriginal']);
                            $numberPlate->setCreatedAt($pictureTakenOn);
                        }
                    }

                    $this->doctrine->getManager()->persist($numberPlate);
                    $this->doctrine->getManager()->flush();

                    $this->resize($newFilename);
                    $this->addFlash('success', 'Plate number saved');

                    // Send an email if the number plate has been already registered
                    $this->checkForRecidivist($numberPlate);
                } catch (FileException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            } else {
                $this->addFlash('warning', 'Plate number already saved since: '.$datetime->format('d.m.Y H:i'));
            }

            return $this->redirectToRoute('app_number_plate', ['initials' => $initials]);
        }

        return $this->render('number_plate/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function resize(string $filename): void
    {
        $filename = $this->getParameter('number_plate_folder').$filename;
        list($iwidth, $iheight) = getimagesize($filename);

        $ratio = $iwidth/$iheight;
        $width = self::MAX_SIZE;
        $height = self::MAX_SIZE;

        if ($width / $height > $ratio) {
            $width = $height * $ratio;
        } else {
            $height = $width / $ratio;
        }

        $photo = $this->imagine->open($filename);
        $photo->resize(new Box($width, $height))->save($filename);
    }

    private function checkForRecidivist(NumberPlate $numberPlate, bool $reminder = false): void
    {
        $result = $this->doctrine->getRepository(NumberPlate::class)->findByNumberPlate($numberPlate->getNumberPlate());

        if (count($result) > 1) {
            $email = new TemplatedEmail();
            $email->subject('Recidive de parking')
                ->htmlTemplate('email/recidiving_number_plate.html.twig')
                ->textTemplate('email/recidiving_number_plate.txt.twig')
                ->context([
                    'registrations' => $result,
                    'number_plate' => $numberPlate,
                    'reminder' => $reminder,
                ])
                ->from($this->getParameter('e_mail.from'))
                ->to($this->getParameter('e_mail.to'))
            ;

            foreach ($result as $registration) {
                $email->attachFromPath($this->getParameter('number_plate_folder').'/'.$registration->getFile());
            }

            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                $this->addFlash('error', 'Error while sending e-mail');
            } catch (\Exception $exception) {
                $this->addFlash('error', $exception->getMessage());
            } catch (\Throwable $throwable) {
                $this->addFlash('error', $throwable->getMessage());
            }
        }
    }
}
