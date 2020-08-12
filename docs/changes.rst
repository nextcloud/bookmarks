=======
Changes
=======


.. _changes:

Breaking changes from v2.x to v3.x
==================================

With the upgrade from v2.x of the bookmarks app to v3.x several breaking changes in the API were introduced which were not possible to avoid.

.. object:: Bookmark

   :param int id: All bookmark IDs are now integer values instead of strings

.. object:: Folder

   :param int id: All folder IDs are now integer values instead of strings

.. get:: /public/rest/v2/bookmark

  This endpoint no longer accepts the ``item[tags]`` query parameter. Use the normal ``tags`` param

.. post:: /public/rest/v2/bookmark

  This endpoint no longer accepts the ``item[tags]`` query parameter. Use the normal ``tags`` param

.. put:: /public/rest/v2/bookmark

  This endpoint no longer accepts the ``item[tags]`` query parameter. Use the normal ``tags`` param
