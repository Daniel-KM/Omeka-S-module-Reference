<?php declare(strict_types=1);

namespace Reference\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Reference\Stdlib\References as ReferencesService;

/**
 * Controller plugin wrapper for References service.
 *
 * @see \Reference\Stdlib\References
 *
 * @method ReferencesService setMetadata($metadata = [])
 * @method array getMetadata()
 * @method ReferencesService setQuery(?array $query = [])
 * @method array getQuery()
 * @method ReferencesService setOptions(?array $options)
 * @method array getOptions($key = null)
 * @method array list()
 * @method array count()
 * @method array initials()
 */
class References extends AbstractPlugin
{
    /**
     * @var \Reference\Stdlib\References
     */
    protected $references;

    public function __construct(ReferencesService $references)
    {
        $this->references = $references;
    }

    public function __invoke($metadata = [], ?array $query = [], ?array $options = []): ReferencesService
    {
        return $this->references->__invoke($metadata, $query, $options);
    }

    public function __call(string $name, array $arguments)
    {
        return $this->references->$name(...$arguments);
    }
}
