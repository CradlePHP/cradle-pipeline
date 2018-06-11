<?php //-->
/**
 * This file is part of a package designed for the CradlePHP Project.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

use Cradle\Package\System\Schema;
use Cradle\Package\System\Exception;

use Cradle\Package\Pipeline\Service;
use Cradle\Package\System\Model\Validator;

use Cradle\Http\Request;
use Cradle\Http\Response;

/**
 * Updates a pipeline item
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('pipeline-update', function ($request, $response) {
    //----------------------------//
    // 1. Get Data
    $data = [];
    if ($request->hasStage()) {
        $data = $request->getStage();
    }

    if (!isset($data['schema'])) {
        throw Exception::forNoSchema();
    }

    $schema = Schema::i($data['schema']);

    if (isset($data['id'])) {
        $data[$schema->getPrimaryFieldName()] = $data['id'];
    }

    //
    // FIX: For import or in any part of the system
    // if primary is set but doesn't have a value.
    //
    if (isset($data[$schema->getPrimaryFieldName()])
    && empty($data[$schema->getPrimaryFieldName()])) {
        // remove the field instead
        unset($data[$schema->getPrimaryFieldName()]);
    }

    //----------------------------//
    // 2. Validate Data
    $errors = Validator::i($schema)
        ->getUpdateErrors($data);

    //if there are errors
    if (!empty($errors)) {
        return $response
            ->setError(true, 'Invalid Parameters')
            ->set('json', 'validation', $errors);
    }

    //----------------------------//
    // 3. Prepare Data
    // nothing to prepare or format
    //----------------------------//
    // 4. Process Data
    //this/these will be used a lot
    $modelSql = Service::get('sql')->setSchema($schema);
    $modelRedis = Service::get('redis')->setSchema($schema);
    $modelElastic = Service::get('elastic');

    //save object to database
    $results = $modelSql->update($data);

    //get the primary value
    $primary = $schema->getPrimaryFieldName();
    $relations = $schema->getRelations();

    //loop through relations
    foreach ($relations as $table => $relation) {
        //if 1:N, skip
        if ($relation['many'] > 1) {
            continue;
        }

        $current = $response->getResults();
        $lastId = null;

        // is the relation array?
        if (isset($current[$relation['name']])
        && is_array($current[$relation['name']])
        && isset($current[$relation['name']][$relation['primary2']])) {
            // get the primary id from the array
            $lastId = $current[$relation['name']][$relation['primary2']];

        // relation already merged with the primary?
        } else if(isset($current[$relation['primary2']])) {
            $lastId = $current[$relation['primary2']];
        }

        //if 0:1 and no primary
        if ($relation['many'] === 0
            && (
                !isset($data[$relation['primary2']])
                || !is_numeric($data[$relation['primary2']])
            )
        ) {
            //remove last id
            $modelSql->unlink(
                $relation['name'],
                $primary,
                $lastId
            );

            continue;
        }

        if (isset($data[$relation['primary2']])
            && is_numeric($data[$relation['primary2']])
            && $lastId != $data[$relation['primary2']]
        ) {
            //remove last id
            $modelSql->unlink(
                $relation['name'],
                $results[$primary],
                $lastId
            );

            //link current id
            $modelSql->link(
                $relation['name'],
                $results[$primary],
                $data[$relation['primary2']]
            );
        }
    }
    //index object
    $modelElastic->update($results[$primary]);

    //invalidate cache
    $uniques = $schema->getUniqueFieldNames();
    foreach ($uniques as $unique) {
        if (isset($data[$unique])) {
            $modelRedis->removeDetail($data[$unique]);
        }
    }

    $modelRedis->removeSearch();

    //return response format
    $response->setError(false)->setResults($results);
});
