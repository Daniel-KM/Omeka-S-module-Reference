<?php declare(strict_types=1);

namespace Reference\Controller;

use Laminas\Http\Response;
use Omeka\Api\Manager as ApiManager;
use Omeka\Stdlib\Paginator;
use Omeka\View\Model\ApiJsonModel;

/**
 * This controller extends the Omeka Api controller in order to manage rights
 * in the same way. This part is not a rest api (it does not manage resources).
 */
class ApiController extends \Omeka\Controller\ApiController
{
    public function __construct(Paginator $paginator, ApiManager $api)
    {
        $this->paginator = $paginator;
        $this->api = $api;
    }

    public function create($data, $fileData = [])
    {
        return $this->returnErrorMethodNotAllowed();
    }

    public function delete($id)
    {
        return $this->returnErrorMethodNotAllowed();
    }

    public function deleteList($data)
    {
        return $this->returnErrorMethodNotAllowed();
    }

    public function get($id)
    {
        return $this->returnErrorMethodNotAllowed();
    }

    public function getList()
    {
        $query = $this->cleanQuery();

        // Field may be an array.
        // Empty string field means meta results.
        $field = $query['metadata'] ?? [];
        $fields = is_array($field) ? $field : [$field];
        $fields = array_unique($fields);

        unset($query['metadata']);

        $resourceName = $this->params('resource');
        if ($resourceName) {
            $query['resource_name'] = $resourceName;
        }

        $options = $query;
        if (array_key_exists('query', $options)) {
            $query = is_array($options['query']) ? $options['query'] : ['text' => $options['query']];
        }

        if (isset($options['option']) && is_array($options['option'])) {
            $options = $options['option'];
        }

        unset($query['query']);
        unset($query['option']);
        unset($options['query']);
        unset($options['option']);

        // Text is full text, but full text doesn't work via api.
        if (array_key_exists('text', $query) && strlen($query['text'])) {
            $query['property'][] = [
                'joiner' => 'and',
                'property' => '',
                'type' => 'in',
                'text' => $query['text'],
            ];
        }
        unset($query['text']);
        unset($query['per_page']);
        unset($query['page']);
        unset($query['sort_by']);
        unset($query['sort_order']);
        unset($query['offset']);
        unset($query['limit']);

        $result = $this->references($fields, $query, $options)->list();
        return new ApiJsonModel($result, $this->getViewOptions());
    }

    public function head($id = null)
    {
        return $this->returnErrorMethodNotAllowed();
    }

    public function options()
    {
        return $this->returnErrorMethodNotAllowed();
    }

    public function patch($id, $data)
    {
        return $this->returnErrorMethodNotAllowed();
    }

    public function replaceList($data)
    {
        return $this->returnErrorMethodNotAllowed();
    }

    public function patchList($data)
    {
        return $this->returnErrorMethodNotAllowed();
    }

    public function update($id, $data)
    {
        return $this->returnErrorMethodNotAllowed();
    }

    public function notFoundAction()
    {
        return $this->returnError(
            $this->translate('Page not found'), // @translate
            Response::STATUS_CODE_404
        );
    }

    protected function returnErrorMethodNotAllowed()
    {
        return $this->returnError(
            $this->translate('Method Not Allowed'), // @translate
            Response::STATUS_CODE_405
        );
    }

    protected function returnError($message, $statusCode = Response::STATUS_CODE_400, array $errors = null)
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        $result = [
            'status' => $statusCode,
            'message' => $message,
        ];
        if (is_array($errors)) {
            $result['errors'] = $errors;
        }
        return new ApiJsonModel($result, $this->getViewOptions());
    }

    /**
     * Clean the query (site slug is not managed by Omeka).
     *
     * @return array
     */
    protected function cleanQuery()
    {
        $query = $this->params()->fromQuery();
        if (empty($query['site_id']) && !empty($query['site_slug'])) {
            $siteSlug = $query['site_slug'];
            if ($siteSlug) {
                $api = $this->api();
                $siteId = $api->searchOne('sites', ['slug' => $siteSlug], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
                if ($siteId) {
                    $query['site_id'] = $siteId;
                }
            }
        }
        return $query;
    }
}
