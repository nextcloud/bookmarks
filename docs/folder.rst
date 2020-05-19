=======
Folders
=======

.. contents::

Folder model
============

.. object:: Folder

   :param int id: The folder's unique id
   :param string title: The humanly-readable label for the folder
   :param int parent_folder: The folder's parent folder

   .. note::

      The root folder has the magic id ``-1``, which is the same for every user.


Get full hierarchy
==================

.. get:: /public/rest/v2/folder

   :synopsis: Retrieve the folder hierarchy

   .. versionadded:: 0.15.0

   :query int root: The id of the folder whose contents to retrieve (Default: ``-1``, which is the root folder)
   :query int layers: How many layers of folders to return at max. By default, all layers are returned.

   :>json string status: ``success`` or ``error``
   :>json array data: The folder hierarchy

   :>jsonarr int id: The id of the folder
   :>jsonarr string title: the folder title
   :>jsonarr int parent_folder: the folder's parent folder
   :>jsonarr array children: The folder's children (folders only)

   **Example:**

   .. sourcecode:: http

      GET /index.php/apps/bookmarks/public/rest/v2/folder HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success", "data": [
          {"id": "1", "title": "work", "parent_folder": "-1"},
          {"id": "2", "title": "personal", "parent_folder": "-1", "children": [
            {"id": "3", "title": "garden", "parent_folder": "2"},
            {"id": "4", "title": "music", "parent_folder": "2"}
          ]},
        ]
      }

Get single folder
=================

.. get:: /public/rest/v2/folder/(int:id)

   :synopsis: Retrieve a single folder

   .. versionadded:: 0.15.0

   :>json string status: ``success`` or ``error``
   :>json object item: The retrieved folder

   **Example:**

   .. sourcecode:: http

      GET /index.php/apps/bookmarks/public/rest/v2/folder/2 HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
        "item": {
          "id": "2",
          "title": "My Personal Bookmarks",
          "parent_folder": "-1"
        }
      }


Create a folder
===============

.. post:: /public/rest/v2/folder

   :synopsis: Create a new folder

   .. versionadded:: 0.15.0

   :<json string title: The title of the new folder
   :<json int parent_folder: The id of the parent folder for the new folder

   :>json string status: ``success`` or ``error``
   :>json object item: The new folder

   **Example:**

   .. sourcecode:: http

      POST /index.php/apps/bookmarks/public/rest/v2/folder HTTP/1.1
      Host: example.com
      Accept: application/json

      {"title": "sports", "parent_folder": "-1"}

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
        "item": {
          "id": 5,
          "title": "sports",
          "parent_folder": "-1"
        }
      }

Edit a folder
=============

.. put:: /public/rest/v2/folder/(int:id)

   :synopsis: Edit an existing folder

   .. versionadded:: 0.15.0

   :<json string title: The title of the new folder
   :<json int parent_folder: The id of the parent folder of the folder

   :>json string status: ``success`` or ``error``
   :>json object item: The new folder

   **Example:**

   .. sourcecode:: http

      POST /index.php/apps/bookmarks/public/rest/v2/folder/5 HTTP/1.1
      Host: example.com
      Accept: application/json

      {"title": "optional physical activity"}

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
        "item": {
          "id": 5,
          "title": "optional physical activity",
          "parent_folder": "-1"
        }
      }

Hash a folder
=============

.. get:: /public/rest/v2/folder/(int:id)/hash

   :synopsis: Compute the hash of a folder

   .. versionadded:: 1.0.0

   :param array fields: All bookmarks fields that should be hashed (default: ``title``, ``url``)

   :>json string status: ``success`` or ``error``
   :>json string data: The SHA256 hash in hexadecimal notation

   This endpoint is useful for synchronizing data between the server and a client. By comparing the hash of the data on your client with the hash from the server you can figure out which parts of the tree have changed.

   The algorithm works as follows:

    - Hash endpoint: return ``hashFolder(id, fields)``
    - ``hashFolder(id, fields)``

      - set ``childrenHashes`` to empty array
      - for all children of the folder

        - if it's a folder

          - add to ``childrenHashes``: ``hashFolder(folderId, fields)``

        - if it's a bookmark

          - add to ``childrenHashes``: ``hashBookmark(bookmarkId, fields)``

      - set ``object`` to an empty dictionary
      - set ``object[title]`` to the title of the folder, if this is not the root folder
      - set ``object[children]`` to the value of ``childrenHashes``
      - set ``json`` to ``to_json(object)``
      - Return ``sha256(json)``

    - ``hashBookmark(id, fields)``

      - set ``object`` to an empty dictionary/hashmap
      - for all entries in ``fields``

        - set ``object[field]`` to the value of the associated field of the bookmark

      - Return ``sha256(to_json(object))``

    - ``to_json``: A JSON stringification algorithm that adds no unnecessary white-space and doesn't use JSON's backslash escaping unless necessary (character set is UTF-8)
    - ``sha256``: The SHA-256 hashing algorithm

   **Example:**

   .. sourcecode:: http

      GET /index.php/apps/bookmarks/public/rest/v2/folder/5/hash HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      { "status": "success", "data": "6543a23c78aefd0274f3ac98de98723" }

Delete a folder
===============

.. delete:: /public/rest/v2/folder/(int:id)

   :synopsis: Delete a folder

   .. versionadded:: 0.15.0

   :>json string status: ``success`` or ``error``
   :>json object item: The new folder

   **Example:**

   .. sourcecode:: http

      DELETE /index.php/apps/bookmarks/public/rest/v2/folder/5 HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success"
      }

