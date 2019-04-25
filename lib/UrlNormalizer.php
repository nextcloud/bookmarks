<?php
namespace OCA\Bookmarks;

class UrlNormalizer {
	private $normalizer;


	const SCHEMES = ['http', 'https', 'ftp', 'sftp', 'file', 'gopher', 'imap', 'mms',
		   'news', 'nntp', 'telnet', 'prospero', 'rsync', 'rtsp', 'rtspu',
		   'svn', 'git', 'ws', 'wss'];
	const SCHEME_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	const IP_CHARS = '0123456789.:';
	const DEFAULT_PORT = [
	'http'=> '80',
	'https'=> '443',
	'ws'=> '80',
	'wss'=> '443',
	'ftp'=> '21',
	'sftp'=> '22',
	'ldap'=> '389'
];
	const QUOTE_EXCEPTIONS = [
	'path'=> ' /?+#',
	'query'=> ' &=+#',
	'fragment'=> ' +#'
];

	public function __construct() {
	}

	/**
	 * @brief Normalize Url
	 * @param string $url Url to load and analyze
	 * @return string Normalized url;
	 */
	public function normalize($url) {
		$url = trim($url);
		if ($url === '') {
			return '';
		}
		$parts = self::split($url);

		if (isset($parts['scheme']) && strlen($parts['scheme']) > 0) {
			$netloc = $parts['netloc'];
			if (in_array($parts['scheme'], self::SCHEMES)) {
				$path = self::normalize_path($parts['path']);
			} else {
				$path = $parts['path'];
			}
			# url is relative, netloc (if present) is part of path
		} else {
			$netloc = $parts['path'];
			$path = '';
			if (strpos($netloc, '/') !== false) {
				$netloc = substr(netloc, 0, strpos($netloc, '/'));
				$path_raw = substr($netloc, strpos($netloc, '/')+1);
				$path = self::normalize_path('/' + $path_raw);
			}
		}
		list($username, $password, $host, $port) = self::split_netloc($netloc);
		$host = self::normalize_host($host);
		$port = self::normalize_port($parts['scheme'], $port);
		$query = self::normalize_query($parts['query']);
		$fragment = self::normalize_fragment($parts['fragment']);
		return self::construct(['scheme' => $parts['scheme'], 'username' => $username, 'password' => $password, 'host' => $host, 'port' => $port, 'path' => $path, 'query' => $query, 'fragment' => $fragment]);
	}

	public static function construct($parts) {
		$url = '';

		if (strlen($parts['scheme'])>0) {
			if (in_array($parts['scheme'], self::SCHEMES)) {
				$url .= $parts['scheme'] . '://';
			} else {
				$url .= $parts['scheme'] . ':';
			}
		}
		if (strlen($parts['username'])>0 && strlen($parts['password'])>0) {
			$url .= $parts['username'] . ':' . $parts['password'] . '@';
		} elseif (strlen($parts['username'])>0) {
			$url .= $parts['username'] . '@';
		}
		$url .= $parts['host'];
		if (strlen($parts['port'])>0) {
			$url .= ':' . $parts['port'];
		}
		if (strlen($parts['path'])>0) {
			$url .= $parts['path'];
		}
		if (strlen($parts['query'])>0) {
			$url .= '?' . $parts['query'];
		}
		if (strlen($parts['fragment'])>0) {
			$url .= '#' . $parts['fragment'];
		}
		return $url;
	}

	public static function normalize_host($host) {
		if (strpos($host, 'xn--') === false) {
			return $host;
		}
		return idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
	}

	public static function normalize_port($scheme, $port) {
		if (!isset($scheme)) {
			return $port;
		}
		if (isset($port) && $port != self::DEFAULT_PORT[$scheme]) {
			return $port;
		}
		return '';
	}

	public static function normalize_path($path) {
		if (in_array($path, ['//', '/', ''])) {
			return '/';
		}
		$npath = self::get_absolute_path(self::unquote($path, self::QUOTE_EXCEPTIONS['path']));
		if ($path[strlen($path)-1] === '/' && $npath != '/') {
			$npath .= '/';
		}
		return $npath;
	}

	public static function get_absolute_path($path) {
		$parts = array_filter(explode('/', $path), 'strlen');
		$absolutes = [];
		foreach ($parts as $part) {
			if ('.' == $part) {
				continue;
			}
			if ('..' == $part) {
				array_pop($absolutes);
			} else {
				$absolutes[] = $part;
			}
		}
		return '/'.implode('/', $absolutes);
	}

	public static function normalize_query($query) {
		if ($query === '' || strlen($query) <= 2) {
			return '';
		}
		$nquery = self::unquote($query, self::QUOTE_EXCEPTIONS['query']);
		if (strpos($nquery, ';') !== false && strpos($nquery, '&') === false) {
			return $nquery;
		}
		$params = explode('&', $nquery);
		$nparams = [];
		foreach ($params as $param) {
			array_push($nparams, $params);
		}
		sort($nparams);
		return implode('&', $nparams);
	}

