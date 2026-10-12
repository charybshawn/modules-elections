<?php

return [

    /*
     * Who the election update email comes from. Set these in the app's .env to
     * override them for this email only; every other email keeps the app's
     * MAIL_FROM_NAME and MAIL_FROM_ADDRESS.
     *
     *   ELECTIONS_MAIL_FROM_NAME="Salmon Arm Election Tracker"
     *   ELECTIONS_MAIL_FROM_ADDRESS=elections@example.com   (optional; must be a sender your mail provider allows)
     *
     * With no address set, the app's MAIL_FROM_ADDRESS is used.
     */
    'mail' => [
        'from_name' => env('ELECTIONS_MAIL_FROM_NAME', 'Team Cult Pantry'),
        'from_address' => env('ELECTIONS_MAIL_FROM_ADDRESS'),
    ],

];
