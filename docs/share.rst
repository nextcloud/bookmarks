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

   :<json string participant: the id of the sharee (mandatory)
   :<json int type: the type of sharee (currently either ``1`` if it's a group, or ``0`` if it's a single user; mandatory)
   :<json bool canWrite: Whether the participant has write access (optional; defaults to ``false``)
   :<json bool canShare: Whether the sharee should be allowed to re-share the folder with others (optional; defaults to ``false``)

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

Get share
=========

.. get:: /public/rest/v2/share/(int:share_id)

   :synopsis: Get a share by id

   .. versionadded:: 3.0.0

   :>json string status: ``success`` or ``error``
   :>json share item: The requested share

   **Example:**

   .. sourcecode:: http

      GET /index.php/apps/bookmarks/public/rest/v2/share/17 HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success"
        "item": {
                "id": 17,
                "folderId": 201,
                "participant": "friends",
                "type": 1,
                "canWrite": false,
                "canShare": false
            }
      }

Edit share
==========

.. put:: /public/rest/v2/share/(int:share_id)

   :synopsis: Edit a share

   .. versionadded:: 3.0.0

   :<json bool canWrite: Whether the sharee should be allowed to edit the shared contents  (mandatory)
   :<json bool canShare: Whether the sharee should be allowed to re-share the folder with others (mandatory)

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
        "item": {
                "id": 17,
                "folderId": 201,
                "participant": "friends",
                "type": 1,
                "canWrite": true,
                "canShare": false
            }
      }

Delete share
============

.. delete:: /public/rest/v2/share/(int:share_id)

   :synopsis: Delete a share

   .. versionadded:: 3.0.0

   :>json string status: ``success`` or ``error``

   **Example:**

   .. sourcecode:: http

      DELETE /index.php/apps/bookmarks/public/rest/v2/share/17 HTTP/1.1
      Host: example.com
      Accept: application/json

   **Response:**

   .. sourcecode:: http

      HTTP/1.1 200 OK
      Content-Type: application/json

      {
        "status": "success",
      }
