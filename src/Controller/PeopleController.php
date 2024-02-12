<?php
/**
 * @category  Videotheque
 * @author    Guillaume Deloffre <guillaume.deloffre@gmail.com>
 * @date      2024
 */

namespace App\Controller;

use App\Classes\Constants;
use App\Entity\People;
use App\Repository\PeopleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PeopleController extends AbstractController
{
    private $format;

    private $security;

    private $cache;

    public function __construct(
        \App\Helper\Format $format,
        \App\Helper\Security $security,
        \Symfony\Contracts\Cache\TagAwareCacheInterface $cache
    )
    {
        $this->format = $format;
        $this->security = $security;
        $this->cache = $cache;
    }

    /*
     * Recupère la liste de toutes les personnalités
     */
    #[Route('/api/peoples', name: 'people', methods: ['GET'])]
    public function getPeopleList(PeopleRepository $peopleRepository, Request $request): JsonResponse
    {
        $paginationLimit = Constants::LIMIT_RESULTS_BY_PAGE;

        // Request parameters
        $page = $request->get('page', 1);
        $limit = $request->get('limit', $paginationLimit);
        $isCached = $request->get('cache', true);

        // si GET limit plus grand que la limite fixée, on ne garde que la valeur limite maximum
        $limit = min($limit, $paginationLimit);

        if (Constants::CACHE_ENABLE && $isCached) {
            // Pour de meilleurs performance, nous allons stocker les résultats de la pagination en cache
            // Si les informations n'existent pas, on va les chercher, sinon nous récupérons la valeur stockée en cache
            $cacheId = "getAllPeople-" . $page . "-" . $limit;
            $returnList = $this->cache->get($cacheId, function (ItemInterface $item) use ($peopleRepository, $page, $limit) {
                $item->tag("peopleCache");
                $item->expiresAfter(Constants::CACHE_TIMEOUT);
                // récupère la liste des personnalités
                $peopleList = $peopleRepository->findAllWithPagination($page, $limit);
                return $this->format->json($peopleList);
            });
        }
        else{
            // récupère la liste des acteurs
            $peopleList = $peopleRepository->findAllWithPagination($page, $limit);
            $returnList = $this->format->json($peopleList);
        }

        return new JsonResponse($returnList, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /*
     * Retourne les informations de la personnalité
     */
    #[Route('/api/peoples/{id}', name: 'detail_people', methods: ['GET'])]
    public function getDetailPeople(People $people): JsonResponse {
        $jsonPeople = $this->format->json($people);
        return new JsonResponse($jsonPeople, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /*
     * Supprime la personnalité
     */
    #[Route('/api/peoples/{id}', name: 'delete_people', methods: ['DELETE'])]
    public function deteletePeople(People $people, EntityManagerInterface $em): JsonResponse {
        // Si l'authentification échoue
        if (!$this->security->isAuthentified()) {
            return new JsonResponse(['authentification' => 'failed'], Response::HTTP_FORBIDDEN, ['accept' => 'json'], false);
        }

        // Supprime le cache
        $this->cache->invalidateTags(["peopleCache"]);
        $em->remove($people);
        $em->flush();
        return new JsonResponse(['status' => 'success'], Response::HTTP_OK, ['accept' => 'json'], false);
    }

    /*
     * Ajouter une nouvelle personnalité
     */
    #[Route('/api/peoples', name: 'add_people', methods: ['POST'])]
    public function addPeople(Request $request, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        // Si l'authentification échoue
        if (!$this->security->isAuthentified()) {
            return new JsonResponse(['authentification' => 'failed'], Response::HTTP_FORBIDDEN, ['accept' => 'json'], false);
        }

        $firstname = $request->get("firstname", null);
        $lastname = $request->get("lastname", null);
        $nationality = $request->get("nationality", null);
        $birthday = $request->get("birthday", null);

        if ($firstname == null || $lastname == null || $nationality == null || $nationality == null){
            return new JsonResponse(['status' => 'error', 'message' => 'Veuillez renseigner les informations obligatoires.'], Response::HTTP_BAD_REQUEST, ["Location" => $location], false);
        }

        // convertir la date retournée
        $birthday = $this->format->convertDateStringToDateTime($birthday);

        $people = new People();
        $people->setFirstname($firstname);
        $people->setLastname($lastname);
        $people->setNationality($nationality);
        $people->setDateOfBirth($birthday);

        $em->persist($people);
        $em->flush();

        $jsonPeople = $this->format->json($people);

        $location = $urlGenerator->generate('detail_people', ['id' => $people->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        // Supprime le cache
        $this->cache->invalidateTags(["peopleCache"]);

        return new JsonResponse($jsonPeople, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /*
     * Mise à jour des informations de la personnalité
     */
    #[Route('/api/peoples/{id}', name: 'update_people', methods: ['PUT'])]
    public function updatePeople(People $people, Request $request, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        // Si l'authentification échoue
        if (!$this->security->isAuthentified()) {
            return new JsonResponse(['authentification' => 'failed'], Response::HTTP_FORBIDDEN, ['accept' => 'json'], false);
        }

        $firstname = $request->get("firstname", null);
        $lastname = $request->get("lastname", null);
        $nationality = $request->get("nationality", null);
        $birthday = $request->get("birthday", null);

        if ($firstname == null || $lastname == null || $nationality == null || $birthday == null){
            return new JsonResponse(['status' => 'error', 'message' => 'Veuillez renseigner les informations obligatoires.'], Response::HTTP_BAD_REQUEST, ["Location" => $location], false);
        }

        if ($firstname != null)
            $people->setFirstname($firstname);
        if ($lastname != null)
            $people->setLastname($lastname);
        if ($nationality != null)
            $people->setNationality($nationality);
        if ($birthday != null)
        {
            // convertir la date retournée
            $birthday = $this->format->convertDateStringToDateTime($birthday);
            $people->setDateOfBirth($birthday);
        }

        $em->persist($people);
        $em->flush();

        $jsonPeople = $this->format->json($people);

        $location = $urlGenerator->generate('detail_people', ['id' => $people->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        // Supprime le cache
        $this->cache->invalidateTags(["peopleCache"]);

        return new JsonResponse($jsonPeople, Response::HTTP_CREATED, ["Location" => $location], true);
    }

}
