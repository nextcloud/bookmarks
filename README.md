Bookmarks app
============

![](https://github.com/nextcloud/bookmarks/raw/master/screenshots/Bookmarks.png)

Maintainers:
------------
- [Blizzz](https://github.com/Blizzz)
- [Marcel Klehr](https://github.com/marcelklehr)

Developer setup info:
---------------------
### Installation:
Install it from the app store in Nextcloud itself or just clone this repo into your apps directory on your server.


Status :
---------
Rewrite by [Stefan Klemm] aka ganomi (https://github.com/ganomi)

* This is a refactored / rewritten version of the bookmarks app using the app framework
* Dependency Injection for user and db is used througout the controllers
* The Routing features a consistent rest api
* The Routing provides some legacy routes, so that for exampe the Android Bookmarks App still works.
* Merged all the changes from https://github.com/nextcloud/bookmarks/pull/68 and added visual fixes. App uses the App Framework Styling on the Client side now.

There is a publicly available api that provides access to bookmarks per user. (This is usefull in connection with the Wordpress Plugin https://github.com/mario-nolte/oc2wp-bookmarks)

REST API
---------
In order to access the REST API you will need to provide credentials for the user on behalf of which you'd
like to access the bookmarks app. This can be done using Basic Auth and must happen for every request.


### Query bookmarks
```
GET
/apps/bookmarks/public/rest/v2/bookmark
```


Parameters:
* (optional) `tags[]`: array of tags that bookmarks returned by the endpoint should have
* (optional) `conjunction`: Set to `and` to require all tags to be present, `or` if one should suffice. Default: `or`
* (optional) `page`: if this is non-negative, results will be paginated by 10 bookmarks a page. Default: `0`
* (optional) `sortby`: The column to sort the results by; one of `url`, `title`, `description`, `public`, `lastmodified`, `clickcount`. Default: `lastmodified`.
* (optional) `search[]`: An array of words to search for in the following columns `url`, `title`, `description`
* (optional) `user`: Instead of returning the bookmarks of the current user, return the public bookmarks of the user passed as this parameter.

Example:
```
GET
/apps/bookmarks/public/rest/v2/bookmark?tags[]=firsttag&tags[]=secondtag&page=-1
```

```json
{
  "status": "success",
  "data": [
    {"id": "7", "title": "Google", "tags": ["firsttag"], /*...*/ }
  ]
}
```

### Create a bookmark
```
POST
/apps/bookmarks/public/rest/v2/bookmark
```

Parameters:
* `url`: the url of the new bookmark
* (optional) `item[]`: Array of tags for this bookmark (these needn't exist and are created on-the-fly)
* (optional) `titel`: the title of the bookmark. If absent the title of the html site referenced by `url` is used
* (optional) `is_public`: Set this parameter (without a value) to mark the new bookmark as public, so that other users can see it
* (optional) `description`: A description for this bookmark

Example:
```
POST /apps/bookmarks/public/rest/v2/bookmark?url=http%3A%2F%2Fgoogle.com&title=Google&description=in%20case%20you%20forget
```

```json
{ "status": "success",
  "item": {
    "id": "7",
	"url": "http://google.com",
	"title": "Google",
	//...
  }
}
```

### Edit a bookmark
```
PUT /apps/bookmarks/public/rest/v2/bookmark/:id
```

* `id`: The id of the bookmark to edit

Parameters:
* `record_id`: The id of the bookmark to edit
* (optional) `url`: The new url
* (optional) `item[]`: the new tags. Existing tags will be deleted.
* (optional) `title`: The new title
* (optional) `is_public`: Set or leave unset to set the new public status.
* (optional) `description`: The new description.

Example:
```
PUT /apps/bookmarks/public/rest/v2/bookmark/7?record_id=7&title=Boogle
```

```json
{ "status": "success",
  "item": {
    "id": "7",
	"url": "http://google.com",
	"title": "Boogle",
	//...
  }
}
```

### Delete a bookmark
```
DELETE /apps/bookmarks/public/rest/v2/bookmark/:id
```

* `id`: The bookmark to delete

Parameters: *None*

Example:
```
DELETE /apps/bookmarks/public/rest/v2/bookmark/7
```

```json
{ "status": "success" }
```

### List all tags
```
GET /apps/bookmarks/public/rest/v2/tag
```

Parameters: *None*

Example:
```
GET /apps/bookmarks/public/rest/v2/tag
```

```
["politics", "satire", "tech", "music", "art", "blogs", "personal"]
```


### Delete a tag
```
DELETE /apps/bookmarks/public/rest/v2/tag
```

Parameters:
* `old_name`: the name of the tag to delete

Example:

```
DELETE /apps/bookmarks/public/rest/v2/tag?old_name=mytag
```

```
{ "status": "success" }
```

### Rename a tag
```
POST /apps/bookmarks/public/rest/v2/tag
```

Parameters:
* `old_name`: The name of the tag to rename
* `new_name`: The new name of the tag

Example:
```
POST /apps/bookmarks/public/rest/v2/tag?old_name=politics&new_name=satire
```

```
{ "status": "success"}
```

