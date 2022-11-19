<?php

namespace App\Controller;

use App\Entity\NumberPlate;
use App\Form\NumberPlateType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class NumberPlateController extends AbstractController
{
    #[Route('/{initials}', name: 'app_number_plate', requirements: ['initials' => '[A-Z]{2,3}'])]
    public function index(Request $request, ManagerRegistry $doctrine, SluggerInterface $slugger, string $initials): Response
    {
        if (!in_array($initials, $this->getParameter('allowed_initials'))) {
            throw $this->createNotFoundException();
        }
        $numberPlate = new NumberPlate();
        $numberPlate->setInitials($initials);
        $form = $this->createForm(NumberPlateType::class, $numberPlate);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', 'Plate number saved');

            $image = $form->get('file')->getData();

            try {
                $originalFilename = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);

                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$image->guessExtension();

                $image->move(
                    $this->getParameter('number_plate_folder'),
                    $newFilename
                );

                $numberPlate->setFile($newFilename);

                $doctrine->getManager()->persist($numberPlate);
                $doctrine->getManager()->flush();
            } catch (FileException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('number_plate/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
