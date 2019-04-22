# REST API

This is the REST API exposed by Nextcloud Bookmarks.

## Authentication

In order to access the REST API you will need to provide credentials for the user on behalf of which you'd
like to access the bookmarks app. This can be done using Basic Auth and must happen for every request.

### Query bookmarks

```
GET
/index.php/apps/bookmarks/public/rest/v2/bookmark
```

Parameters:

- (optional) `tags[]`: array of tags that bookmarks returned by the endpoint should have
- (optional) `conjunction`: Set to `and` to require all tags to be present, `or` if one should suffice. Default: `or`
- (optional) `page`: if this is non-negative, results will be paginated by 10 bookmarks a page. Default: `0`
- (optional) `sortby`: The column to sort the results by; one of `url`, `title`, `description`, `public`, `lastmodified`, `clickcount`. Default: `lastmodified`.
- (optional) `search[]`: An array of words to search for in the following columns `url`, `title`, `description`
- (optional) `user`: Instead of returning the bookmarks of the current user, return the public bookmarks of the user passed as this parameter.
- (optional) `folder`: Only return bookmarks that are direct children of the folder with the passed ID. The root folder has id `-1`.

Example:

```
GET
/index.php/apps/bookmarks/public/rest/v2/bookmark?tags[]=firsttag&tags[]=secondtag&page=-1
```

```json
{
	"status": "success",
	"data": [{ "id": "7", "title": "Google", "tags": ["firsttag"] /*...*/ }]
}
```

### Create a bookmark

```
POST
/index.php/apps/bookmarks/public/rest/v2/bookmark
```

Parameters:

