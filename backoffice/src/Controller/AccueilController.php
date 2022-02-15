<?php

namespace App\Controller;

use Symfony\Component\Form;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use Doctrine\ORM\EntityManagerInterface;

use App\Form\AccueilType;

use App\Entity\Echouage;
use App\Entity\Zone;
use App\Entity\Espece;

class AccueilController extends AbstractController
{
    /**
     * @Route("/", name="accueil")
     */
    public function acceuil(Request $request): Response
    {
        // exit(\Doctrine\Common\Util\Debug::dump($zone_name->getZone()));
        $zones = $this->getDoctrine()->getManager()
            ->getRepository(Zone::class)
            ->findAll();
        $zones_options = array();
        $zones_options['Any'] = -1;
        foreach ($zones as $id => $zone_entity) {
            $zones_options[$zone_entity->getZone()] = $id;
        }

        $form = $this->createForm(AccueilType::class, null, [
            'zones' => $zones_options
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $zone = $form->get('Zone')->getData();
            $espece = $form->get('Espece')->getData();

            return $this->redirectToRoute('tableau', ['zone' => $zone, 'espece' => $espece]);
        }

        return $this->render('accueil/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/tableau/{zone}/{espece}", name="tableau")
     */
    public function tableau(Request $request, int $zone, string $espece): Response
    {
        $espece_entity = $this->getDoctrine()->getManager()
            ->getRepository(Espece::class)
            ->findEntityByName($espece);

        if ($espece_entity != null) {
            //Error, espece does not exist
            $liste_zones = null;

            if ($zone == -1) {
                $liste_zones = $this->getDoctrine()->getManager()
                    ->getRepository(Zone::class)
                    ->findAll();
            } else {
                $liste_zones = array($this->getDoctrine()->getManager()
                    ->getRepository(Zone::class)
                    ->find($zone));
            }

            $liste = array();
            $header = array("Annee");

            $zone_column_index = 1;
            $column_count = 1 + count($liste_zones);
            $year_per_zones = array_fill(0, $column_count - 1, 0);
            foreach ($liste_zones as $id => $zone_entity) {
                array_push($header, $zone_entity->getZone());

                $echouage = $this->getDoctrine()->getManager()
                    ->getRepository(Echouage::class)
                    ->findAny($zone_entity->getId(), $espece_entity->getID());

                foreach ($echouage as $echouage_entity) {
                    if (array_key_exists($echouage_entity->getDate(), $liste) == false) {
                        $liste[$echouage_entity->getDate()] = array_fill(0, $column_count, 0);
                        $liste[$echouage_entity->getDate()][0] = $echouage_entity->getDate();
                        $year_per_zones[$zone_column_index - 1]++;
                    } else {
                        if($liste[$echouage_entity->getDate()][$zone_column_index] == 0) {
                            $year_per_zones[$zone_column_index - 1]++;
                        }
                    }
                    $liste[$echouage_entity->getDate()][$zone_column_index] += $echouage_entity->getNombre();
                }
                $zone_column_index++;
            }

            $tableau = array();
            $annees = array_keys($liste);
            sort($annees);

            $average = array_fill(0, $column_count - 1, 0);
            $min = array_fill(0, $column_count - 1, 1e9);
            $max = array_fill(0, $column_count - 1, 0);
            foreach ($annees as $index => $annee) {
                array_push($tableau, $liste[$annee]);

                for($zone_id = 0; $zone_id < $column_count - 1; $zone_id++) {
                    $average[$zone_id] += $liste[$annee][$zone_id + 1];
                    $min[$zone_id] = min($min[$zone_id], $liste[$annee][$zone_id + 1]);
                    $max[$zone_id] = max($max[$zone_id], $liste[$annee][$zone_id + 1]);
                }
            }
            for($i = 0; $i < $column_count - 1; ++$i) {
                $average[$i] = round($average[$i] / $year_per_zones[$i]);
            }

            return $this->render('accueil/tableau.html.twig', [
                'header' => $header,
                'list' => $tableau,
                'averages' => $average,
                'mins' => $min,
                'maxs' => $max
            ]);
        }
        exit(\Doctrine\Common\Util\Debug::dump($espece_entity));
    }
}