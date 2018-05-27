# Cradle Pipeline Package

Pipeline manager.

## Install

```
composer install cradlephp/cradle-pipeline
```

## Pipeline

Cradle Pipeline handles everything about the pipeline. It is based on CradlePHP/cradle-system.

### Pipeline Routes

The following routes are available in the admin.

 - `GET /admin/system/model/:schema/pipeline` - Pipeline search page
 - `POST /admin/system/model/:schema/pipeline` - Pipeline processor

### Pipeline Events

 - `pipeline-update`
