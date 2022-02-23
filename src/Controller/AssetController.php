<?php

namespace App\Controller;

use App\Form\AssetType;
use App\Entity\Asset;
use App\Entity\AssetPrice;
use App\Service\FetchPrices;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AssetController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route("/assets", name: "asset_list")]
    public function index(): Response
    {
        $assets = $this->entityManager
            ->getRepository(Asset::class)
            ->allWithLatestPrice();

        return $this->render('asset/index.html.twig', [
            'controller_name' => 'AssetController',
            'assets' => $assets
        ]);
    }

    #[Route("/asset/new", name: "asset_new", methods: ["GET", "POST"])]
    #[IsGranted("ROLE_USER")]
    public function new(Request $request) {
        $asset = new Asset();

        $form = $this->createForm(AssetType::class, $asset);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $asset = $form->getData();

            $this->entityManager->persist($asset);
            $this->entityManager->flush();

            return $this->redirectToRoute('asset_show', ['id' => $asset->getId()]);
        }

        return $this->renderForm('asset/edit.html.twig', ['form' => $form]);
    }

    #[Route("/asset/{id}/edit", name: "asset_edit", methods: ["GET", "POST"])]
    #[IsGranted("ROLE_USER")]
    public function edit(Asset $asset, Request $request) {
        $form = $this->createForm(AssetType::class, $asset);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $asset = $form->getData();

            $this->entityManager->persist($asset);
            $this->entityManager->flush();

            return $this->redirectToRoute('asset_show', ['id' => $asset->getId()]);
        }

        return $this->renderForm('asset/edit.html.twig', ['form' => $form]);
    }

    #[Route("/asset/{id}/update", name: "asset_update_prices", methods: ["GET"])]
    public function updatePrices(Asset $asset, Request $request, FetchPrices $fp) {

        $ap = $this->entityManager->getRepository(AssetPrice::class);
        $last_price = $ap->latestPrice($asset);

        if ($last_price) {
            $start_day = $last_price->getDate()->add(new \DateInterval('P1D'));
        } else {
            // get one year worth of data
            $start_day = (new \DateTime('NOW'))->sub(new \DateInterval('P1Y'));
        }

        try
        {
            $num_prices = $fp->updatePrices($asset, $start_day);
            $this->addFlash('success', "$num_prices prices updated");
        }
        catch (\Exception $ex)
        {
            $this->addFlash('error', $ex->getMessage());
        }
        
        return $this->redirectToRoute('asset_show', ['id' => $asset->getId()]);
    }
    
    #[Route("/asset/{id}", name: "asset_show", methods: ["GET"])]
    #[IsGranted("ROLE_USER")]
    public function show(Asset $asset) {
        $instruments = $this->entityManager->getRepository(Asset::class)
            ->getInstrumentPositionsForUser($asset, $this->getUser());

        $ap = $this->entityManager->getRepository(AssetPrice::class);
        $last_price = $ap->latestPrice($asset);

        //var_dump($instruments);

        return $this->render('asset/show.html.twig', [
            'controller_name' => 'AssetController',
            'asset' => $asset,
            'price' => $last_price,
            'chartdatefrom' => $last_price ? $last_price->getDate()->modify("-365 day") : null,
            'instruments' => $instruments,
        ]);
    }

    #[Route("/asset/{id}", name: "asset_delete", methods: ["DELETE"])]
    #[IsGranted("ROLE_USER")]
    public function delete(Asset $asset) {
        try
        {
            $this->entityManager->remove($asset);
            $this->entityManager->flush();
            $this->addFlash('success', "Asset {$asset->getName()} deleted.");
            return new JsonResponse(['message' => 'ok']);
        }
        catch (\Exception $e)
        {
            $this->addFlash('error', $e->getMessage());
            return new JsonResponse(['message' => $e->getMessage()], 409);
        }
    }
}
