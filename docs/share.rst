=======
Shares
=======

.. contents::

Share model
============

.. object:: Share

   :param int id: The share's unique id
   :param int folderId: The id of the folder that was shared
   :param string participant: The id of the participant that the folder was shared to
   :param int type: The participant type. Currently either ``0`` for users, or ``1`` for groups.
   :param boolean canWrite: Whether the participant has write access.
   :param boolean canShare: Whether the participant is allowed to reshare the folder or subfolders to other users, including the creation of public links.


Create a share
==============

.. post:: /public/rest/v2/folder/(int:folder_id)/shares

   :synopsis: Create a share for a folder

   .. versionadded:: 3.0.0

   :>json string status: ``success`` or ``error``
   :>json share item: The new share

   **Example:**

   .. sourcecode:: http

      POST /index.php/apps/bookmarks/public/rest/v2/folder/5/shares HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success"
        "item": {
            "id": 5,
            "folderId": 201,
            "participant": "friends",
            "type": 1,
            "canWrite": false,
            "canShare": false
        }
      }

Get folder's shares
===================

.. get:: /public/rest/v2/folder/(int:folder_id)/shares

   :synopsis: Retrieves all shares of a folder

   .. versionadded:: 3.0.0

   :>json string status: ``success`` or ``error``
   :>json array data: The shares of this folder


   **Example:**

   .. sourcecode:: http

      GET /index.php/apps/bookmarks/public/rest/v2/folder/5/shares HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success"
        "data": [
            {
                "id": 5,
                "folderId": 201,
                "participant": "friends",
                "type": 1,
                "canWrite": false,
                "canShare": false
            }
        ]
      }


Get public token
================

.. get:: /public/rest/v2/folder/(int:folder_id)/publictoken

   :synopsis: Retrieve the public token of a folder that has been shared via a public link

   .. versionadded:: 3.0.0

   :>json string status: ``success`` or ``error``
   :>json share item: The public token

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

Get share
=========

.. post:: /public/rest/v2/share/(int:share_id)

   :synopsis: Get a share by id

   .. versionadded:: 3.0.0

   :>json string status: ``success`` or ``error``
   :>json share item: The requested share

   **Example:**

   .. sourcecode:: http

      POST /index.php/apps/bookmarks/public/rest/v2/share/17 HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success"
        "data": [
            {
                "id": 17,
                "folderId": 201,
                "participant": "friends",
                "type": 1,
                "canWrite": false,
                "canShare": false
            }
        ]
      }

Edit share
==========

.. put:: /public/rest/v2/share/(int:share_id)

   :synopsis: Get a share by id

   .. versionadded:: 3.0.0

   :>json string status: ``success`` or ``error``
   :>json share item: The requested share

   **Example:**

   .. sourcecode:: http

      PUT /index.php/apps/bookmarks/public/rest/v2/share/17 HTTP/1.1
      Host: example.com
      Accept: application/json

      {
        "canWrite": true,
        "canShare": false
      }

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success"
        "data": [
            {
                "id": 17,
                "folderId": 201,
                "participant": "friends",
                "type": 1,
                "canWrite": true,
                "canShare": false
            }
        ]
      }

Delete share
============

.. delete:: /public/rest/v2/share/(int:share_id)

   :synopsis: Delete a share

   .. versionadded:: 3.0.0

   :>json string status: ``success`` or ``error``

   **Example:**

   .. sourcecode:: http

      POST /index.php/apps/bookmarks/public/rest/v2/share/17 HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
      }
