<?php
/**
 * Created by PhpStorm.
 * User: krona
 * Date: 4/22/14
 * Time: 2:43 PM
 */
return [
    'arilas' => [
        'whoops' => [
            'disabled' => false,
            'blacklist' => [
                //Exceptions that we doesn't handling
            ],
            'handler' => [
                'type' => 'Whoops\Handler\PrettyPageHandler',
                'options_type' => 'prettyPage',
                'options' => [
                    'editor' => 'sublime',
                ],
            ],
        ],
    ],
];