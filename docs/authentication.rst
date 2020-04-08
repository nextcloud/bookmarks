==============
Authentication
==============


.. _authentication:

User-based authentication
=========================

In order to access the REST API you will need to provide credentials for the user on behalf of which you'd
like to access the bookmarks app. This should be done using Basic Auth and must happen for every request.


.. sourcecode:: http

  GET /index.php/apps/bookmarks/public/rest/v2/bookmark HTTP/1.1
  Host: example.com
  Accept: application/json
  Authorization: basic 345678ikmnbvcdewsdfgzuiolkmnbvfr==

Token-based authentication
==========================
If a user has shared one of their folders publicly, you can access its contents via the token as part of the public link.
You may bass this token to the various endpoints using the ``token`` GET-parameter or by setting it as
part of the ``Authorization header``.

.. sourcecode:: http

  GET /index.php/apps/bookmarks/public/rest/v2/bookmark?token=j5KJr7c HTTP/1.1
  Host: example.com
  Accept: application/json

.. sourcecode:: http

  GET /index.php/apps/bookmarks/public/rest/v2/bookmark HTTP/1.1
  Host: example.com
  Accept: application/json
  Authorization: bearer j5KJr7c
