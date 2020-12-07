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
        $resourceName = $this->params('resource');
        if ($resourceName) {
            $query['resource_name'] = $resourceName;
        }

        // Field may be an array.
        // Empty string field means meta results.
        $field = $query['metadata'] ?? [];
        $fields = is_array($field) ? $field : [$field];
        $fields = array_unique($fields);

        // Either "field" or "text" is required.
        if (empty($fields)) {
            return new ApiJsonModel([], $this->getViewOptions());
        }
        unset($query['metadata']);

        $options = $query;
        $query = $options['query'] ?? [];
        unset($options['query']);

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
