<?php declare(strict_types=1);

namespace Reference\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Reference\Stdlib\ReferenceTree as ReferenceTreeService;

/**
 * Controller plugin wrapper for ReferenceTree service.
 *
 * @see \Reference\Stdlib\ReferenceTree
 *
 * @method array convertTreeToLevels(string $dashTree)
 * @method string convertLevelsToTree(array $levels)
 * @method string convertFlatLevelsToTree(array $levels)
 * @method array getTree($referenceLevels, array $query = null, array $options = null)
 */
class ReferenceTree extends AbstractPlugin
{
    /**
     * @var \Reference\Stdlib\ReferenceTree
     */
    protected $referenceTree;

    public function __construct(ReferenceTreeService $referenceTree)
    {
        $this->referenceTree = $referenceTree;
    }

    /**
     * Get the ReferenceTree object.
     *
     * @return ReferenceTreeService
     */
    public function __invoke(): ReferenceTreeService
    {
        return $this->referenceTree;
    }

    public function __call(string $name, array $arguments)
    {
        return $this->referenceTree->$name(...$arguments);
    }
}
