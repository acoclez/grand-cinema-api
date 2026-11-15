<?php

namespace Tests\Doubles;

use Stripe\HttpClient\ClientInterface;
use Stripe\Util\CaseInsensitiveArray;

/**
 * Client HTTP du SDK Stripe pour les tests: il enregistre les requêtes et renvoie les réponses
 * préparées par le test, sans appel réseau.
 */
class ClientHttpStripeSimule implements ClientInterface
{
    /** @var list<array{methode: string, url: string, entetes: list<string>, parametres: array<string, mixed>}> */
    public array $requetes = [];

    /**
     * @param  list<array<string, mixed>>  $reponses  Corps JSON des réponses, dans l'ordre des requêtes
     */
    public function __construct(private array $reponses) {}

    /**
     * @param  'delete'|'get'|'post'  $method
     * @param  string  $absUrl
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $params
     * @param  bool  $hasFile
     * @param  'v1'|'v2'  $apiMode
     * @param  int|null  $maxNetworkRetries
     * @return array{0: string, 1: int, 2: CaseInsensitiveArray}
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        $this->requetes[] = ['methode' => $method, 'url' => $absUrl, 'entetes' => $headers, 'parametres' => $params];

        return [(string) json_encode(array_shift($this->reponses) ?? []), 200, new CaseInsensitiveArray];
    }
}
