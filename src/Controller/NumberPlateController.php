<?php

namespace App\Controller;

use App\Entity\NumberPlate;
use App\Form\NumberPlateType;
use Doctrine\Persistence\ManagerRegistry;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class NumberPlateController extends AbstractController
{
    private const MAX_SIZE = 2000;

    private $imagine;

    public function __construct()
    {
        $this->imagine = new Imagine();
    }

    #[Route('/{initials}', name: 'app_number_plate', requirements: ['initials' => '[A-Z]{2,3}'])]
    public function index(Request $request, ManagerRegistry $doctrine, string $initials): Response
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
            $datetime = new \DateTime('-1 day');

            if ($doctrine->getRepository(NumberPlate::class)->findWithinTime($numberPlate->getNumberPlate(), $datetime) === 0){
                $image = $form->get('file')->getData();

                try {
                    $newFilename = $initials.'-'.time().'-'.uniqid().'.'.$image->guessExtension();

                    $image->move(
                        $this->getParameter('number_plate_folder'),
                        $newFilename
                    );

                    $numberPlate->setFile($newFilename);
                    $exifData = exif_read_data($this->getParameter('number_plate_folder').'/'.$newFilename);

                    if ($exifData !== false) {
                        if (array_key_exists('DateTimeOriginal', $exifData) ) {
                            $pictureTakenOn = new \DateTime($exifData['DateTimeOriginal']);
                            $numberPlate->setCreatedAt($pictureTakenOn);
                        }
                    }

                    $doctrine->getManager()->persist($numberPlate);
                    $doctrine->getManager()->flush();

                    $this->resize($newFilename);
                    $this->addFlash('success', 'Plate number saved');
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
}