Add bookmark to folder
======================

.. post:: /public/rest/v2/folder/(int:folder_id)/bookmarks/(int:bookmark_id)

   :synopsis: Add a bookmark to a folder

   .. versionadded:: 0.15.0

   :>json string status: ``success`` or ``error``

   **Example:**

   .. sourcecode:: http

      POST /index.php/apps/bookmarks/public/rest/v2/folder/5/bookmarks/418 HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success"
      }

Remove bookmark from folder
===========================

.. delete:: /public/rest/v2/folder/(int:folder_id)/bookmarks/(int:bookmark_id)

   :synopsis: Remove a bookmark from a folder

   .. versionadded:: 0.15.0

   :>json string status: ``success`` or ``error``

   If this is the only folder this bookmark resides in, the bookmark will be deleted entirely.

   **Example:**

   .. sourcecode:: http

      DELETE /index.php/apps/bookmarks/public/rest/v2/folder/5/bookmarks/418 HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success"
      }

Get folder's content order
==========================

.. get:: /public/rest/v2/folder/(int:folder_id)/childorder

   :synopsis: Retrieve the order of contents of a folder

   .. versionadded:: 0.15.0

   :>json string status: ``success`` or ``error``
   :>json array data: An ordered list of child items

   :>jsonarr string type: Either ``folder`` or ``bookmark``
   :>jsonarr string id: The id of the bookmark or folder

   **Example:**

   .. sourcecode:: http

      GET /index.php/apps/bookmarks/public/rest/v2/folder/5/childorder HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
        "data": [
          {"type": "folder", "id": "17"},
          {"type": "bookmark", "id": "204"},
          {"type": "bookmark", "id": "192"},
          {"type": "bookmark", "id": "210"}
        ]
      }

Set folder's content order
==========================

.. patch:: /public/rest/v2/folder/(int:folder_id)/childorder

   :synopsis: Set the order of contents of a folder

   .. versionadded:: 0.15.0

   :<json array data: An ordered list of child items

   :<jsonarr string type: Either ``folder`` or ``bookmark``
   :<jsonarr string id: The id of the bookmark or folder

   :>json string status: ``success`` or ``error``

   **Example:**

   .. sourcecode:: http

      PATCH /index.php/apps/bookmarks/public/rest/v2/folder/5/childorder HTTP/1.1
      Host: example.com
      Accept: application/json

      {
        "status": "success",
        "data": [
          {"type": "folder", "id": "17"},
          {"type": "bookmark", "id": "204"},
          {"type": "bookmark", "id": "192"},
          {"type": "bookmark", "id": "210"}
        ]
      }

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success"
      }

Get folder's contents
=====================

.. get:: /public/rest/v2/folder/(int:folder_id)/children

   :synopsis: Retrieve all of a folder's contents (with varying depth)

   .. versionadded:: 3.0.0

   :query int layers: How many layers of descendants to return at max. By default only immediate children are returned.

   :>json string status: ``success`` or ``error``
   :>json array data: An ordered list of child items

   :>jsonarr string type: Either ``folder`` or ``bookmark``
   :>jsonarr string id: The id of the bookmark or folder

   If the type of the item is ``folder``

   :>jsonarr string title: The title of the folder
   :>jsonarr string userId: The owner of the folder
   :>jsonarr array children: The children of the folder. This is only set, when the number of layers to return includes this folder.

   If the type of the item is ``bookmark``

   :>jsonarr string url: The URL of the bookmark
   :>jsonarr string title: The title of the bookmark
   :>jsonarr string description: Description of the bookmark

   **Example:**

   .. sourcecode:: http

      GET /index.php/apps/bookmarks/public/rest/v2/folder/5/children HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
        "data": [
          {"type": "folder", "id": "17", "title": "foo", "userId": "admin"},
          {"type": "bookmark", "id": "204", "title": "Nextcloud", "url": "https://nextcloud.com/"},
          {"type": "bookmark", "id": "204", "title": "Google", "url": "https://google.com/"},
        ]
      }


Get public token
================

.. get:: /public/rest/v2/folder/(int:folder_id)/publictoken

   :synopsis: Retrieve the public token of a folder that has been shared via a public link

   .. versionadded:: 3.0.0

   :>json string status: ``success`` or ``error``
   :>json string item: The public token

   To use the token either make API requests with it (see :ref:`authentication`). Or point your browser to ``https://yournextcloud.com/index.php/apps/bookmarks/public/{token}``

   **Example:**

   .. sourcecode:: http

      GET /index.php/apps/bookmarks/public/rest/v2/folder/5/publictoken HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
        "item": "dk3J8Qm"
      }

Get public token
================

.. post:: /public/rest/v2/folder/(int:folder_id)/publictoken

   :synopsis: Create a public link for a folder

   .. versionadded:: 3.0.0

   :>json string status: ``success`` or ``error``
   :>json string item: The token that can be used to access the folder publicly.

   **Example:**

   .. sourcecode:: http

      POST /index.php/apps/bookmarks/public/rest/v2/folder/5/publictoken HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
        "item": "dk3J8Qm"
      }

Delete public token
===================

.. delete:: /public/rest/v2/folder/(int:folder_id)/publictoken

   :synopsis: Remove the public link for a folder

   .. versionadded:: 3.0.0

   :>json string status: ``success`` or ``error``

   **Example:**

   .. sourcecode:: http

      POST /index.php/apps/bookmarks/public/rest/v2/folder/5/publictoken HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
      }
