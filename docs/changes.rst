=======
Changes
=======


.. _changes:

Changes in v13.1.0
==================

In v13.1.0 the ``target`` field for bookmarks was introduced, which largely replaces the ``url`` field.
The ``url`` field still exists and can be used, however, while the ``url`` field is guaranteed to only contain http(s), (s)ftp and file links,
the ``target`` field may also contain javascript links. If a bookmark represents a javascript link, the ``url`` field will be set to the empty string.
The ``target`` field was introduced as a security consideration taking into account downstream implementors of this API that
may expose the contents of the ``url`` field directly as links that users click on, and which do not expect this field to contain javascript.


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
