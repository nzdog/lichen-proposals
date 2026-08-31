<?php
/* Copy this to _config.php on the server and set your own keys.
   _config.php is NOT in git and is NOT deployed — it exists only on the host,
   so a push can never publish a key and a deploy can never overwrite it.

   Make keys long and random. From a terminal:
       openssl rand -base64 18 | tr '/+' '_-'

   Rotate by editing this one file in cPanel. Nothing else depends on it. */
return [
    // Opens the generator:   /p/_new.php?k=...
    'new_key'  => 'CHANGE-ME-generator',

    // Opens the activity log: /p/_view.php?k=...
    'view_key' => 'CHANGE-ME-viewer',

    // BCC'd on every covering email written from the built-proposal panel,
    // so there is an archive of what was actually sent. Leave empty for none.
    'archive_email' => '',
];
