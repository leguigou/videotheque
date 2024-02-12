<?php
/**
 * Liste de Constantes globales
 *
 * @category  Videotheque
 * @author    Guillaume Deloffre <guillaume.deloffre@gmail.com>
 * @date      2024
 */

namespace App\Classes;

class Constants {
    // Limite le nombre de résultats lors d'un appel REST API
    const LIMIT_RESULTS_BY_PAGE = 10;

    // Activation du cache
    const CACHE_ENABLE = true;

    // Expiration du cache
    const CACHE_TIMEOUT = 3600;

    // Clé secrète (à garder secret! )
    const SECRET_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9';

    // IMDB api key
    const IMDB_API_KEY = '872831d0';
}