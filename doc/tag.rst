====
Tags
====

.. contents::

Tag model
=========

.. object:: Tag

   A tag is just a string that can be added as a label to a bookmark

Get list of tags
================

.. get:: /public/rest/v2/tag

   :synopsis: Retrieve a list of tags

   .. versionadded:: 0.11.0

   **Example:**

   .. sourcecode:: http

      GET /index.php/apps/bookmarks/public/rest/v2/tag HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      ["politics", "satire", "tech", "music", "art", "blogs", "personal"]

Delete a tag
============

.. delete:: /public/rest/v2/tag/(string:tag)

   :synopsis: Delete a tag

   .. versionadded:: 0.11.0

   :>json string status: ``success`` or ``error``

   **Example:**

   .. sourcecode:: http

      DELETE /index.php/apps/bookmarks/public/rest/v2/tag/politics HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      { "status": "success" }

Rename a tag
============

.. put:: /public/rest/v2/tag/(string:tag)

   :synopsis: Rename a tag

   .. versionadded:: 0.11.0

   :<json string name: The new name for the tag

   :>json string status: ``success`` or ``error``

   **Example:**

   .. sourcecode:: http

      PUT /index.php/apps/bookmarks/public/rest/v2/tag/politics HTTP/1.1
      Host: example.com
      Accept: application/json

      { "name": "satire" }

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      { "status": "success" }
