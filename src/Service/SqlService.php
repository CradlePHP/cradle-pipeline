<?php //-->
/**
 * This file is part of a package designed for the CradlePHP Project.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

namespace Cradle\Package\Pipeline\Service;

use PDO as Resource;
use Cradle\Storm\SqlFactory;

use Cradle\Module\Utility\Service\SqlServiceInterface;
use Cradle\Module\Utility\Service\AbstractSqlService;

use Cradle\Package\System\Model\Service\SqlService as ModelService;

use Cradle\Package\System\Schema;
use Cradle\Package\System\Exception as SystemException;

/**
 * Model SQL Service
 *
 * @vendor   Cradle
 * @package  System
 * @author   Christan Blanquera <cblanquera@openovate.com>
 * @standard PSR-2
 */
class SqlService extends ModelService
{
    /**
     * Update to database
     *
     * @param array $data
     *
     * @return array
     */
    public function update(array $data)
    {
        if (is_null($this->schema)) {
            throw SystemException::forNoSchema();
        }

        $table = $this->schema->getName();
        $updated = $this->schema->getUpdatedFieldName();

        if ($updated) {
            $data[$updated] = date('Y-m-d H:i:s');
        }

        // we will be using moved, since
        // 'order' is used for sorting
        // if there is moved, the the user
        // attempts to update ordering
        if (isset($data['moved'])) {
            $query = $this->resource
                ->getUpdateQuery($table)
                ->where($data['filters']);

            if (!isset($data['fields'])) {
                $data['fields'] = [];
            }

            // update the update field if any
            if ($updated) {
                $data['fields'][$updated] = "'" . $data[$updated] . "'";
            }

            // add fields to be updated
            foreach ($data['fields'] as $field => $value) {
                $query->set($field, $value);
            }

            if (isset($data['previous_column']) && $data['previous_column']) {
                cradle()->inspect($query->getQuery());
            }

            // we need to assign as we might end from here
            $results = $this->resource->query($query);
        }

        // if this is for the previous column update only
        // we have nothing to update against a specific
        // column, so we have to go back from here
        if (isset($data['previous_column']) && $data['previous_column']) {
            return [];
        }

        return $this
            ->resource
            ->model($data)
            ->save($table)
            ->get();
    }
}
