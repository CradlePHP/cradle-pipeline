<?php //-->
/**
 * This file is part of a package designed for the CradlePHP Project.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

use Cradle\Package\System\Schema;

use Cradle\Http\Request;
use Cradle\Http\Response;


/**
 * Render the System Model Pipeline Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/system/model/:schema/pipeline', function ($request, $response) {
    //----------------------------//
    // 1. Prepare Data
    $data = $request->getStage();

    // set redirect
    $redirect = sprintf(
        '/admin/system/model/%s/search',
        $request->getStage('schema')
    );

    if ($request->getStage('redirect_uri')) {
        $redirect = $request->getStage('redirect_uri');
    }

    $request->setStage('redirect_uri', $redirect);

    if (!isset($data['ajax'])) {
        $data['ajax'] = [];
    }

    // if no ajax set, set this page as default
    if (!isset($data['ajax']['pull']) || empty($data['ajax']['pull'])) {
        $data['ajax']['pull'] = sprintf(
            '/admin/system/model/%s/search',
            $request->getStage('schema')
        );
    }

    if (!isset($data['ajax']['update']) || empty($data['ajax']['update'])) {
        $data['ajax']['update'] = sprintf(
            '/admin/system/model/%s/pipeline',
            $request->getStage('schema')
        );
    }

    // if no detail set, set update page as the default
    if (!isset($data['detail']) || empty($data['detail'])) {
        $data['detail'] = sprintf(
            '/admin/system/model/%s/update',
            $request->getStage('schema')
        );
    }

    //----------------------------//
    // 2. Validate
    // does the schema exists?
    try {
        $data['schema'] = Schema::i($request->getStage('schema'))->getAll();
    } catch (\Exception $e) {
        $message = $this
            ->package('global')
            ->translate($e->getMessage());

        $redirect = '/admin/system/schema/search';
        $response->setError(true, $message);
    }

    // if this is a return back from processing
    // this form and it's because of an error
    if ($response->isError()) {
        //pass the error messages to the template
        $this
            ->package('global')
            ->flash($response->getMessage(), 'error');
        $this
            ->package('global')
            ->redirect($redirect);
    }

    // check what to show
    if (!isset($data['show']) || !$data['show']) {
        // flash an error message and redirect
        $error = $this
            ->package('global')
            ->translate('Please specify what to plot.');
        $this
            ->package('global')
            ->flash($error, 'error');
        $this
            ->package('global')
            ->redirect($redirect);
    }

    // minimize long array chain
    $fields = $data['schema']['fields'];
    // pipeline stages
    $data['stages'] = $fields[$data['show']]['field']['options'];

    $allowed = ['select', 'radios'];

    if (!isset($fields[$data['show']])
        || !in_array($fields[$data['show']]['field']['type'], $allowed)
    ) {
        $error = $this
            ->package('global')
            ->translate('%s is not a select/radio field', $data['show']);
        $this
            ->package('global')
            ->flash($error, 'error');
        $this
            ->package('global')
            ->redirect($redirect);
    }

    $dates = ['date', 'datetime', 'created', 'updated', 'time', 'week', 'month'];
    if (isset($data['date'])
        && (!isset($fields[$data['date']])
            || !in_array($fields[$data['date']]['field']['type'], $dates))
    ) {
        $error = $this
            ->package('global')
            ->translate('%s is not a type of date field', $data['date']);
        $this
            ->package('global')
            ->flash($error, 'error');
        $this
            ->package('global')
            ->redirect($redirect);
    }

    // initialize the stageHeader keys into null
    // so that even there's no total or range, it will not get errors
    $data['stageHeader'] = [
        'total' => null,
        'minRange' => null,
        'maxRange' => null
    ];

    $rangeFieldTypes = ['number', 'small', 'float', 'price'];
    $fields = $data['schema']['fields'];

    // check if the user wants to display the total
    if (isset($data['range'])) {
        if (strpos($data['range'], ',') !== false) {
            $data['range'] = explode(',', $data['range']);
        }

        // if not array, make it an array
        if (!is_array($data['range'])) {
            $data['range'] = [$data['range']];
        }

        foreach ($data['range'] as $field) {
            if (isset($fields[$field])
                && in_array($fields[$field]['field']['type'], $rangeFieldTypes)
            ) {
                continue;
            }

            // flash error message
            $error = $this
                ->package('global')
                ->translate('%s is not a number type field', $field);

            $this
                ->package('global')
                ->flash($error, 'error');

            $this
                ->package('global')
                ->redirect($redirect);
        }
    }

    if (isset($data['total'])
        && (!isset($fields[$data['total']])
        || !in_array($fields[$data['total']]['field']['type'], $rangeFieldTypes))
    ) {
        // flash error message
        $error = $this
            ->package('global')
            ->translate('%s is not a number type field', $data['total']);

        $this
            ->package('global')
            ->flash($error, 'error');

        $this
            ->package('global')
            ->redirect($redirect);
    }

    $data['schema']['filterable'] = array_values($data['schema']['filterable']);
    //----------------------------//
    // 3. Render Template
    $data['title'] = $this
        ->package('global')
        ->translate('%s Pipeline', $data['schema']['singular']);

    $class = sprintf('page-admin-%s-pipeline page-admin', $request->getStage('schema'));
    $body = $this
        ->package('cradlephp/cradle-pipeline')
        ->template('board', $data, ['search_form', 'search_filters']);

    // Set Content
    $response
        ->setPage('title', $data['title'])
        ->setPage('class', $class)
        ->setContent($body);

    // Render blank page
    $this->trigger('admin-render-page', $request, $response);
});

/**
 * Process Pipeline Update
 *
 * @param Request $request
 * @param Response $response
 */
