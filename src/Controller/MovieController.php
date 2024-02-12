<?php
/**
 * @category  Videotheque
 * @author    Guillaume Deloffre <guillaume.deloffre@gmail.com>
 * @date      2024
 */

namespace App\Controller;

use App\Entity\Movie;
use App\Classes\Constants;
use App\Repository\MovieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MovieController extends AbstractController
{
    private $format;

    private $cache;

    private $security;

    private $imdb;

    public function __construct(
        \App\Helper\Format $format,
        \App\Helper\Security $security,
        \Symfony\Contracts\Cache\TagAwareCacheInterface $cache,
        \App\Classes\Imdb $imdb
    )
    {
        $this->format = $format;
        $this->cache = $cache;
        $this->security = $security;
        $this->imdb = $imdb;
    }

    /*
     * Récupère toutes les informations des films de la base de données y compris les artistes et les types
     * (une méthode de récupération des informations avec optimisation des ressources par pagination)
     */
    #[Route('/api/movies', name: 'movies', methods: ['GET'])]
    public function getAllMovieList(MovieRepository $movieRepository,
                                    Request $request): JsonResponse {

        // Nous pourrions également utiliser $this->getParameter('api.pagination_limit');
        // en renseignant dans le fichier config\services.yaml api.pagination_limit: 25
        $paginationLimit = Constants::LIMIT_RESULTS_BY_PAGE;

        // Request parameters
        $page  = $request->get('page', 1);
        $limit = $request->get('limit', $paginationLimit);
        $isCached = $request->get('cache', true);

        // si GET limit plus grand que la limite fixée, on ne garde que la valeur limite maximum
        $limit = min($limit, $paginationLimit);

        if (Constants::CACHE_ENABLE && $isCached) {
            // Pour de meilleurs performance, nous allons stocker les résultats de la pagination en cache
            // Si les informations n'existent pas, on va les chercher, sinon nous récupérons la valeur stockée en cache
            $cacheId = "getAllMovies-" . $page . "-" . $limit;
            $returnList = $this->cache->get($cacheId, function (ItemInterface $item) use ($movieRepository, $page, $limit) {
                $item->tag("moviesCache");
                $item->expiresAfter(Constants::CACHE_TIMEOUT);
                // récupère la liste des films
                $moviesList = $movieRepository->findAllWithPagination($page, $limit);
                return $this->format->json($moviesList);
            });
        }
        else{
            // récupère la liste des films
            $moviesList = $movieRepository->findAllWithPagination($page, $limit);
            $returnList = $this->format->json($moviesList);
        }

        return new JsonResponse($returnList, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /*
     * Récupère le detail de toutes les informations d'un film y compris les artistes et les types
     * (une méthode de récupération des informations de façon plus clair avec moins de code)
     */
    #[Route('/api/movies/{id}', name: 'movie_detail', methods: ['GET'])]
    public function getDetailMovie(Movie $movie): JsonResponse {
        $jsonMovie = $this->format->json($movie);
        return new JsonResponse($jsonMovie, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /*
     * Récupère la liste de tous les films par la recherche de titre
     */
    #[Route('/api/movies/search/{title}', name: 'movie_search', methods: ['GET'])]
    public function getSearchMovie(MovieRepository $movieRepo, string $title): JsonResponse {
        // Recherche tous les films qui contiennent une partie du titre
        $movie = $movieRepo->findMovies($title);
        $jsonMovie = $this->format->json($movie);
        return new JsonResponse($jsonMovie, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /*
     * Récupère le detail de toutes les informations d'un film y compris les artistes et les types
     */
    #[Route('/api/movies/{id}/all', name: 'movie_all_details', methods: ['GET'])]
    public function getAllDetailsMovie(int $id, MovieRepository $movieRepository): JsonResponse {
        // Récupérer le film par son ID avec les personnes associées
        $movie = $movieRepository->find($id);

        if (!$movie) {
            return new JsonResponse(['message' => 'Film non trouvé'], Response::HTTP_NOT_FOUND, ['accept' => 'json'], false);
        }

        // Récupérer la liste des personnes associées au film
        $people = $movie->getPeople();

        // Récupérer la liste des types associées au film
        $types = $movie->getType();

        // Construire la réponse JSON
        $response = [
            'id' => $movie->getId(),
            'title' => $movie->getTitle(),
            'duration' => $movie->getDuration(),
            'people' => [], // Placeholder, à peupler avec les personnes
            'type' => [] // Placeholder, à peupler avec les types
        ];

        // Ajouter les personnes
        foreach ($people as $person) {
            $response['people'][] = [
                'id' => $person->getId(),
                'firstname' => $person->getFirstname(),
                'lastname' => $person->getLastname(),
                'date_of_birth' => $person->getDateOfBirth()->format('Y-m-d'),
                'nationality' => $person->getNationality(),
            ];
        }

        // Ajouter les types
        foreach ($types as $type) {
            $response['type'][] = [
                'id' => $type->getId(),
                'name' => $type->getName()
            ];
        }

        return new JsonResponse($response, Response::HTTP_OK, ['accept' => 'json'], false);
    }

    /*
     * Supprime un film
     */
    #[Route('/api/movies/{id}', name: 'delete_movie', methods: ['DELETE'])]
    public function deleteMovie(Movie $movie, EntityManagerInterface $em): JsonResponse
    {
        // Si l'authentification échoue
        if (!$this->security->isAuthentified()) {
            return new JsonResponse(['authentification' => 'failed'], Response::HTTP_FORBIDDEN, ['accept' => 'json'], false);
        }

        // Supprime le cache
        $this->cache->invalidateTags(["moviesCache"]);
        $em->remove($movie);
        $em->flush();
        return new JsonResponse(['status' => 'success'], Response::HTTP_OK, ['accept' => 'json'], false);
    }

    /*
     * Ajout d'un nouveau film
     */
    #[Route('/api/movies', name: 'add_movie', methods: ['POST'])]
    public function addMovie(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        // Si l'authentification échoue
        if (!$this->security->isAuthentified()) {
            return new JsonResponse(['authentification' => 'failed'], Response::HTTP_FORBIDDEN, ['accept' => 'json'], false);
        }

        $title = $request->get("title", null);
        $duration = $request->get("duration", null);

        if ($title == null || $duration == null){
            return new JsonResponse(['status' => 'error', 'message' => 'Veuillez renseigner le titre et la durée du film.'], Response::HTTP_BAD_REQUEST, ["Location" => $location], false);
        }

        //$movie = $serializer->deserialize($request->getContent(), Movie::class, 'json');

        $movie = new Movie();
        $movie->setTitle($title);
        $movie->setDuration($duration);

        // On récupère le poster
        $poster = $this->imdb->getImdbPoster($title);
        if($poster)
        {
            $movie->setPoster($poster);
        }

        $em->persist($movie);
        $em->flush();

        $jsonMovie = $this->format->json($movie);

        $location = $urlGenerator->generate('movie_detail', ['id' => $movie->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        // Supprime le cache
        $this->cache->invalidateTags(["moviesCache"]);

        return new JsonResponse($jsonMovie, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /*
     * Mise à jour des informations du film
     */
    #[Route('/api/movies/{id}', name: 'update_movie', methods: ['PUT'])]
    public function updateMovie(Movie $movie, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        // Si l'authentification échoue
        if (!$this->security->isAuthentified()) {
            return new JsonResponse(['authentification' => 'failed'], Response::HTTP_FORBIDDEN, ['accept' => 'json'], false);
        }

        $title = $request->get("title", null);
        $duration = $request->get("duration", null);

        if ($title == null && $duration == null){
            return new JsonResponse(['status' => 'error', 'message' => 'Veuillez renseigner le titre ou la durée du film.'], Response::HTTP_BAD_REQUEST, ["Location" => $location], false);
        }

        if ($title != null)
            $movie->setTitle($title);
        if ($duration != null)
            $movie->setDuration($duration);

        // On récupère le poster si celui-ci n'existe pas dans la base, sinon on conserve l'informations de la bdd
        $poster = $this->imdb->getImdbPoster($title);
        if($poster)
        {
            $movie->setPoster($poster);
        }

        $em->persist($movie);
        $em->flush();

        $jsonMovie = $this->format->json($movie);

        $location = $urlGenerator->generate('movie_detail', ['id' => $movie->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        // Supprime le cache
        $this->cache->invalidateTags(["moviesCache"]);

        return new JsonResponse($jsonMovie, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /*
     * Récupère l'url du poster d'un film si celui-ci existe à partir de l'identifiant d'un film
     * On force la mise à jour dans la base de données
     */
    #[Route('/api/movies/{id}/poster', name: 'imdb_movie', methods: ['GET'])]
    public function imdbMovie(Movie $movie, Request $request, SerializerInterface $serializer, EntityManagerInterface $em): JsonResponse
    {
        $title = $movie->getTitle();
        $poster = $this->imdb->getImdbPoster($title);
        if(!$poster)
        {
            return new JsonResponse(['status' => 'error', 'message' => 'Le poster de ce film n\'est pas disponible'], Response::HTTP_BAD_REQUEST, [], false);
        }

        // On met à jour les informations dans la bdd
        $movie->setPoster($poster);
        $em->persist($movie);
        $em->flush();

        return new JsonResponse(['status' => 'success', 'message' => $poster], Response::HTTP_OK, ['accept' => 'json'], false);
    }
}
