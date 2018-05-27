<?php

use Cradle\Package\System\Schema;

return function($request, $response) {
    /**
     * Add Template Builder
     */
    $this->package('cradlephp/cradle-pipeline')->addMethod('template', function (
        // $type,
        $file,
        array $data = [],
        $partials = []
    ) {
        // get the root directory
        // $type = ucwords($type);
        $root =  sprintf('%s/template/', __DIR__);

        // check for partials
        if (!is_array($partials)) {
            $partials = [$partials];
        }

        $paths = [];

        foreach ($partials as $partial) {
            //Sample: product_comment => product/_comment
            //Sample: flash => _flash
            $path = str_replace('_', '/', $partial);
            $last = strrpos($path, '/');

            if($last !== false) {
                $path = substr_replace($path, '/_', $last, 1);
            }

            $path = $path . '.html';

            if (strpos($path, '_') === false) {
                $path = '_' . $path;
            }

            $paths[$partial] = $root . $path;
        }

        $file = $root . $file . '.html';

        //render
        return cradle('global')->template($file, $data, $paths);
    });
};
