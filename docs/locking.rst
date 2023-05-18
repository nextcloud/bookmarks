.. _locking:

=======
Locking
=======

As a client application of this API will likely make many requests in succession to synchronize its state with the app, a simple per-user locking mechanism was implemented to let clients know when a different client is currently making changes.

Acquire lock
============

.. POST:: /public/rest/v2/lock

   :synopsis: Acquire a lock for the authenticated user. This lock will be automatically released after a timeout of 30min. If the client requesting the lock needs longer than 30min it has to repeat the request.

   .. versionadded:: 10.0.0

   :>json string status: ``success`` or ``error``

   **Example:**

   .. sourcecode:: http

      POST /index.php/apps/bookmarks/public/rest/v2/lock HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
      }

   **Example:**

   .. sourcecode:: http

      POST /index.php/apps/bookmarks/public/rest/v2/lock HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 423 Locked
      Content-Type: application/json

      {
        "status": "error", "data": "Resource is already locked"
      }

Release lock
============

.. DELETE:: /public/rest/v2/lock

   :synopsis: Release the lock for the authenticated user

   .. versionadded:: 10.0.0

   :>json string status: ``success`` or ``error``

   **Example:**

   .. sourcecode:: http

      DELETE /index.php/apps/bookmarks/public/rest/v2/lock HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
      }