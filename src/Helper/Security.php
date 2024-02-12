<?php
/**
 * Fonctions de sécurisation
 *
 * @category  Videotheque
 * @author    Guillaume Deloffre <guillaume.deloffre@gmail.com>
 * @date      2024
 */

namespace App\Helper;

use App\Classes\Constants;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class Security {
    private $requestStack;

    public function __construct(
        \Symfony\Component\HttpFoundation\RequestStack $requestStack
    )
    {
        $this->requestStack = $requestStack;
    }

    /*
     * Fonction qui permet de savoir si l'authentification par clée privée est vrai
     */
    public function isAuthentified(): bool {
        // On récupère la clé secrète passée dans le header
        $token = $this->requestStack->getCurrentRequest()->headers->get('token', false);
        return ($token == Constants::SECRET_KEY)?true:false;
    }

}