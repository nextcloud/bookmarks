<?php
namespace OCA\Bookmarks;

class Murmur2Hash {
	public static function hash($str) {
		$l = strlen($str);
		$h = $l;
		$i = 0;

		while ($l >= 4) {
			$k = ((ord(substr($str, $i)) & 0xff)) |
	  ((ord(substr($str, ++$i)) & 0xff) << 8) |
	  ((ord(substr($str, ++$i)) & 0xff) << 16) |
	  ((ord(substr($str, ++$i)) & 0xff) << 24);

			$k = ((($k & 0xffff) * 0x5bd1e995) + (((self::unsignedRightShift($k, 16) * 0x5bd1e995) & 0xffff) << 16));
			$k ^= self::unsignedRightShift($k, 24);
			$k = ((($k & 0xffff) * 0x5bd1e995) + (((self::unsignedRightShift($k, 16) * 0x5bd1e995) & 0xffff) << 16));

			$h = ((($h & 0xffff) * 0x5bd1e995) + (((self::unsignedRightShift($h, 16) * 0x5bd1e995) & 0xffff) << 16)) ^ $k;

			$l -= 4;
			++$i;
		}

		switch ($l) {
		case 3: $h ^= (ord(substr($str, $i + 2)) & 0xff) << 16;
		// no break
		case 2: $h ^= (ord(substr($str, $i + 1)) & 0xff) << 8;
		// no break
		case 1: $h ^= (ord(substr($str, $i)) & 0xff);
		  $h = ((($h & 0xffff) * 0x5bd1e995) + (((self::unsignedRightShift($h, 16) * 0x5bd1e995) & 0xffff) << 16));
  }

		$h ^= self::unsignedRightShift($h, 13);
		$h = ((($h & 0xffff) * 0x5bd1e995) + (((self::unsignedRightShift($h, 16) * 0x5bd1e995) & 0xffff) << 16));
		$h ^= self::unsignedRightShift($h, 15);

		return self::unsignedRightShift($h, 0);
	}

	private static function unsignedRightShift($a, $b) {
		if ($b >= 32 || $b < -32) {
			$m = (int)($b/32);
			$b = $b-($m*32);
		}

		if ($b < 0) {
			$b = 32 + $b;
		}

		if ($b == 0) {
			return (($a>>1) & 0x7fffffff) * 2 + (($a>>$b) & 1);
		}

		if ($a < 0) {
			$a = ($a >> 1);
			$a &= 0x7fffffff;
			$a |= 0x40000000;
			$a = ($a >> ($b - 1));
		} else {
			$a = ($a >> $b);
		}
		return $a;
	}
}