$this->post('/admin/system/model/:schema/pipeline', function ($request, $response) {
    //----------------------------//
    // get json response data only
    $request->setStage('redirect_uri', 'false');
    $request->setStage('render', 'false');

    $data = [];
    if ($request->getStage()) {
        $data = $request->getStage();
    }

    $filters = [];

    // if it reached here, then we're assumming
    // that the user attempted to do an order/sort update
    if (isset($data['moved']) && isset($data['sort'])) {
        if (isset($data['stage']) && isset($data[$data['stage']])) {
            $filters[] = sprintf(
                '%s = "%s"',
                $data['stage'],
                $data[$data['stage']]
            );
        }

        // if it was moved upwards
        // then we only update the rows from this item to the previous elder
        if ($request->getStage('moved') == 'upwards') {
            $request->setStage(
                'fields',
                [$data['sort'] => $data['sort'] . '+1']
            );

            $filters[] = $data['sort'] . ' > ' . $data['new_elder'];

            if (!isset($data['previous_stage'])) {
                $filters[] = $data['sort'] . ' <= ' . ($data['previous_elder'] + 1);
            }
        }

        // if it was moved downwards
        // then we update from the previous elder to the newest elder
        if ($request->getStage('moved') == 'downwards') {
            $request->setStage(
                'fields',
                [$data['sort'] => $data['sort'] . '-1']
            );

            $filters[] = $data['sort'] . ' >= ' . $data['previous_elder'];

            if (!isset($data['previous_stage'])) {
                $filters[] = $data['sort'] . ' <= ' . $data['new_elder'];
            }
        }

        $request->setStage('filters', $filters);

        $this->trigger('pipeline-update', $request, $response);

        if (isset($data['previous_stage'])
            && $data['previous_stage'] !== $data[$data['stage']]
        ) {
            $request->setStage(
                'fields',
                [$data['sort'] => $data['sort'] . '-1']
            );

            // we should only update cards in the previous stage
            $filters = [sprintf(
                '%s = "%s"',
                $data['stage'],
                $data['previous_stage']
            )];

            $filters[] = $data['sort'] . ' > ' . $data['previous_elder'];

            $request->setStage('filters', $filters);
            $request->setStage('previous_column', true);
            $this->trigger('pipeline-update', $request, $response);
        }
    }
});
