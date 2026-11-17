# laravel-api-geo

Client Laravel de l'API de géocodage Splitfire ([api-geo](https://github.com/splitfire-agency/api-geo)), joignable à travers la passerelle [api-gateway](https://github.com/splitfire-agency/api-gateway) sous `/geo`. Le contrat de réponse de référence est [`docs/contract.md`](https://github.com/splitfire-agency/api-geo/blob/main/docs/contract.md) d'api-geo.

Compatible Laravel 8.83 à 13, PHP 8.2 et plus.

## Installation

Le dépôt est privé :

```json
"repositories": [
    { "type": "vcs", "url": "git@github.com:splitfire-agency/laravel-api-geo.git" }
]
```

```bash
composer require splitfire/laravel-api-geo
```

La clé de service est une clé `sfg_live_…` de la passerelle portant les portées `geo:address` (`/search`) et `geo:reverse` (`/reverse`). Côté serveur, elle ne doit pas être restreinte par origine ; une restriction par IP reste possible.

| Variable | Défaut | Rôle |
|---|---|---|
| `API_GEO_KEY` | requise | clé de service |
| `API_GEO_URL` | `https://api.services.splitfire.fr/geo` | URL de base, passerelle comprise |
| `API_GEO_TIMEOUT` | `8` | délai de réponse, en secondes |
| `API_GEO_CONNECT_TIMEOUT` | `2` | délai de connexion, en secondes |
| `API_GEO_RETRY_TIMES` | `1` | nouvelles tentatives sur une indisponibilité |
| `API_GEO_RETRY_SLEEP_MS` | `1000` | attente avant une nouvelle tentative |

`php artisan vendor:publish --tag=api-geo-config` publie `config/api-geo.php`. Un fichier partiel est complété par les valeurs par défaut. Une valeur invalide (délai vide ou nul, URL vide) lève une `InvalidArgumentException` à la première résolution du client.

## Utilisation

Par injection de `Splitfire\ApiGeo\Contracts\Geocoder` ou par la façade `ApiGeo` :

```php
use Splitfire\ApiGeo\Data\Coordinates;
use Splitfire\ApiGeo\Data\FeatureType;
use Splitfire\ApiGeo\Facades\ApiGeo;
use Splitfire\ApiGeo\Query\ReverseQuery;
use Splitfire\ApiGeo\Query\SearchQuery;

$results = ApiGeo::search('8 boulevard du port amiens');

$results = ApiGeo::search(
    SearchQuery::for('8 bd du port')->autocomplete(false)->postcode('80000')->type(FeatureType::Address)->limit(1)
);

$results = ApiGeo::reverse(ReverseQuery::at(new Coordinates(lat: 49.8974, lon: 2.2900)));

foreach ($results as $feature) {
    $feature->label;              // 8 Boulevard du Port 80000 Amiens
    $feature->coordinates->lat;   // 49.897443
    $feature->citycode;           // 80021, ou null
}

$results->attribution;            // licence de la BAN, à afficher
$results->meta->requestId;        // X-Request-Id, pour corréler avec les journaux de la passerelle
$results->meta->rateLimit?->remaining;
```

- `SearchQuery` accepte `limit`, `autocomplete`, `type`, `postcode`, `citycode`, `departmentCode`, `regionCode` et `near`. `autocomplete(false)` convient au géocodage d'une adresse complète.
- Une requête est validée à sa construction, avec les règles du contrat : une valeur refusée lève `InvalidQuery` sans appel, donc sans consommer de quota.
- Les coordonnées sont tronquées comme le fait geo : 3 décimales pour `near`, 4 pour `reverse`.
- Zéro résultat n'est pas une erreur : `$results->isEmpty()`.
- Un résultat d'un type que cette version ne connaît pas est écarté.
- `Feature::$id` change à chaque publication de la BAN : il ne doit pas être conservé.
- `ApiGeo::withRequestId($id)` renvoie un client qui transmet cet identifiant.

## Erreurs

Les exceptions d'une requête implémentent `Splitfire\ApiGeo\Exceptions\ApiGeoException`. Celles qui viennent d'une réponse étendent `RequestFailed` et portent `errorCode`, `status`, `parameter`, `retryAfter` et `meta` (`requestId`, `rateLimit`).

| Exception | Cas |
|---|---|
| `InvalidQuery` | requête refusée avant tout appel ; `errorCode` et `parameter` comme dans le contrat |
| `RequestRejected` | 400 de geo |
| `AccessDenied` | clé absente, invalide, révoquée ou expirée, projet suspendu, portée, origine ou IP refusée |
| `QuotaExceeded` | limite minute ou jour (429), plafond mensuel (402) |
| `ServiceUnavailable` | passerelle, cache ou moteur indisponible, délai dépassé, connexion refusée, service désactivé |
| `UnexpectedResponse` | réponse hors contrat |

`retryAfter` reprend `Retry-After`. Pour une `QuotaExceeded`, il prend à défaut `RateLimit-Reset`, seul présent sur le plafond mensuel (fin du mois).

Une `ServiceUnavailable` est retentée `API_GEO_RETRY_TIMES` fois quand elle est passagère (`isTransient()`, faux pour `service_disabled`). Les autres erreurs ne le sont pas : un job peut relâcher une `QuotaExceeded` avec `$this->release($e->retryAfter ?? 60)`.

## Cache

Le package ne met rien en cache. Chaque appel passe par la passerelle et compte dans le quota, même servi depuis le cache d'api-geo. Une `FeatureCollection` se sérialise, et `FeatureCollection::fromArray()` reconstruit sa forme JSON :

```php
$key = 'geo:search:'.sha1(mb_strtolower(trim($text)));
$results = Cache::remember($key, now()->addWeek(), fn () => ApiGeo::search($text));
```

## Tests dans une application

Le client passe par la façade `Http`, `Http::fake()` s'applique :

```php
Http::fake(['api.services.splitfire.fr/geo/*' => Http::response($featureCollection)]);
```

Ou par substitution du contrat : `$this->app->instance(Geocoder::class, $fake)`.

## Développement

PHP et Composer tournent dans Docker (`PHP_VERSION`, 8.2 par défaut) :

```bash
docker compose run --rm php composer update
docker compose run --rm php vendor/bin/phpunit
docker compose run --rm php composer lint
```

Les versions de Laravel 8 à 11 ont des avis de sécurité ouverts : Composer refuse de les installer sans `--no-blocking`. Pour tester une version précise :

```bash
docker compose run --rm php composer update --no-blocking --with 'orchestra/testbench:6.*'
```

Les tests restent compatibles PHPUnit 9 à 12 : méthodes préfixées `test_` et fournisseurs de données déclarés en annotation et en attribut. Les fixtures de `tests/Fixtures` sont validées contre `geo-v1.schema.json`, copie du schéma d'api-geo.
