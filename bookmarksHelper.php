<?php

// Source: http://www.php.net/manual/de/function.curl-setopt.php#102121
// This works around a safe_mode/open_basedir restriction
function curl_exec_follow(/*resource*/ $ch, /*int*/ &$maxredirect = null) {
	$mr = $maxredirect === null ? 5 : intval($maxredirect);
	if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) {
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $mr > 0);
		curl_setopt($ch, CURLOPT_MAXREDIRS, $mr);
	} else {
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		if ($mr > 0) {
			$newurl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

			$rch = curl_copy_handle($ch);
			curl_setopt($ch, CURLOPT_USERAGENT, "Owncloud Bookmark Crawl");
			curl_setopt($rch, CURLOPT_HEADER, true);
			curl_setopt($rch, CURLOPT_NOBODY, true);
			curl_setopt($rch, CURLOPT_FORBID_REUSE, false);
			curl_setopt($rch, CURLOPT_RETURNTRANSFER, true);
			do {
				curl_setopt($rch, CURLOPT_URL, $newurl);
				$header = curl_exec($rch);
				if (curl_errno($rch)) {
					$code = 0;
				} else {
					$code = curl_getinfo($rch, CURLINFO_HTTP_CODE);
					if ($code == 301 || $code == 302) {
						preg_match('/Location:(.*?)\n/', $header, $matches);
						$newurl = trim(array_pop($matches));
					} else {
						$code = 0;
					}
				}
			} while ($code && --$mr);
			curl_close($rch);
			if (!$mr) {
				if ($maxredirect === null) {
					trigger_error('Too many redirects. When following redirects, libcurl hit the maximum amount.', E_USER_WARNING);
				} else {
					$maxredirect = 0;
				}
				return false;
			}
			curl_setopt($ch, CURLOPT_URL, $newurl);
		}
	}
	return curl_exec($ch);
}

function getURLMetadata($url) {
	//allow only http(s) and (s)ftp
	$protocols = '/^[hs]{0,1}[tf]{0,1}tp[s]{0,1}\:\/\//i';
	//if not (allowed) protocol is given, assume http
	if(preg_match($protocols, $url) == 0) {
		$url = 'http://' . $url;
	}
	$metadata['url'] = $url;
	if (!function_exists('curl_init')){
		return $metadata;
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$page = curl_exec_follow($ch);
	curl_close($ch);

	@preg_match( "/<title>(.*)<\/title>/sUi", $page, $match );
	$metadata['title'] = htmlspecialchars_decode(@$match[1]);
	return $metadata;
}

function analyzeTagRequest($line) {
	$tags = explode(',',$line);
	$filterTag = array();
	foreach($tags as $tag){
		if(trim($tag) != '')
			$filterTag[] = trim($tag);
	}
	return $filterTag;
}

function getNowValue() {
	$CONFIG_DBTYPE = OCP\Config::getSystemValue( "dbtype", "sqlite" );
	if( $CONFIG_DBTYPE == 'sqlite' or $CONFIG_DBTYPE == 'sqlite3' ){
		$_ut = "strftime('%s','now')";
	} elseif($CONFIG_DBTYPE == 'pgsql') {
		$_ut = 'date_part(\'epoch\',now())::integer';
	} else {
		$_ut = "UNIX_TIMESTAMP()";
	}
	return $_ut;
}

function editBookmark($id, $url, $title, $tags = array(), $description='', $is_public=false) {

	$is_public = $is_public ? 1 : 0;
	$user_id = OCP\USER::getUser();

	// Update the record
	$query = OCP\DB::prepare("
	UPDATE *PREFIX*bookmarks SET
		url = ?, title = ?, public = ?, description = ?,
		lastmodified = ".getNowValue() ."
	WHERE id = ?
	AND user_id = ?
	");

	$params=array(
		htmlspecialchars_decode($url),
		htmlspecialchars_decode($title),
		$is_public,
		htmlspecialchars_decode($description),
		$id,
		$user_id,
	);

	$result = $query->execute($params);

	// Abort the operation if bookmark couldn't be set
	// (probably because the user is not allowed to edit this bookmark)
	if ($result->numRows() == 0) exit();


	// Remove old tags
	$sql = "DELETE from *PREFIX*bookmarks_tags  WHERE bookmark_id = ?";
	$query = OCP\DB::prepare($sql);
	$query->execute(array($id));

	// Add New Tags
	addTags($id, $tags);
}

function addTags($bookmark_id, $tags) {
	$query = OCP\DB::prepare("
		INSERT INTO *PREFIX*bookmarks_tags
		(bookmark_id, tag)
		VALUES (?, ?)
	");

	foreach ($tags as $tag) {
		if(empty($tag)) {
			//avoid saving blankspaces
			continue;
		}
		$params = array($bookmark_id, trim($tag));
		$query->execute($params);
	}
}

function addBookmark($url, $title, $tags=array(), $description='', $is_public=false) {
 
	$is_public = $is_public ? 1 : 0;
	//FIXME: Detect and do smth when user adds a known URL
	$_ut = getNowValue();

	$query = OCP\DB::prepare("
		INSERT INTO `*PREFIX*bookmarks`
		(url, title, user_id, public, added, lastmodified, description)
		VALUES (?, ?, ?, ?, $_ut, $_ut, ?)
		");

	$params=array(
		htmlspecialchars_decode($url),
		htmlspecialchars_decode($title),
		OCP\USER::getUser(),
		$is_public,
		$description,
	);
	$query->execute($params);

	$b_id = OCP\DB::insertid('*PREFIX*bookmarks');

	if($b_id !== false) {
		addTags($b_id, $tags);
		return $b_id;
	}
}
