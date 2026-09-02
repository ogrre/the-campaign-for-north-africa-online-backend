<?php

return [
    'map_review_enabled' => env('CNA_MAP_REVIEW_ENABLED', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),
];
