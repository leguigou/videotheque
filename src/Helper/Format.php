<?php
/**
 * Fonctions de formatage
 *
 * @category  Videotheque
 * @author    Guillaume Deloffre <guillaume.deloffre@gmail.com>
 * @date      2024
 */

namespace App\Helper;

use Symfony\Component\Serializer\SerializerInterface;

class Format {

    private $serialize;

    public function __construct(SerializerInterface $serialize)
    {
        $this->serialize = $serialize;
    }

    /*
     * Fonction qui permet de s’assurer que le JSON généré est valide et ne contient que du texte en UTF-8
     */
    public function json($element): string {
        // Cette ligne sérialise l’élément en JSON
        $jsonList = $this->serialize->serialize($element, 'json');
        // Cette ligne réencode la structure de données en JSON, en s’assurant que les caractères Unicode ne sont pas échappés
        return json_encode(json_decode($jsonList), JSON_UNESCAPED_UNICODE);
    }

    /*
     * Fonction qui crée un objet DateTime à partir d'une chaine de caractères de forme date [aaaa-mm-dd]
     */
    public function convertDateStringToDateTime(string $dateString): \DateTime
    {
        $dateTime = \DateTime::createFromFormat('Y-m-d', $dateString);
        if ($dateTime === false) {
            throw new InvalidArgumentException('La chaîne de date est invalide.');
        }
        return $dateTime;
    }

}