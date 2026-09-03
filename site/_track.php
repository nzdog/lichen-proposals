<?php
/* Answers 204 and records nothing.

   Proposals built before September 2026 carry a script that calls this address
   when the page is opened, when the reader reaches the offer, and when they
   reach the end. Tracking was switched off then. The deploy cannot delete files
   from the server, and a missing endpoint would leave those pages logging a 404
   on every open, so the address stays and the server does nothing with what
   arrives. Nothing is written, nothing is sent, nothing is read.

   Proposals built since carry no such script. When every page from before that
   date is gone, this file can go too. */
http_response_code(204);
