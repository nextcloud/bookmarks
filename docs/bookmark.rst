=========
Bookmarks
=========

.. contents::

Bookmark model
==============

A bookmark has at least the following properties

.. object:: Bookmark

   :param int id: The bookmarks unique id
   :param string url: The Uniform Resource Locator that this bookmark represents
   :param string title: A short humanly readable label for the bookmark
   :param string description: A longer description or note on the bookmark
   :param array tags: A list of tags this bookmark is tagged with
   :param array folders: The folders this bookmark has been added to


Query bookmarks
===============

.. get:: /public/rest/v2/bookmark

   :synopsis: Filter and query all bookmarks by the authenticated user.

   .. versionadded:: 0.11.0

   :query conjunction: Set to ``and`` to require all tags to be present, ``or`` if one should suffice. Default: ``or``
   :query tags[]: An array of tags that bookmarks returned by the endpoint should have
   :query page: if this is non-negative, results will be paginated by 10 bookmarks a page. Default: ``0``.
   :query sortby: The column to sort the results by; one of ``url``, ``title``, ``description``, ``public``, ``lastmodified``, ``clickcount``. Default: ``lastmodified``.
   :query search[]: An array of words to search for in the following columns ``url``, ``title``, ``description``
   :query folder: Only return bookmarks that are direct children of the folder with the passed ID. The root folder has id ``-1``.
   :query url: Only return bookmarks with this URL. This will only ever return just one bookmark or none, because the app doesn't store duplicates. Thus, with this parameter you can test whether a URL exists in the user's bookmarks. This parameter cannot be mixed with the others.
   :query unavailable: Only return bookmarks that are dead links, i.e. return 404 status codes or similar. This parameter cannot be mixed with the others.

   :>json string status: ``success`` or ``error``
   :>json array data: The list of resulting bookmarks

   **Example:**

   .. sourcecode:: http

      GET /index.php/apps/bookmarks/public/rest/v2/bookmark?tags[]=firsttag&tags[]=secondtag&page=-1 HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
        "data": [{ "id": 7, "title": "Google", "tags": ["firsttag"] }]
      }

Create a bookmark
=================

.. post:: /public/rest/v2/bookmark

   :synopsis: Create a bookmark

   .. versionadded:: 0.11.0

   :param id: the url of the new bookmark
   :param array tags: Array of tags for this bookmark (these needn't exist and are created on-the-fly; this used to be `item[tags][]`, which is now deprecated)
   :param string title: the title of the bookmark. If absent the title of the html site referenced by `url` is used
   :param string description: A description for this bookmark
   :param array folders: An array of IDs of the folders this bookmark should reside in.

   :>json string status: ``success`` or ``error``
   :>json object item: The created bookmark

   **Example:**

   .. sourcecode:: http

      POST /index.php/apps/bookmarks/public/rest/v2/bookmark?tags[]=firsttag&tags[]=secondtag&page=-1 HTTP/1.1
      Host: example.com
      Accept: application/json

      {
        "url": "http://google.com",
        "title": "Google",
        "description":"in case i forget",
        "tags": ["search-engines", "uselessbookmark"]
      }

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
        "item": {
          "id": 7,
          "url": "http://google.com",
          "title": "Google",
          "description":"in case i forget",
          "tags": ["search-engines", "uselessbookmark"],
          "folders": [-1]
        }
      }

Get a bookmark
==============

.. get:: /public/rest/v2/bookmark/(int:id)

   :synopsis: Retrieve a bookmark

   .. versionadded:: 0.11.0

   :>json string status: ``success`` or ``error``
   :>json object item: The retrieved bookmark

   **Example:**

   .. sourcecode:: http

      GET /index.php/apps/bookmarks/public/rest/v2/bookmark/7 HTTP/1.1
      Host: example.com
      Accept: application/json


   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
        "item": {
          "id": 7,
          "url": "http://google.com",
          "title": "Google",
          "description":"in case i forget",
          "tags": ["search-engines", "uselessbookmark"],
          "folders": [-1]
        }
      }

Edit a bookmark
===============

.. put:: /public/rest/v2/bookmark/(int:id)

   :synopsis: Edit a bookmark

   .. versionadded:: 0.11.0

   :param id: the url of the new bookmark
   :param array tags: Array of tags for this bookmark (these needn't exist and are created on-the-fly; this used to be `item[tags][]`, which is now deprecated)
   :param string title: the title of the bookmark. If absent the title of the html site referenced by `url` is used
   :param string description: A description for this bookmark
   :param array folders: An array of IDs of the folders this bookmark should reside in.

   :>json string status: ``success`` or ``error``
   :>json object item: The new bookmark after editing

   **Example:**

   .. sourcecode:: http

      PUT /index.php/apps/bookmarks/public/rest/v2/bookmark/7 HTTP/1.1
      Host: example.com
      Accept: application/json

      { "title": "Boogle" }


   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
        "item": {
          "id": 7,
          "url": "http://google.com",
          "title": "Boogle",
          "description":"in case i forget",
          "tags": ["search-engines", "uselessbookmark"],
          "folders": [-1]
        }
      }

Delete a bookmark
=================

.. delete:: /public/rest/v2/bookmark/(int:id)

   :synopsis: Delete a bookmark

   .. versionadded:: 0.11.0

   :>json string status: ``success`` or ``error``

   **Example:**

   .. sourcecode:: http

      DELETE /index.php/apps/bookmarks/public/rest/v2/bookmark/7 HTTP/1.1
      Host: example.com
      Accept: application/json


   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success"
      }

Get a preview image
===================

.. get:: /public/rest/v2/bookmark/(int:id)/image

   :synopsis: Retrieve the preview image of a bookmark

   .. versionadded:: 1.0.0

   **Example:**

   .. sourcecode:: http

      GET /index.php/apps/bookmarks/public/rest/v2/bookmark/7/image HTTP/1.1
      Host: example.com


   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: image/png

      ... binary data ...

Get a favicon
=============

.. get:: /public/rest/v2/bookmark/(int:id)/favicon

   :synopsis: Retrieve the favicon of a bookmark

   .. versionadded:: 1.0.0

   **Example:**

   .. sourcecode:: http

      GET /index.php/apps/bookmarks/public/rest/v2/bookmark/7/favicon HTTP/1.1
      Host: example.com


   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: image/png

      ... binary data ...

Export all bookmarks
====================

.. get:: /public/rest/v2/bookmark/export

   :synopsis: Export all bookmarks of the current user in a HTML file.

   .. versionadded:: 0.11.0

   **Example:**

   .. sourcecode:: http

      GET /index.php/apps/bookmarks/public/rest/v2/bookmark/export HTTP/1.1
      Host: example.com


   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: text/html

      <html>
      ...

