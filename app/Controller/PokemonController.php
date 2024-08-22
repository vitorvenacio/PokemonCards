<?php
namespace App\Controller;
use Hyperf\Coroutine\WaitGroup;
use Hyperf\Guzzle\ClientFactory;
use Swoole\Coroutine;
use Psr\SimpleCache\CacheInterface;


class PokemonController  extends AbstractController

{
    private $clientFactory;

    public function __construct(ClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

public function getRandomPokemon(CacheInterface $cache)
{
    $wg = new WaitGroup();
    $client = $this->clientFactory->create();
    $pokemonData = [];
    
    // Batching de IDs para reduzir chamadas
    $ids = [];
    for ($i = 0; $i < 5; $i++) {
        $ids[] = rand(1, 1025);
    }

    // Obtenção dos dados básicos dos Pokémon em batch
    $wg->add();
    Coroutine::create(function () use ($wg, $client, $ids, &$pokemonData, $cache) {
        $responses = [];

        foreach ($ids as $id) {
            $cacheKey = "pokemon_{$id}";
            if ($cache->has($cacheKey)) {
                $responses[$id] = $cache->get($cacheKey);
            } else {
                $response = $client->get("https://pokeapi.co/api/v2/pokemon/{$id}");
                $responses[$id] = json_decode($response->getBody()->getContents(), true);
                $cache->set($cacheKey, $responses[$id], 3600); // Cache por 1 hora
            }
        }

        foreach ($responses as $id => $pokemon) {
            $moves = array_map(function ($move) {
                return [
                    'name' => $move['move']['name'],
                    'url' => $move['move']['url'],
                ];
            }, $pokemon['moves']);

            $pokemonData[] = [
                'name' => $pokemon['name'],
                'image' => $pokemon['sprites']['other']['official-artwork']['front_default'],
                'moves' => $moves,
            ];
        }

        $wg->done();
    });

    $wg->wait();

    // Obter efeitos dos movimentos usando corrotinas e cache
    foreach ($pokemonData as &$pokemon) {
        foreach ($pokemon['moves'] as &$move) {
            $wg->add();
            Coroutine::create(function () use ($wg, $client, &$move, $cache) {
                $cacheKey = "move_{$move['name']}";
                if ($cache->has($cacheKey)) {
                    $move['effect'] = $cache->get($cacheKey);
                } else {
                    $moveResponse = $client->get($move['url']);
                    $moveData = json_decode($moveResponse->getBody()->getContents(), true);
                    $move['effect'] = $moveData['effect_entries'][0]['effect'] ?? 'No effect found';
                    $cache->set($cacheKey, $move['effect'], 3600); // Cache por 1 hora
                }
                $wg->done();
            });
        }
    }

    $wg->wait(); // Espera que todas as corrotinas para os movimentos terminem

    return $pokemonData;
}
}