- `url`: the url of the new bookmark
- (optional) `item[tags][]`: Array of tags for this bookmark (these needn't exist and are created on-the-fly)
- (optional) `title`: the title of the bookmark. If absent the title of the html site referenced by `url` is used
- (optional) `is_public`: Set this parameter (without a value) to mark the new bookmark as public, so that other users can see it
- (optional) `description`: A description for this bookmark
- (optional) `folders`: An array of IDs of the folders this bookmark should reside in.

Example:

```
POST /index.php/apps/bookmarks/public/rest/v2/bookmark?url=http%3A%2F%2Fgoogle.com&title=Google&description=in%20case%20you%20forget&item[tags][]=search-engines&item[tags][]=uselessbookmark
```

```json
{
	"status": "success",
	"item": {
		"id": "7",
		"url": "http://google.com",
		"title": "Google"
		//...
	}
}
```

### Get a bookmark

```
GET /index.php/apps/bookmarks/public/rest/v2/bookmark/:id
```

- `id`: The id of the bookmark to edit

Parameters:

- (optional) `user`: The user this bookmark belongs to

Example:

```
GET /index.php/apps/bookmarks/public/rest/v2/bookmark/7
```

```json
{
	"status": "success",
	"item": {
		"id": "7",
		"url": "http://google.com",
		"title": "Boogle"
		//...
	}
}
```

### Edit a bookmark

```
PUT /index.php/apps/bookmarks/public/rest/v2/bookmark/:id
```

- `id`: The id of the bookmark to edit

Parameters:

- `record_id`: The id of the bookmark to edit
- (optional) `url`: The new url
- (optional) `item[tags][]`: the new tags. Existing tags will be deleted.
- (optional) `title`: The new title
- (optional) `is_public`: Set or leave unset to set the new public status.
- (optional) `description`: The new description.
- (optional) `folders`: The folders this bookmark should reside in.

Example:

```
PUT /index.php/apps/bookmarks/public/rest/v2/bookmark/7?record_id=7&title=Boogle
```

```json
{
	"status": "success",
	"item": {
		"id": "7",
		"url": "http://google.com",
		"title": "Boogle"
		//...
	}
}
```

### Delete a bookmark

```
DELETE /index.php/apps/bookmarks/public/rest/v2/bookmark/:id
```

- `id`: The bookmark to delete

Note: This will remove the bookmark from all folders it resided in.

Parameters: _None_

Example:

```
DELETE /index.php/apps/bookmarks/public/rest/v2/bookmark/7
```

```json
{ "status": "success" }
```

### List all tags

```
GET /index.php/apps/bookmarks/public/rest/v2/tag
```

Parameters: _None_

Example:

```
GET /index.php/apps/bookmarks/public/rest/v2/tag
```

```
["politics", "satire", "tech", "music", "art", "blogs", "personal"]
```

### Delete a tag

```
DELETE /index.php/apps/bookmarks/public/rest/v2/tag
```

Parameters:

- `old_name`: the name of the tag to delete

Example:

```
DELETE /index.php/apps/bookmarks/public/rest/v2/tag?old_name=mytag
```

```
{ "status": "success" }
```

### Rename a tag

```
POST /index.php/apps/bookmarks/public/rest/v2/tag
```

Parameters:

- `old_name`: The name of the tag to rename
- `new_name`: The new name of the tag

Example:

```
POST /index.php/apps/bookmarks/public/rest/v2/tag?old_name=politics&new_name=satire
```

```
{ "status": "success"}
```

### List folders

```
GET /index.php/apps/bookmarks/public/rest/v2/folder
```

Parameters:

- (optional) `root`: The id of the folder to start listing folders from. (Default: `-1`; the root folder)
- (optional) `layers`: How many layers of folders to return at max. By default, all layers are returned.

Example:

```
GET /index.php/apps/bookmarks/public/rest/v2/folder
```

```
{"status": "success", "data": [
  {"id": "1", "title": "work", "parent_folder": "-1"},
  {"id": "2", "title": "personal", "parent_folder": "-1", "children": [
    {"id": "3", "title": "garden", "parent_folder": "2"},
    {"id": "4", "title": "music", "parent_folder": "2"}
  ]},
]}
```

### Show single folder

```
GET /index.php/apps/bookmarks/public/rest/v2/folder/:id
```

- `id`: The id of the folder to show

Parameters: _None_

Example:

```
GET /index.php/apps/bookmarks/public/rest/v2/folder/2
```

```
{"status": "success", "item": {"id": "2", "title": "personal", "parent_folder": "-1"}}
```

### Create folder

```
POST /index.php/apps/bookmarks/public/rest/v2/folder
```

Parameters:

- `title`: The title of the new folder
- `parent_folder`: The id of the parent folder for the new folder

Example:

```
POST /index.php/apps/bookmarks/public/rest/v2/folder

{"title": "sports", "parent_folder": "-1"}
```

```
{ "status": "success", "item": {"id": 5, "title": "sports", "parent_folder": "-1"}}
```

### Edit folders

```
PUT /index.php/apps/bookmarks/public/rest/v2/folder/:id
```

Parameters:

- (optional) `title`: The new title for the folder
- (optional) `parent_folder`: The new parent to move the folder to

Example:

```
PUT /index.php/apps/bookmarks/public/rest/v2/folder/5

{"title": "physical activity"}
```

```
{ "status": "success", "item": {"id": 5, "title": "physical activity", "parent_folder": "-1"}}
```

### Hash folders

```
GET /index.php/apps/bookmarks/public/rest/v2/folder/:id/hash
```

Parameters:

- (optional) `fields[]`: All bookmarks fields that should be hashed (default: `title`, `url`)

Example:

```
GET /index.php/apps/bookmarks/public/rest/v2/folder/5/hash
```

```
{ "status": "success", "data": "65432378"}
```

Description:

This endpoint is useful for synchronizing data between the server and a client. By comparing the hash of the data on your client with the hash from the server you can figure out which parts of the tree have changed.

The algorithm works as follows:

- Hash endpoint: `hashFolder(id, fields)`
- `hashFolder(id, fields)`
  - for all children of the folder
    - if it's a folder
      - add to `childrenHashes`: `hashFolder(folderId, fields)`
    - if it's a bookmark
      - add to `childrenHashes`: `hashBookmark(bookmarkId, fields)`
  - Return `murmur2(to_json({title: folderTitle, children: childrenHashes}))`
- `hashBookmark(id, fields)`
  - for all entries in `fields`
    - set `object[field]` to the value of the associated field of the bookmark
  - Return `murmur2(to_json(object))`
- `murmur2`: [The murmur2 hashing algorithm](https://en.wikipedia.org/wiki/MurmurHash)

### Delete folders

```
DELETE /index.php/apps/bookmarks/public/rest/v2/folder/:id
```

- `id`: The id of the folder to remove

Parameters: _None_

Example:

```
DELETE /index.php/apps/bookmarks/public/rest/v2/folder/2
```

```
{"status": "success"}
```

### Put bookmarks into a folder

```
POST /index.php/apps/bookmarks/public/rest/v2/folder/:id/bookmarks/:bookmark
```

- `id`: The id of the folder
- `bookmark`: The id of the bookmark to put into the folder

Parameters: _None_

Example:

```
POST /index.php/apps/bookmarks/public/rest/v2/folder/2/bookmarks/15
```

```
{"status": "success"}
```

### Remove a bookmark from a folder

```
DELETE /index.php/apps/bookmarks/public/rest/v2/folder/:id/bookmarks/:bookmark
```

- `id`: The id of the folder
- `bookmark`: The id of the bookmark to put into the folder

Parameters: _None_

Example:

```
DELETE /index.php/apps/bookmarks/public/rest/v2/folder/2/bookmarks/15
```

```
{"status": "success"}
```

### Order folder contents

```
PATCH /index.php/apps/bookmarks/public/rest/v2/folder/:id/childorder
```

- `id`: the folder's ID

Parameters:

- `data`: An array of children objects with two keys each: `type` and `id`, where type is either `bookmark` or `folder`

Example:

```
PATCH /index.php/apps/bookmarks/public/rest/v2/folder/2/childorder

{"data": [
  {"type": "folder", "id": "17"},
  {"type": "bookmark", "id": "204"},
  {"type": "bookmark", "id": "192"},
  {"type": "bookmark", "id": "210"}
]}
```

```
{ "status": "success"}
```

### Retrieve current order of folder contents

```
GET /index.php/apps/bookmarks/public/rest/v2/folder/:id/childorder
```

- `id`: the folder's ID

Parameters: _None_

Example:

```
GET /index.php/apps/bookmarks/public/rest/v2/folder/2/childorder
```

```
{ "status": "success", "data": [
  {"type": "folder", "id": "17"},
  {"type": "bookmark", "id": "204"},
  {"type": "bookmark", "id": "192"},
  {"type": "bookmark", "id": "210"}
]}
```
