<?php
/**
 *
 * @category  Videotheque
 * @author    Guillaume Deloffre <guillaume.deloffre@gmail.com>
 * @date      2024
 */

namespace App\Classes;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class Imdb
{
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function fetchApiData($title): array
    {
        $response = $this->client->request(
            'GET',
            'https://www.omdbapi.com/?apikey=' . Constants::IMDB_API_KEY . '&t=' . $title
        );

        $statusCode = $response->getStatusCode();
        if ($statusCode === 200) {
            $content = $response->getContent();
            $contentArray = $response->toArray();

            return $contentArray;
        }

        throw new \Exception("Erreur lors de l'appel à l'API: $statusCode");
    }

    public function getImdbPoster(string $title): string|bool
    {
        $poster = false;
        $data = $this->fetchApiData($title);
        if ($data["Response"] == "True") {
            $poster = $data["Poster"];
        }

        return $poster;
    }

}