	public static function normalize_fragment($fragment) {
		return self::unquote($fragment, self::QUOTE_EXCEPTIONS['fragment']);
	}


	public static function unquote($text, $exceptions=[]) {
		$_hextochr = [];
		for ($i = 0; $i < 256; $i++) {
			$_hextochr[dechex($i)] = chr($i);
			$_hextochr[strtoupper(dechex($i))] = chr($i);
		}
		if (strlen($text) == 0) {
			return $text;
		}
		if (!isset($text)) {
			throw new Exception('text is not set and thus cannot be unquoted');
		}
		if (strpos($text, '%') === false) {
			return $text;
		}
		$s = explode('%', $text);
		$res = $s[0];
		for ($i=1; $i < count($s); $i++) {
			$h = $s[$i];
			$c = isset($_hextochr[substr($h, 0, 2)]) ? $_hextochr[substr($h, 0, 2)] : '';
			if (strlen($c) > 0 && false === strpos($exceptions, $c) && hexdec(substr($h, 0, 2)) < 128) {
				if (strlen($h) > 2) {
					$res .= $c . substr($h, 2);
				} else {
					$res .= $c;
				}
			} else {
				$res .= '%' . $h;
			}
		}
		return $res;
	}

	public static function split($url) {
		$scheme = $netloc = $path = $query = $fragment = '';
		$ip6_start = strpos($url, '[');
		$scheme_end = strpos($url, ':');
		if ($ip6_start !== false && $scheme_end !== false && $ip6_start < $scheme_end) {
			$scheme_end = -1;
		}
		if ($scheme_end > 0) {
			for ($i = 0; $i < $scheme_end; $i++) {
				$c = $url[$i];
				if (strpos(self::SCHEME_CHARS, $c) === false) {
					break;
				} else {
					$scheme = strtolower(substr($url, 0, $scheme_end));
					$rest = ltrim(substr($url, $scheme_end), ':/');
				}
			}
		}
		if (!$scheme) {
			$rest = $url;
		}
		$l_path = strpos($rest, '/');
		$l_query = strpos($rest, '?');
		$l_frag = strpos($rest, '#');
		if ($l_path > 0) {
			if ($l_query > 0 && $l_frag > 0) {
				$netloc = substr($rest, 0, $l_path);
				$path = substr($rest, $l_path, min($l_query, $l_frag)-$l_path);
			} elseif ($l_query > 0) {
				if ($l_query > $l_path) {
					$netloc = substr($rest, 0, $l_path);
					$path = substr($rest, $l_path, $l_query-$l_path);
				} else {
					$netloc = substr($rest, 0, $l_query);
					$path = '';
				}
			} elseif ($l_frag > 0) {
				$netloc = substr($rest, 0, $l_path);
				$path = substr($rest, $l_path, $l_frag-$l_path);
			} else {
				$netloc = substr($rest, 0, $l_path);
				$path = substr($rest, $l_path);
			}
		} else {
			if ($l_query > 0) {
				$netloc = substr($rest, 0, $l_query);
			} elseif ($l_frag > 0) {
				$netloc = substr($rest, 0, $l_frag);
			} else {
				$netloc = $rest;
			}
		}
		if ($l_query > 0) {
			if ($l_frag > 0) {
				$query = substr($rest, $l_query+1, $l_frag-($l_query+1));
			} else {
				$query = substr($rest, $l_query+1);
			}
		}
		if ($l_frag > 0) {
			$fragment = substr($rest, $l_frag+1);
		}
		if (!$scheme) {
			$path = $netloc . $path;
			$netloc = '';
		}
		return ['scheme' => $scheme, 'netloc'=> $netloc, 'path' => $path, 'query'=>$query, 'fragment' => $fragment];
	}

	public static function _clean_netloc($netloc) {
		return strtolower(rtrim($netloc, '.:'));
	}

	public static function split_netloc($netloc) {
		$username = $password = $host = $port = '';
		if (strpos($netloc, '@') !== false) {
			$user_pw = substr($netloc, 0, strpos($netloc, '@'));
			$netloc = substr($netloc, strpos($netloc, '@')+1);
			if (strpos($user_pw, ':') !== false) {
				$username = substr($user_pw, 0, strpos($user_pw, ':'));
				$password = substr($user_pw, strpos($user_pw, ':')+1);
			} else {
				$username = $user_pw;
			}
		}
		$netloc = self::_clean_netloc($netloc);
		if (strpos($netloc, ':') !== false && $netloc[strlen($netloc)-1] !== ']') {
			$host = substr($netloc, 0, strpos($netloc, ':'));
			$port = substr($netloc, strpos($netloc, ':')+1);
		} else {
			$host = $netloc;
		}
		return [$username, $password, $host, $port];
	}
}
